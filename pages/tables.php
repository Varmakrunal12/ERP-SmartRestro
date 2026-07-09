<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin']);

// Handle Add Table POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_table') {
        $tableNumber = trim($_POST['table_number'] ?? '');
        if (!empty($tableNumber)) {
            // Check for duplicate table number
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE table_number = ?");
            $checkStmt->execute([$tableNumber]);
            if ($checkStmt->fetchColumn() > 0) {
                $_SESSION['toast'] = ['msg' => 'Table number already exists!', 'type' => 'error'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO restaurant_tables (table_number, status) VALUES (?, 'available')");
                $stmt->execute([$tableNumber]);
                $_SESSION['toast'] = ['msg' => 'Table added successfully!', 'type' => 'success'];
            }
        }
        header('Location: tables.php');
        exit;
    }
}

$pageTitle = 'Table Management';
$currentPage = 'tables';
require_once '../includes/header.php';

// Fetch all tables
$tables = $pdo->query("SELECT * FROM restaurant_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);

// Count statuses
$statusCounts = ['available' => 0, 'reserved' => 0, 'occupied' => 0];
foreach ($tables as $table) {
    $statusCounts[$table['status']]++;
}
?>

<div class="page-header flex-between">
    <h1 class="page-title">🪑 Table Management</h1>
    <button class="btn btn-primary" onclick="openModal('addTableModal')">+ Add Table</button>
</div>

<!-- Status Summary -->
<div class="status-summary mb-4">
    <div class="status-summary-item">
        <span class="status-dot green"></span>
        <span>Available: <strong><?php echo $statusCounts['available']; ?></strong></span>
    </div>
    <div class="status-summary-item">
        <span class="status-dot amber"></span>
        <span>Reserved: <strong><?php echo $statusCounts['reserved']; ?></strong></span>
    </div>
    <div class="status-summary-item">
        <span class="status-dot red"></span>
        <span>Occupied: <strong><?php echo $statusCounts['occupied']; ?></strong></span>
    </div>
</div>

<!-- Tables Grid -->
<div class="tables-grid">
    <?php foreach ($tables as $table): ?>
    <div class="table-card <?php echo $table['status']; ?>">
        <div class="table-label">Table</div>
        <div class="table-number"><?php echo htmlspecialchars($table['table_number']); ?></div>
        <span class="badge badge-<?php echo $table['status']; ?>">
            <?php echo ucfirst($table['status']); ?>
        </span>

        <div class="table-qr mt-2">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . str_replace('/pages/tables.php', '/pages/customer_menu.php', $_SERVER['SCRIPT_NAME']) . '?table=' . $table['table_number']); ?>"
                 alt="QR Code - Table <?php echo htmlspecialchars($table['table_number']); ?>"
                 width="80" height="80" loading="lazy">
        </div>

        <div class="table-actions mt-2">
            <?php if ($table['status'] === 'available'): ?>
                <button class="btn btn-warning btn-sm" onclick="updateTableStatus(<?php echo $table['id']; ?>, 'reserved')">
                    Reserve
                </button>
                <button class="btn btn-success btn-sm" onclick="updateTableStatus(<?php echo $table['id']; ?>, 'occupied')">
                    Seat
                </button>
            <?php elseif ($table['status'] === 'reserved'): ?>
                <button class="btn btn-success btn-sm" onclick="updateTableStatus(<?php echo $table['id']; ?>, 'occupied')">
                    Seat
                </button>
                <button class="btn btn-secondary btn-sm" onclick="updateTableStatus(<?php echo $table['id']; ?>, 'available')">
                    Cancel
                </button>
            <?php elseif ($table['status'] === 'occupied'): ?>
                <button class="btn btn-danger btn-sm" onclick="confirmAction('Free this table?', () => updateTableStatus(<?php echo $table['id']; ?>, 'available'))">
                    Free
                </button>
                <a href="orders.php?table_id=<?php echo $table['id']; ?>" class="btn btn-primary btn-sm">
                    View Order
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($tables)): ?>
    <div class="text-center text-muted" style="grid-column: 1 / -1; padding: 3rem;">
        <p style="font-size: 3rem;">🪑</p>
        <p>No tables added yet. Click "Add Table" to get started.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Add Table Modal -->
<div class="modal-overlay" id="addTableModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Table</h3>
            <button class="modal-close" onclick="closeModal('addTableModal')">&times;</button>
        </div>
        <form method="POST" action="tables.php">
            <input type="hidden" name="action" value="add_table">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="table_number">Table Number</label>
                    <input type="number" id="table_number" name="table_number" class="form-input"
                           placeholder="e.g. 1, 2, 3..." min="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTableModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Table</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateTableStatus(tableId, newStatus) {
    const formData = new FormData();
    formData.append('table_id', tableId);
    formData.append('new_status', newStatus);

    fetch('../api/book_table.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Table status updated!', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message || 'Failed to update table.', 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    });
}

<?php if (isset($_SESSION['toast'])): ?>
    showToast('<?php echo addslashes($_SESSION['toast']['msg']); ?>', '<?php echo $_SESSION['toast']['type']; ?>');
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
