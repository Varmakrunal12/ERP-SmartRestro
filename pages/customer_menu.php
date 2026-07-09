<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Auto table allocation via QR scan
$tableId = null;
$tableNumber = null;

if (isset($_GET['table'])) {
    $tableNum = (int)$_GET['table'];
    $stmt = $pdo->prepare('SELECT id, table_number, status FROM restaurant_tables WHERE table_number = ?');
    $stmt->execute([$tableNum]);
    $table = $stmt->fetch();
    if ($table) {
        $tableId = $table['id'];
        $tableNumber = $table['table_number'];
        // Auto-allocate: mark as occupied if available
        if ($table['status'] === 'available') {
            $pdo->prepare('UPDATE restaurant_tables SET status = "occupied" WHERE id = ?')->execute([$tableId]);
        }
        // Auto-login as user if not logged in
        if (!isset($_SESSION['user_id'])) {
            $userAcc = $pdo->query("SELECT id, username, role FROM users WHERE role='user' LIMIT 1")->fetch();
            if ($userAcc) {
                $_SESSION['user_id'] = $userAcc['id'];
                $_SESSION['username'] = $userAcc['username'];
                $_SESSION['role'] = 'user';
            }
        }
    }
} elseif (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// If logged in but no table from GET, check session or allow browsing
if (!$tableId && isset($_SESSION['user_id'])) {
    // Allow browsing without a table (logged-in user)
}

// --- Auto-menu availability logic ---
// Find menu items that are unavailable based on inventory
$unavailableItems = $pdo->query("
    SELECT DISTINCT mii.menu_item_id
    FROM menu_item_ingredients mii
    JOIN inventory inv ON mii.inventory_id = inv.id
    WHERE inv.available_qty < mii.qty_required
")->fetchAll(PDO::FETCH_COLUMN);

// Set all to available first
$pdo->exec("UPDATE menu_items SET is_available = 1");

// Mark unavailable ones
if (!empty($unavailableItems)) {
    $placeholders = implode(',', array_fill(0, count($unavailableItems), '?'));
    $stmt = $pdo->prepare("UPDATE menu_items SET is_available = 0 WHERE id IN ($placeholders)");
    $stmt->execute($unavailableItems);
}

// Fetch all menu items
$menuItems = $pdo->query("SELECT * FROM menu_items ORDER BY category, item_name")->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories
$categories = array_unique(array_column($menuItems, 'category'));

$pageTitle = 'Menu';
$currentPage = 'customer_menu';
require_once '../includes/header.php';
?>

<!-- Welcome Banner -->
<?php if ($tableNumber): ?>
<div class="card welcome-banner" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: #fff; text-align: center; padding: 2rem 1.5rem; margin-bottom: 1.5rem; border: none; position: relative; overflow: hidden;">
    <div style="position: absolute; top: -20px; right: -20px; font-size: 6rem; opacity: 0.15; transform: rotate(15deg);">🍽️</div>
    <div style="position: absolute; bottom: -15px; left: -15px; font-size: 4rem; opacity: 0.1; transform: rotate(-15deg);">🍕</div>
    <h1 style="font-size: 1.8rem; margin: 0 0 0.5rem 0; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2); color: white;">
        Welcome to SmartRestro! 🍽️
    </h1>
    <p style="font-size: 1.1rem; margin: 0; opacity: 0.9; color: white;">
        You are seated at <strong style="background: rgba(255,255,255,0.2); padding: 0.2rem 0.6rem; border-radius: 6px;">Table <?php echo htmlspecialchars($tableNumber); ?></strong>
    </p>
    <p style="font-size: 0.85rem; margin-top: 0.75rem; opacity: 0.75; color: white;">Browse our menu and place your order below</p>
</div>
<?php endif; ?>

<div class="page-header" style="margin-bottom: 1rem;">
    <h1 class="page-title">🍽️ Our Menu</h1>
</div>

<!-- Category Filter Pills -->
<div class="category-filter" style="margin-bottom: 1.5rem;">
    <button class="filter-pill active" onclick="filterCategory('all')">🍴 All</button>
    <button class="filter-pill" onclick="filterCategory('Starters')">🥗 Starters</button>
    <button class="filter-pill" onclick="filterCategory('Main Course')">🍛 Main Course</button>
    <button class="filter-pill" onclick="filterCategory('Beverages')">🥤 Beverages</button>
    <button class="filter-pill" onclick="filterCategory('Desserts')">🍰 Desserts</button>
</div>

<!-- Hidden table input -->
<input type="hidden" id="customerTableId" value="<?php echo $tableId; ?>">

<!-- Order Layout: Menu + Cart -->
<div class="order-layout">

    <!-- Left: Menu Grid -->
    <div>
        <div class="menu-grid">
            <?php foreach ($menuItems as $item):
                $isAvailable = (bool)$item['is_available'];
                $cardClass = 'menu-card' . (!$isAvailable ? ' unavailable' : '');
                $category = htmlspecialchars($item['category']);
                $itemName = htmlspecialchars($item['item_name']);
                $price = number_format($item['price'], 2);
                $imageUrl = $item['image_url'];

                // Category emoji map
                $catEmojis = [
                    'Starters' => '🥗',
                    'Main Course' => '🍛',
                    'Beverages' => '🥤',
                    'Desserts' => '🍰'
                ];
                $catEmoji = $catEmojis[$item['category']] ?? '🍽️';

                // Food emoji fallback
                $foodEmojis = ['🍕', '🍔', '🌮', '🍜', '🍲', '🥘', '🍱', '🥗', '🍣', '🧁'];
                $fallbackEmoji = $foodEmojis[array_sum(array_map('ord', str_split($item['item_name']))) % count($foodEmojis)];
            ?>
                <div class="<?php echo $cardClass; ?>" data-category="<?php echo $category; ?>" style="position: relative; overflow: hidden;">
                    <!-- Item Image -->
                    <div class="menu-item-image" style="position: relative; width: 100%; height: 180px; overflow: hidden; border-radius: var(--radius, 12px) var(--radius, 12px) 0 0;">
                        <?php if (!empty($imageUrl)): ?>
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                 alt="<?php echo $itemName; ?>"
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="display: none; width: 100%; height: 100%; background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); align-items: center; justify-content: center; font-size: 3.5rem;">
                                <?php echo $fallbackEmoji; ?>
                            </div>
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); display: flex; align-items: center; justify-content: center; font-size: 3.5rem;">
                                <?php echo $fallbackEmoji; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isAvailable): ?>
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 2; backdrop-filter: blur(1.5px);">
                                <span style="color: #fff; font-weight: 700; font-size: 1.1rem; background: rgba(220,38,38,0.9); padding: 0.4rem 1rem; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Out of Stock</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Header -->
                    <div class="menu-card-header" style="padding: 0.75rem 1rem 0.25rem;">
                        <h3 class="menu-item-name" style="margin: 0 0 0.4rem 0; font-size: 1rem;"><?php echo $itemName; ?></h3>
                        <span class="menu-category-badge"><?php echo $catEmoji; ?> <?php echo $category; ?></span>
                    </div>

                    <!-- Price & Availability -->
                    <div style="padding: 0.25rem 1rem 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <span class="menu-price" style="font-size: 1.15rem; font-weight: 700; color: var(--primary);">₹<?php echo $price; ?></span>
                        <?php if ($isAvailable): ?>
                            <span class="availability-badge available">Available</span>
                        <?php else: ?>
                            <span class="availability-badge unavailable">Unavailable</span>
                        <?php endif; ?>
                    </div>

                    <!-- Footer: Add to Cart -->
                    <?php if ($isAvailable): ?>
                        <div class="menu-card-footer" style="padding: 0.5rem 1rem 1rem;">
                            <button class="btn btn-primary btn-block add-to-cart-btn"
                                    onclick="addToCart(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['price']; ?>)">
                                🛒 Add to Cart
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($menuItems)): ?>
            <div class="text-center" style="padding: 3rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <p class="text-muted" style="font-size: 1.1rem;">No menu items available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Cart Panel -->
    <div>
        <div class="cart-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.2rem;">🛒 Your Cart</h3>
                <span class="cart-count" id="cartCount">0</span>
            </div>

            <div id="cartItems">
                <div class="cart-empty">
                    <div class="empty-icon" style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛒</div>
                    <p class="text-muted">Your cart is empty</p>
                    <p class="text-muted" style="font-size: 0.8rem;">Browse the menu and add items</p>
                </div>
            </div>

            <div class="cart-summary" id="cartSummary" style="display: none;">
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span id="cartSubtotal">₹0.00</span>
                </div>
                <div class="cart-summary-row">
                    <span>GST (18%)</span>
                    <span id="cartGst">₹0.00</span>
                </div>
                <div class="cart-summary-row total">
                    <span>Grand Total</span>
                    <span id="cartGrandTotal">₹0.00</span>
                </div>
            </div>

            <button class="btn btn-success btn-block btn-lg" id="placeOrderBtn"
                    style="margin-top: 1rem; display: none; font-size: 1rem;">
                🍽_ Place Order
            </button>
        </div>
    </div>
</div>

<style>
    /* Customer menu specific styles */
    .welcome-banner {
        animation: fadeInDown 0.6s ease-out;
    }

    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .menu-card {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        overflow: hidden;
    }

    .menu-card:not(.unavailable):hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    }

    .menu-card:not(.unavailable):hover .menu-item-image img {
        transform: scale(1.05);
    }

    .menu-card.unavailable {
        filter: blur(1.5px) grayscale(80%);
        pointer-events: none;
        opacity: 0.7;
    }

    .add-to-cart-btn {
        transition: all 0.2s ease;
    }

    .add-to-cart-btn:active {
        transform: scale(0.95);
    }

    .cart-panel {
        position: sticky;
        top: 1rem;
    }

    .cart-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-secondary);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        animation: slideIn 0.2s ease-out;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateX(10px); }
        to { opacity: 1; transform: translateX(0); }
    }

    .cart-item-info {
        flex: 1;
    }

    .cart-item-name {
        font-weight: 500;
        font-size: 0.9rem;
        margin-bottom: 0.15rem;
    }

    .cart-item-price {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .cart-quantity {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .cart-quantity button {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: 1px solid var(--border-glass);
        background: var(--bg-primary);
        color: var(--text-primary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.15s ease;
    }

    .cart-quantity button:hover {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .cart-quantity span {
        font-weight: 600;
        min-width: 1.2rem;
        text-align: center;
    }

    .cart-item-total {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--primary-light);
        min-width: 4rem;
        text-align: right;
    }

    .cart-remove {
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        font-size: 1rem;
        padding: 0.2rem;
        transition: transform 0.15s ease;
        line-height: 1;
    }

    .cart-remove:hover {
        transform: scale(1.2);
    }

    .filter-pill {
        transition: all 0.2s ease;
    }

    .filter-pill:hover {
        transform: translateY(-1px);
    }

    .filter-pill.active {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
</style>

<script>
    // ---- Cart Logic ----
    let cart = [];

    function formatCurrency(amount) {
        return '₹' + parseFloat(amount).toFixed(2);
    }

    function addToCart(id, name, price) {
        const existing = cart.find(item => item.id === id);
        if (existing) {
            existing.quantity++;
        } else {
            cart.push({ id: id, name: name, price: parseFloat(price), quantity: 1 });
        }
        updateCartDisplay();
        showToast(name + ' added to cart!', 'success');
    }

    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        updateCartDisplay();
    }

    function updateQuantity(id, delta) {
        const item = cart.find(i => i.id === id);
        if (!item) return;
        item.quantity += delta;
        if (item.quantity <= 0) {
            removeFromCart(id);
            return;
        }
        updateCartDisplay();
    }

    function updateCartDisplay() {
        const container = document.getElementById('cartItems');
        const summary = document.getElementById('cartSummary');
        const placeBtn = document.getElementById('placeOrderBtn');
        const countBadge = document.getElementById('cartCount');

        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        countBadge.textContent = totalItems;

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="cart-empty">
                    <div class="empty-icon" style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛒</div>
                    <p class="text-muted">Your cart is empty</p>
                    <p class="text-muted" style="font-size: 0.8rem;">Browse the menu and add items</p>
                </div>`;
            summary.style.display = 'none';
            placeBtn.style.display = 'none';
            return;
        }

        let html = '';
        let subtotal = 0;

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            html += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">${formatCurrency(item.price)} each</div>
                    </div>
                    <div class="cart-quantity">
                        <button onclick="updateQuantity(${item.id}, -1)">−</button>
                        <span>${item.quantity}</span>
                        <button onclick="updateQuantity(${item.id}, 1)">+</button>
                    </div>
                    <div class="cart-item-total">${formatCurrency(itemTotal)}</div>
                    <button class="cart-remove" onclick="removeFromCart(${item.id})" title="Remove">&times;</button>
                </div>`;
        });

        container.innerHTML = html;

        const gst = subtotal * 0.18;
        const grandTotal = subtotal + gst;

        document.getElementById('cartSubtotal').textContent = formatCurrency(subtotal);
        document.getElementById('cartGst').textContent = formatCurrency(gst);
        document.getElementById('cartGrandTotal').textContent = formatCurrency(grandTotal);

        summary.style.display = 'block';
        placeBtn.style.display = 'block';
    }

    // ---- Category Filter ----
    function filterCategory(category) {
        // Update active pill
        document.querySelectorAll('.filter-pill').forEach(pill => pill.classList.remove('active'));
        event.target.classList.add('active');

        // Filter menu cards
        document.querySelectorAll('.menu-card').forEach(card => {
            if (category === 'all' || card.dataset.category === category) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // ---- Place Order ----
    document.getElementById('placeOrderBtn').addEventListener('click', function() {
        if (cart.length === 0) {
            showToast('Your cart is empty!', 'error');
            return;
        }

        const tableId = document.getElementById('customerTableId').value;
        if (!tableId || tableId === '' || tableId === 'null') {
            showToast('No table assigned. Please scan a QR code or select a table.', 'error');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '⏳ Placing Order...';

        const items = cart.map(item => ({
            menu_item_id: item.id,
            quantity: item.quantity,
            price: item.price
        }));

        postData('../api/place_order.php', {
            table_id: parseInt(tableId),
            items: items
        }).then(response => {
            if (response && response.success) {
                cart = [];
                updateCartDisplay();
                showToast('🎉 Order placed successfully!', 'success');
                // Show success message
                const menuGrid = document.querySelector('.menu-grid');
                if (menuGrid) {
                    const overlay = document.createElement('div');
                    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
                    overlay.innerHTML = `
                        <div style="background:var(--bg-secondary);border:1px solid var(--border-glass);border-radius:16px;padding:3rem;text-align:center;max-width:400px;margin:1rem;animation:slideUp 0.3s ease-out;box-shadow:var(--shadow-lg);">
                            <div style="font-size:4rem;margin-bottom:1rem;">🎉</div>
                            <h2 style="margin:0 0 0.5rem;font-size:1.5rem;color:var(--text-primary);">Order Placed!</h2>
                            <p style="color:var(--text-secondary);margin:0 0 1rem;">Your order has been sent to the kitchen. We'll have it ready soon!</p>
                            <p style="color:var(--text-muted);font-size:0.9rem;margin:0 0 1.5rem;">Order #${response.order_id || ''}</p>
                            <button class="btn btn-primary btn-lg" onclick="this.closest('div[style*=fixed]').remove();">Continue Browsing</button>
                        </div>`;
                    document.body.appendChild(overlay);
                }
            } else {
                showToast(response?.message || 'Failed to place order. Please try again.', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '🍽️ Place Order';
        }).catch(err => {
            showToast('Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = '🍽️ Place Order';
        });
    });

    // ---- Post Data Helper ----
    function postData(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(res => res.json());
    }
</script>
<?php require_once '../includes/footer.php'; ?>
