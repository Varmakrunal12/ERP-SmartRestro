<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['kitchen', 'admin']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$_POST['new_status'], (int)$_POST['order_id']]);
        header('Location: kitchen.php');
        exit;
    }
}

$pageTitle = 'Kitchen Dashboard';
$currentPage = 'kitchen';

// --- Stats ---
$pendingCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$preparingCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'preparing'")->fetchColumn();
$servedTodayCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'served' AND DATE(order_time) = CURDATE()")->fetchColumn();
$maxPendingId = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM orders WHERE status = 'pending'")->fetchColumn();

// --- Active Orders (pending + preparing) with table info ---
$activeOrders = $pdo->query("
    SELECT o.id, o.table_id, o.status, o.subtotal, o.gst_amount, o.grand_total, o.order_time,
           rt.table_number
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    WHERE o.status IN ('pending', 'preparing')
    ORDER BY FIELD(o.status, 'pending', 'preparing'), o.order_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch order items for each active order
$orderItems = [];
if (!empty($activeOrders)) {
    $orderIds = array_column($activeOrders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("
        SELECT od.order_id, od.quantity, od.price, mi.item_name, mi.image_url
        FROM order_details od
        JOIN menu_items mi ON od.menu_item_id = mi.id
        WHERE od.order_id IN ($placeholders)
        ORDER BY od.id ASC
    ");
    $itemStmt->execute($orderIds);
    $allItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allItems as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
}

// --- Inventory Status ---
$inventoryItems = $pdo->query("
    SELECT id, ingredient_name, available_qty, min_req_qty, unit
    FROM inventory
    ORDER BY ingredient_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🍳 Kitchen Dashboard</h1>
    <p class="text-muted">Real-time order management &amp; inventory overview</p>
</div>

<!-- Audio Autoplay Unlock Banner -->
<div id="audioUnlockBanner" class="card" style="margin-bottom: 1.5rem; border: 1px dashed var(--warning); background: rgba(217, 119, 6, 0.05); display: flex; flex-direction: row; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-radius: 12px; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-size: 1.5rem;">🔊</span>
        <div>
            <h4 style="margin: 0; color: var(--text-primary); font-size: 0.95rem;">Enable Audio Alerts</h4>
            <p style="margin: 2px 0 0; color: var(--text-muted); font-size: 0.8rem;">To bypass browser rules, click this button to enable instant ringtone alerts when customers order.</p>
        </div>
    </div>
    <button class="btn btn-warning btn-sm" onclick="unlockAudio()" style="white-space: nowrap;">🔔 Enable Audio</button>
</div>

<!-- Stats Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon amber">🔥</div>
        <div class="stat-value"><?php echo $pendingCount; ?></div>
        <div class="stat-label">Pending Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">👨‍🍳</div>
        <div class="stat-value"><?php echo $preparingCount; ?></div>
        <div class="stat-label">Preparing Now</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-value"><?php echo $servedTodayCount; ?></div>
        <div class="stat-label">Served Today</div>
    </div>
</div>

<!-- Main Content: Two Columns -->
<div class="two-columns">

    <!-- Left Column: Active Orders -->
    <div>
        <div class="card">
            <div class="card-header flex-between">
                <h2 class="card-title">📋 Active Orders</h2>
                <span class="badge badge-pending"><?php echo count($activeOrders); ?> active</span>
            </div>

            <?php if (empty($activeOrders)): ?>
                <div class="text-center" style="padding: 3rem 1rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                    <p class="text-muted" style="font-size: 1.1rem;">No active orders right now!</p>
                    <p class="text-muted">All caught up. Great job, team!</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem; padding: 1rem;">
                    <?php foreach ($activeOrders as $order): ?>
                        <?php
                        $statusClass = $order['status'] === 'pending' ? 'badge-pending' : 'badge-preparing';
                        $timeFormatted = date('h:i A', strtotime($order['order_time']));
                        $timeAgo = '';
                        $diff = time() - strtotime($order['order_time']);
                        if ($diff < 60) {
                            $timeAgo = 'Just now';
                        } elseif ($diff < 3600) {
                            $timeAgo = floor($diff / 60) . ' min ago';
                        } else {
                            $timeAgo = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm ago';
                        }
                        ?>
                        <div class="card kitchen-order-card <?php echo $order['status'] === 'pending' ? 'order-pending' : 'order-preparing'; ?>" style="border-left: 4px solid <?php echo $order['status'] === 'pending' ? 'var(--warning)' : 'var(--primary)'; ?>;">
                            <!-- Order Header -->
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                    <strong style="font-size: 1.1rem;">Order #<?php echo $order['id']; ?></strong>
                                    <span class="badge badge-paid" style="background: var(--bg-secondary); color: var(--primary-light);">
                                        🪑 Table <?php echo htmlspecialchars($order['table_number'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo $order['status'] === 'pending' ? '⏳ Pending' : '🔄 Preparing'; ?>
                                    </span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem;">
                                    <span>🕐 <?php echo $timeFormatted; ?></span>
                                    <span style="opacity: 0.7;">(<?php echo $timeAgo; ?>)</span>
                                </div>
                            </div>

                            <!-- Order Items -->
                            <div style="padding: 1rem;">
                                <?php if (isset($orderItems[$order['id']])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                                        <?php foreach ($orderItems[$order['id']] as $item): ?>
                                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; background: var(--bg-secondary); border-radius: 8px;">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                                         alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                         style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover; flex-shrink: 0;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; border-radius: 6px; background: var(--bg-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.2rem;">
                                                        🍽️
                                                    </div>
                                                <?php endif; ?>
                                                <div style="flex: 1;">
                                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <span class="badge badge-success">
                                                        ×<?php echo $item['quantity']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No items found.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Order Footer: Action Buttons -->
                            <div style="padding: 0.75rem 1rem; border-top: 1px solid var(--border-glass); display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.9rem; color: var(--text-muted);">
                                    Total: <strong style="color: var(--text-primary);">₹<?php echo number_format($order['grand_total'], 2); ?></strong>
                                </span>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <input type="hidden" name="new_status" value="preparing">
                                        <button type="submit" class="btn btn-warning" style="display: flex; align-items: center; gap: 0.4rem;">
                                            👨‍🍳 Start Preparing
                                        </button>
                                    <?php elseif ($order['status'] === 'preparing'): ?>
                                        <input type="hidden" name="new_status" value="served">
                                        <button type="submit" class="btn btn-success" style="display: flex; align-items: center; gap: 0.4rem;">
                                            ✅ Mark Served
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Quick Inventory Status -->
    <div>
        <div class="card">
            <div class="card-header flex-between">
                <h2 class="card-title">📦 Inventory Status</h2>
                <a href="inventory.php" class="btn btn-primary btn-sm">View All</a>
            </div>

            <?php if (empty($inventoryItems)): ?>
                <div class="text-center" style="padding: 2rem 1rem;">
                    <p class="text-muted">No inventory items found.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem;">
                    <?php foreach ($inventoryItems as $inv):
                        // Determine stock level percentage
                        $maxRef = max($inv['min_req_qty'] * 4, 1);
                        $percent = min(($inv['available_qty'] / $maxRef) * 100, 100);
                        if ($percent > 50) {
                            $stockClass = 'stock-ok';
                            $barColor = 'var(--success)';
                            $dotClass = 'green';
                        } elseif ($percent >= 25) {
                            $stockClass = 'stock-low';
                            $barColor = 'var(--warning)';
                            $dotClass = 'amber';
                        } else {
                            $stockClass = 'stock-critical';
                            $barColor = 'var(--danger)';
                            $dotClass = 'red';
                        }
                    ?>
                        <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; transition: transform 0.15s;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="status-dot <?php echo $dotClass; ?>"></span>
                                    <span style="font-weight: 500; font-size: 0.9rem;"><?php echo htmlspecialchars($inv['ingredient_name']); ?></span>
                                </div>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">
                                    <strong><?php echo $inv['available_qty']; ?></strong> <?php echo htmlspecialchars($inv['unit']); ?>
                                </span>
                            </div>
                            <div class="stock-level" style="height: 6px; background: rgba(255, 255, 255, 0.05); border-radius: 3px; overflow: hidden;">
                                <div class="stock-level-fill <?php echo $stockClass; ?>" style="height: 100%; width: <?php echo $percent; ?>%; background: <?php echo $barColor; ?>; border-radius: 3px; transition: width 0.5s ease;"></div>
                            </div>
                            <?php if ($percent < 25): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.4rem;">
                                    <span style="font-size: 0.75rem; color: var(--danger); font-weight: 500;">⚠️ Critical — Min: <?php echo $inv['min_req_qty']; ?> <?php echo htmlspecialchars($inv['unit']); ?></span>
                                    <a href="inventory.php" class="btn btn-danger btn-sm" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">Update Stock</a>
                                </div>
                            <?php elseif ($percent < 50): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.4rem;">
                                    <span style="font-size: 0.75rem; color: var(--warning); font-weight: 500;">⚡ Low Stock — Min: <?php echo $inv['min_req_qty']; ?> <?php echo htmlspecialchars($inv['unit']); ?></span>
                                    <a href="inventory.php" class="btn btn-warning btn-sm" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">Update Stock</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-top: 1rem;">
            <div class="card-header">
                <h2 class="card-title">⚡ Quick Actions</h2>
            </div>
            <div class="quick-actions" style="padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="orders.php" class="btn btn-primary btn-block">📑 View All Orders</a>
                <a href="inventory.php" class="btn btn-secondary btn-block">📦 Manage Inventory</a>
                <a href="menu.php" class="btn btn-secondary btn-block">🍽️ View Menu</a>
                <button class="btn btn-warning btn-block" onclick="location.reload();">🔄 Refresh Dashboard</button>
            </div>
        </div>
    </div>

</div>

<style>
    /* Kitchen-specific styles */
    .kitchen-order-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kitchen-order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }
    .order-pending {
        animation: pulse-border 2s ease-in-out infinite;
    }
    @keyframes pulse-border {
        0%, 100% { box-shadow: 0 2px 8px rgba(217, 119, 6, 0.1); }
        50% { box-shadow: 0 2px 16px rgba(217, 119, 6, 0.25); }
    }
    .stock-level {
        position: relative;
    }
    .stock-level-fill {
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .status-summary {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .status-summary-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.85rem;
    }
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .status-dot.green { background: var(--success); }
    .status-dot.amber { background: var(--warning); }
    .status-dot.red { background: var(--danger); }
</style>

<script>
    let maxPendingId = <?php echo (int)$maxPendingId; ?>;
    const alertAudio = new Audio('/assets/audio/alert.wav');
    
    // Check if audio has already been enabled by user previously
    if (localStorage.getItem('kitchenAudioUnlocked') === 'true') {
        const banner = document.getElementById('audioUnlockBanner');
        if (banner) banner.style.display = 'none';
    }

    function unlockAudio() {
        alertAudio.play().then(() => {
            alertAudio.pause();
            alertAudio.currentTime = 0;
            localStorage.setItem('kitchenAudioUnlocked', 'true');
            const banner = document.getElementById('audioUnlockBanner');
            if (banner) {
                banner.style.transition = 'opacity 0.3s ease';
                banner.style.opacity = '0';
                setTimeout(() => { banner.style.display = 'none'; }, 300);
            }
            showToast('🔊 Audio alerts enabled successfully!', 'success');
        }).catch(err => {
            console.error('Audio unlock failed:', err);
            showToast('❌ Browser blocked audio. Please try clicking again.', 'error');
        });
    }

    function checkNewOrders() {
        fetch('/api/check_new_orders.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.max_order_id > maxPendingId) {
                    maxPendingId = data.max_order_id;
                    
                    // Try to play alert chime
                    alertAudio.play().catch(e => {
                        console.log('Audio playback blocked:', e);
                        const banner = document.getElementById('audioUnlockBanner');
                        if (banner) {
                            banner.style.display = 'flex';
                            banner.style.opacity = '1';
                        }
                    });
                    
                    showToast('🔔 New order received in kitchen!', 'info');
                    
                    // Reload after a short delay so the user hears sound and receives visual cue
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            })
            .catch(err => console.error('Error polling orders:', err));
    }

    // Poll database for new orders every 5 seconds
    setInterval(checkNewOrders, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>
