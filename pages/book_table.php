<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkAuth();

$pageTitle = 'Book Table';
$currentPage = 'book_table';
require_once '../includes/header.php';

// Fetch all restaurant tables
$tables = $pdo->query("SELECT * FROM restaurant_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);

// Count availability
$availableCount = 0;
foreach ($tables as $table) {
    if ($table['status'] === 'available') {
        $availableCount++;
    }
}
?>

<div class="page-header">
    <h1 class="page-title">🪑 Book a Table</h1>
    <p class="text-muted">Select an available table to book and start your dining experience.</p>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon green">🪑</div>
        <div class="stat-value"><?php echo $availableCount; ?> / <?php echo count($tables); ?></div>
        <div class="stat-label">Tables Available Now</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">🍽️ Restaurant Floor Plan</h2>
    </div>
    
    <div style="padding: 1.5rem;">
        <?php if (empty($tables)): ?>
            <div class="text-center text-muted" style="padding: 3rem;">
                <p style="font-size: 3rem;">🪑</p>
                <p>No tables are registered in the system yet. Please contact the administrator.</p>
            </div>
        <?php else: ?>
            <div class="tables-grid">
                <?php foreach ($tables as $table): 
                    $status = $table['status'];
                    $statusColors = [
                        'available' => '#22c55e',
                        'reserved' => '#f59e0b',
                        'occupied' => '#ef4444'
                    ];
                    $badgeClass = 'badge-' . $status;
                ?>
                    <div class="table-card <?php echo $status; ?>" style="background: var(--bg-secondary); border: 1px solid var(--border-glass); border-radius: 16px; padding: 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; transition: transform 0.2s, box-shadow 0.2s;">
                        <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Table</div>
                        <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: var(--text-primary);"><?php echo htmlspecialchars($table['table_number']); ?></div>
                        
                        <span class="badge <?php echo $badgeClass; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                        
                        <div style="width: 100%; margin-top: 0.5rem;">
                            <?php if ($status === 'available'): ?>
                                <button class="btn btn-primary btn-block" onclick="bookTable(<?php echo $table['id']; ?>, <?php echo $table['table_number']; ?>)">
                                    ⚡ Reserve &amp; Order
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-block" disabled style="cursor: not-allowed; opacity: 0.5;">
                                    🔒 Unavailable
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function bookTable(tableId, tableNumber) {
    confirmAction(`Would you like to book Table ${tableNumber} and view the menu?`, () => {
        const formData = new FormData();
        formData.append('table_id', tableId);
        formData.append('new_status', 'reserved');

        fetch('../api/book_table.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`🎉 Table ${tableNumber} reserved successfully! Redirecting...`, 'success');
                // Redirect to menu page with the booked table allocated
                setTimeout(() => {
                    window.location.href = `customer_menu.php?table=${tableNumber}`;
                }, 1500);
            } else {
                showToast(data.message || 'Failed to book the table.', 'error');
            }
        })
        .catch(error => {
            showToast('Network error. Please try again.', 'error');
            console.error('Error booking table:', error);
        });
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
