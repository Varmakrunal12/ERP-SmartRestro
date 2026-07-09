<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin', 'kitchen']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_item') {
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');

        if (!empty($itemName) && !empty($category) && $price > 0) {
            $stmt = $pdo->prepare("INSERT INTO menu_items (item_name, category, price, image_url, is_available) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$itemName, $category, $price, $imageUrl ?: null]);
            $_SESSION['toast'] = ['msg' => 'Menu item added successfully!', 'type' => 'success'];
        } else {
            $_SESSION['toast'] = ['msg' => 'Please fill in all fields correctly.', 'type' => 'error'];
        }
        header('Location: menu.php');
        exit;
    }

    if ($_POST['action'] === 'edit_item') {
        $id = intval($_POST['item_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');

        if ($id > 0 && !empty($itemName) && !empty($category) && $price > 0) {
            $stmt = $pdo->prepare("UPDATE menu_items SET item_name = ?, category = ?, price = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$itemName, $category, $price, $imageUrl ?: null, $id]);
            $_SESSION['toast'] = ['msg' => 'Menu item updated successfully!', 'type' => 'success'];
        }
        header('Location: menu.php');
        exit;
    }

    if ($_POST['action'] === 'delete_item') {
        $id = intval($_POST['item_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['msg' => 'Menu item deleted.', 'type' => 'success'];
        }
        header('Location: menu.php');
        exit;
    }
}

// AUTO-MENU LOGIC: Update availability based on inventory
$unavailableItems = $pdo->query("
    SELECT DISTINCT mi.id
    FROM menu_items mi
    INNER JOIN menu_item_ingredients mii ON mi.id = mii.menu_item_id
    INNER JOIN inventory i ON mii.inventory_id = i.id
    WHERE i.available_qty < mii.qty_required
")->fetchAll(PDO::FETCH_COLUMN);

$pdo->exec("UPDATE menu_items SET is_available = 1");

if (!empty($unavailableItems)) {
    $placeholders = implode(',', array_fill(0, count($unavailableItems), '?'));
    $stmt = $pdo->prepare("UPDATE menu_items SET is_available = 0 WHERE id IN ($placeholders)");
    $stmt->execute($unavailableItems);
}

$pageTitle = 'Menu Management';
$currentPage = 'menu';
require_once '../includes/header.php';

// Fetch all menu items with low stock details
$menuItemsQuery = $pdo->query("
    SELECT mi.*,
           GROUP_CONCAT(
               CASE WHEN i.available_qty < mii.qty_required
                    THEN CONCAT(i.ingredient_name, ' (', i.available_qty, '/', mii.qty_required, ' ', i.unit, ')')
                    ELSE NULL
               END SEPARATOR ', '
           ) AS low_stock_ingredients
    FROM menu_items mi
    LEFT JOIN menu_item_ingredients mii ON mi.id = mii.menu_item_id
    LEFT JOIN inventory i ON mii.inventory_id = i.id
    GROUP BY mi.id
    ORDER BY mi.category, mi.item_name
");
$menuItems = $menuItemsQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header flex-between">
    <h1 class="page-title">📋 Menu Management</h1>
    <button class="btn btn-primary" onclick="openModal('addItemModal')">+ Add Item</button>
</div>

<!-- Category Filter -->
<div class="category-filter mb-4">
    <button class="filter-pill active" data-category="all" onclick="filterCategory('all')">All</button>
    <button class="filter-pill" data-category="Starters" onclick="filterCategory('Starters')">Starters</button>
    <button class="filter-pill" data-category="Main Course" onclick="filterCategory('Main Course')">Main Course</button>
    <button class="filter-pill" data-category="Beverages" onclick="filterCategory('Beverages')">Beverages</button>
    <button class="filter-pill" data-category="Desserts" onclick="filterCategory('Desserts')">Desserts</button>
</div>

<!-- Menu Grid -->
<div class="menu-grid">
    <?php foreach ($menuItems as $item): ?>
    <div class="menu-card <?php echo !$item['is_available'] ? 'unavailable' : ''; ?>"
         data-category="<?php echo htmlspecialchars($item['category']); ?>">
        
        <!-- Food Image -->
        <div class="menu-item-image">
            <?php if (!empty($item['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                     loading="lazy"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22><rect fill=%22%231a1f3a%22 width=%22400%22 height=%22300%22/><text x=%22200%22 y=%22160%22 text-anchor=%22middle%22 font-size=%2260%22>🍽️</text></svg>'">
            <?php else: ?>
                <div class="menu-image-placeholder">🍽️</div>
            <?php endif; ?>
            <?php if (!$item['is_available']): ?>
                <div class="out-of-stock-overlay">Out of Stock</div>
            <?php endif; ?>
        </div>

        <div class="menu-card-body">
            <div class="menu-card-header">
                <div class="menu-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                <span class="menu-category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
            </div>

            <div class="menu-price">₹<?php echo number_format($item['price'], 2); ?></div>

            <span class="availability-badge <?php echo $item['is_available'] ? 'available' : 'unavailable'; ?>">
                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
            </span>

            <?php if (!$item['is_available'] && !empty($item['low_stock_ingredients'])): ?>
            <div class="low-stock-info mt-1">
                ⚠️ Low: <?php echo htmlspecialchars($item['low_stock_ingredients']); ?>
            </div>
            <?php endif; ?>

            <div class="menu-card-footer mt-2">
                <button class="btn btn-secondary btn-sm"
                        onclick="editMenuItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', '<?php echo addslashes($item['category']); ?>', <?php echo $item['price']; ?>, '<?php echo addslashes($item['image_url'] ?? ''); ?>')">
                    ✏️ Edit
                </button>
                <form method="POST" action="menu.php" style="display:inline;" onsubmit="return confirm('Delete this menu item?');">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($menuItems)): ?>
    <div class="text-center text-muted" style="grid-column: 1 / -1; padding: 3rem;">
        <p style="font-size: 3rem;">📋</p>
        <p>No menu items yet. Click "Add Item" to get started.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Menu Item</h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
        </div>
        <form method="POST" action="menu.php">
            <input type="hidden" name="action" value="add_item">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_item_name">Item Name</label>
                    <input type="text" id="add_item_name" name="item_name" class="form-input"
                           placeholder="e.g. Butter Chicken" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_category">Category</label>
                        <select id="add_category" name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="Starters">Starters</option>
                            <option value="Main Course">Main Course</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Desserts">Desserts</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_price">Price (₹)</label>
                        <input type="number" id="add_price" name="price" class="form-input"
                               placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_image_url">Image URL</label>
                    <input type="url" id="add_image_url" name="image_url" class="form-input"
                           placeholder="https://example.com/food-image.jpg">
                    <small class="text-muted">Paste an image URL for the dish photo</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal-overlay" id="editItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Menu Item</h3>
            <button class="modal-close" onclick="closeModal('editItemModal')">&times;</button>
        </div>
        <form method="POST" action="menu.php">
            <input type="hidden" name="action" value="edit_item">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_item_name">Item Name</label>
                    <input type="text" id="edit_item_name" name="item_name" class="form-input" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_category">Category</label>
                        <select id="edit_category" name="category" class="form-select" required>
                            <option value="Starters">Starters</option>
                            <option value="Main Course">Main Course</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Desserts">Desserts</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_price">Price (₹)</label>
                        <input type="number" id="edit_price" name="price" class="form-input"
                               step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_image_url">Image URL</label>
                    <input type="url" id="edit_image_url" name="image_url" class="form-input"
                           placeholder="https://example.com/food-image.jpg">
                    <small class="text-muted">Paste an image URL for the dish photo</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editMenuItem(id, name, category, price, imageUrl) {
    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_item_name').value = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_image_url').value = imageUrl || '';
    openModal('editItemModal');
}

<?php if (isset($_SESSION['toast'])): ?>
    showToast('<?php echo addslashes($_SESSION['toast']['msg']); ?>', '<?php echo $_SESSION['toast']['type']; ?>');
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
