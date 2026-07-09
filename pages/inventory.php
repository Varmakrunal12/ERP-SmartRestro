<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin', 'kitchen']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_ingredient') {
        $name = trim($_POST['ingredient_name'] ?? '');
        $availableQty = floatval($_POST['available_qty'] ?? 0);
        $minReqQty = floatval($_POST['min_req_qty'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');

        if (!empty($name) && !empty($unit)) {
            $stmt = $pdo->prepare("INSERT INTO inventory (ingredient_name, available_qty, min_req_qty, unit) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $availableQty, $minReqQty, $unit]);
            $_SESSION['toast'] = ['msg' => 'Ingredient added successfully!', 'type' => 'success'];
        } else {
            $_SESSION['toast'] = ['msg' => 'Please fill in all required fields.', 'type' => 'error'];
        }
        header('Location: inventory.php');
        exit;
    }

    if ($_POST['action'] === 'edit_ingredient') {
        $id = intval($_POST['ingredient_id'] ?? 0);
        $name = trim($_POST['ingredient_name'] ?? '');
        $minReqQty = floatval($_POST['min_req_qty'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');

        if ($id > 0 && !empty($name) && !empty($unit)) {
            $stmt = $pdo->prepare("UPDATE inventory SET ingredient_name = ?, min_req_qty = ?, unit = ? WHERE id = ?");
            $stmt->execute([$name, $minReqQty, $unit, $id]);
            $_SESSION['toast'] = ['msg' => 'Ingredient updated successfully!', 'type' => 'success'];
        }
        header('Location: inventory.php');
        exit;
    }

    // NEW: Handle update stock via POST form (fallback)
    if ($_POST['action'] === 'update_stock') {
        $id = intval($_POST['ingredient_id'] ?? 0);
        $qty = floatval($_POST['quantity'] ?? 0);

        if ($id > 0 && $qty >= 0) {
            $stmt = $pdo->prepare("UPDATE inventory SET available_qty = ? WHERE id = ?");
            $stmt->execute([$qty, $id]);
            $_SESSION['toast'] = ['msg' => 'Stock updated successfully!', 'type' => 'success'];
        }
        header('Location: inventory.php');
        exit;
    }
}

$pageTitle = 'Inventory Management';
$currentPage = 'inventory';
require_once '../includes/header.php';

// Fetch stats
$totalIngredients = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory WHERE available_qty <= min_req_qty AND available_qty > 0")->fetchColumn();
$outOfStockCount = $pdo->query("SELECT COUNT(*) FROM inventory WHERE available_qty <= 0")->fetchColumn();

// Fetch all inventory items
$inventoryItems = $pdo->query("
    SELECT *,
           CASE
               WHEN available_qty <= 0 THEN 'out_of_stock'
               WHEN available_qty <= min_req_qty THEN 'low_stock'
               ELSE 'in_stock'
           END AS stock_status
    FROM inventory
    ORDER BY
        CASE WHEN available_qty <= 0 THEN 0
             WHEN available_qty <= min_req_qty THEN 1
             ELSE 2
        END,
        ingredient_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate max qty for stock level bar
$maxQty = 1;
foreach ($inventoryItems as $item) {
    $compareVal = max($item['available_qty'], $item['min_req_qty'] * 2);
    if ($compareVal > $maxQty) {
        $maxQty = $compareVal;
    }
}
?>

<div class="page-header flex-between">
    <h1 class="page-title">📦 Inventory Management</h1>
    <button class="btn btn-primary" onclick="openModal('addIngredientModal')">+ Add Ingredient</button>
</div>

<!-- Stats Grid -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-value"><?php echo $totalIngredients; ?></div>
        <div class="stat-label">Total Ingredients</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">⚠️</div>
        <div class="stat-value"><?php echo $lowStockCount; ?></div>
        <div class="stat-label">Low Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">🚫</div>
        <div class="stat-value"><?php echo $outOfStockCount; ?></div>
        <div class="stat-label">Out of Stock</div>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Ingredient Inventory</h2>
    </div>
    <?php if (count($inventoryItems) > 0): ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Ingredient</th>
                <th>Available Qty</th>
                <th>Min Required</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Stock Level</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inventoryItems as $index => $item): ?>
            <?php
                $rowClass = '';
                if ($item['stock_status'] === 'out_of_stock') $rowClass = 'out-of-stock';
                elseif ($item['stock_status'] === 'low_stock') $rowClass = 'low-stock';

                $stockPercent = ($maxQty > 0) ? min(($item['available_qty'] / $maxQty) * 100, 100) : 0;
                $stockClass = 'stock-ok';
                if ($item['stock_status'] === 'out_of_stock') $stockClass = 'stock-critical';
                elseif ($item['stock_status'] === 'low_stock') $stockClass = 'stock-low';
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><?php echo $index + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($item['ingredient_name']); ?></strong></td>
                <td><?php echo number_format($item['available_qty'], 2); ?></td>
                <td><?php echo number_format($item['min_req_qty'], 2); ?></td>
                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                <td>
                    <?php if ($item['stock_status'] === 'in_stock'): ?>
                        <span class="badge badge-success">In Stock</span>
                    <?php elseif ($item['stock_status'] === 'low_stock'): ?>
                        <span class="badge badge-warning">Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Out of Stock</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="stock-level <?php echo $stockClass; ?>">
                        <div class="stock-level-fill" style="width: <?php echo $stockPercent; ?>%;"></div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm"
                            onclick="openUpdateStockModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['ingredient_name']); ?>', <?php echo $item['available_qty']; ?>)">
                        📦 Update
                    </button>
                    <button class="btn btn-secondary btn-sm"
                            onclick="openEditModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['ingredient_name']); ?>', <?php echo $item['min_req_qty']; ?>, '<?php echo addslashes($item['unit']); ?>')">
                        ✏️ Edit
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="text-center text-muted" style="padding: 3rem;">
        <p style="font-size: 3rem;">📦</p>
        <p>No ingredients in inventory. Click "Add Ingredient" to get started.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Update Stock Modal -->
<div class="modal-overlay" id="updateStockModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Stock</h3>
            <button class="modal-close" onclick="closeModal('updateStockModal')">&times;</button>
        </div>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="action" value="update_stock">
            <input type="hidden" name="ingredient_id" id="updateStockId">
            <div class="modal-body">
                <p class="mb-2">Ingredient: <strong id="updateStockName"></strong></p>
                <p class="mb-2 text-muted">Current Qty: <span id="updateStockCurrent"></span></p>
                <div class="form-group">
                    <label class="form-label" for="updateStockQty">New Quantity</label>
                    <input type="number" id="updateStockQty" name="quantity" class="form-input"
                           placeholder="Enter new quantity" step="0.01" min="0" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateStockModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Ingredient Modal -->
<div class="modal-overlay" id="addIngredientModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Ingredient</h3>
            <button class="modal-close" onclick="closeModal('addIngredientModal')">&times;</button>
        </div>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="action" value="add_ingredient">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_ingredient_name">Ingredient Name</label>
                    <input type="text" id="add_ingredient_name" name="ingredient_name" class="form-input"
                           placeholder="e.g. Chicken Breast" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_available_qty">Available Quantity</label>
                        <input type="number" id="add_available_qty" name="available_qty" class="form-input"
                               placeholder="0" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_min_req_qty">Minimum Required</label>
                        <input type="number" id="add_min_req_qty" name="min_req_qty" class="form-input"
                               placeholder="0" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_unit">Unit</label>
                    <select id="add_unit" name="unit" class="form-select" required>
                        <option value="">Select Unit</option>
                        <option value="kg">kg</option>
                        <option value="liters">liters</option>
                        <option value="pieces">pieces</option>
                        <option value="grams">grams</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addIngredientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Ingredient</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Ingredient Modal -->
<div class="modal-overlay" id="editIngredientModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Ingredient</h3>
            <button class="modal-close" onclick="closeModal('editIngredientModal')">&times;</button>
        </div>
        <form method="POST" action="inventory.php">
            <input type="hidden" name="action" value="edit_ingredient">
            <input type="hidden" name="ingredient_id" id="edit_ingredient_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_ingredient_name">Ingredient Name</label>
                    <input type="text" id="edit_ingredient_name" name="ingredient_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_min_req_qty">Minimum Required</label>
                    <input type="number" id="edit_min_req_qty" name="min_req_qty" class="form-input"
                           step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_unit">Unit</label>
                    <select id="edit_unit" name="unit" class="form-select" required>
                        <option value="kg">kg</option>
                        <option value="liters">liters</option>
                        <option value="pieces">pieces</option>
                        <option value="grams">grams</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editIngredientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUpdateStockModal(id, name, currentQty) {
    document.getElementById('updateStockId').value = id;
    document.getElementById('updateStockName').textContent = name;
    document.getElementById('updateStockCurrent').textContent = currentQty;
    document.getElementById('updateStockQty').value = currentQty;
    openModal('updateStockModal');
}

function openEditModal(id, name, minReqQty, unit) {
    document.getElementById('edit_ingredient_id').value = id;
    document.getElementById('edit_ingredient_name').value = name;
    document.getElementById('edit_min_req_qty').value = minReqQty;
    document.getElementById('edit_unit').value = unit;
    openModal('editIngredientModal');
}

<?php if (isset($_SESSION['toast'])): ?>
    showToast('<?php echo addslashes($_SESSION['toast']['msg']); ?>', '<?php echo $_SESSION['toast']['type']; ?>');
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
