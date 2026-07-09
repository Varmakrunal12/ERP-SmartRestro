<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin', 'kitchen', 'user']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['new_status'];
        $allowed = ['pending', 'preparing', 'served', 'paid'];
        if (in_array($new_status, $allowed)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
        }
        header("Location: orders.php" . (isset($_GET['status']) ? "?status=" . urlencode($_GET['status']) : ''));
        exit;
    } catch (PDOException $e) {
        $error = "Failed to update order status.";
    }
}

// Handle edit order (update item quantities)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_order'])) {
    try {
        $order_id = (int)$_POST['order_id'];
        $quantities = $_POST['quantities'] ?? [];
        $prices = $_POST['prices'] ?? [];
        $detail_ids = $_POST['detail_ids'] ?? [];
        
        $pdo->beginTransaction();
        
        $subtotal = 0;
        for ($i = 0; $i < count($detail_ids); $i++) {
            $qty = max(0, (int)$quantities[$i]);
            $price = (float)$prices[$i];
            $detailId = (int)$detail_ids[$i];
            
            if ($qty === 0) {
                // Remove item
                $pdo->prepare("DELETE FROM order_details WHERE id = ?")->execute([$detailId]);
            } else {
                // Update quantity
                $pdo->prepare("UPDATE order_details SET quantity = ? WHERE id = ?")->execute([$qty, $detailId]);
                $subtotal += $price * $qty;
            }
        }
        
        // Recalculate totals
        $gst = $subtotal * 0.18;
        $grandTotal = $subtotal + $gst;
        $pdo->prepare("UPDATE orders SET subtotal = ?, gst_amount = ?, grand_total = ? WHERE id = ?")->execute([$subtotal, $gst, $grandTotal, $order_id]);
        
        $pdo->commit();
        $_SESSION['toast'] = ['msg' => 'Order updated successfully!', 'type' => 'success'];
        header("Location: orders.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to update order.";
    }
}

$pageTitle = 'Orders';
$currentPage = 'orders';
require_once '../includes/header.php';

$role = $_SESSION['role'] ?? 'user';

// Filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all', 'pending', 'preparing', 'served', 'paid'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// Fetch orders
$sql = "SELECT o.*, rt.table_number,
        (SELECT COUNT(*) FROM order_details od WHERE od.order_id = o.id) AS item_count
        FROM orders o
        JOIN restaurant_tables rt ON o.table_id = rt.id";
if ($statusFilter !== 'all') {
    $sql .= " WHERE o.status = :status";
}
$sql .= " ORDER BY o.order_time DESC";

$stmt = $pdo->prepare($sql);
if ($statusFilter !== 'all') {
    $stmt->bindParam(':status', $statusFilter);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatCurrencyPHP($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// Calculate dynamic ETA variables
$activePendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$etaMinutes = ($activePendingCount > 5) ? 25 : 15;
?>

<div class="page-header flex-between">
    <h1 class="page-title"><?= $role === 'user' ? 'My Orders' : 'Order Management' ?></h1>
    <?php if ($role !== 'user'): ?>
        <a href="customer_menu.php" class="btn btn-primary">+ New Order</a>
    <?php endif; ?>
</div>

<div class="category-filter">
    <a href="?status=all" class="filter-pill <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
    <a href="?status=pending" class="filter-pill <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
    <a href="?status=preparing" class="filter-pill <?php echo $statusFilter === 'preparing' ? 'active' : ''; ?>">Preparing</a>
    <a href="?status=served" class="filter-pill <?php echo $statusFilter === 'served' ? 'active' : ''; ?>">Served</a>
    <a href="?status=paid" class="filter-pill <?php echo $statusFilter === 'paid' ? 'active' : ''; ?>">Paid</a>
</div>

<?php if (isset($error)): ?>
    <div class="login-error" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <?php if (empty($orders)): ?>
        <div class="text-center text-muted" style="padding: 3rem;">
            <div style="font-size: 3rem;">📋</div>
            <p>No orders found.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Table #</th>
                    <th>Items</th>
                    <th>Subtotal</th>
                    <th>GST</th>
                    <th>Grand Total</th>
                    <th>Status</th>
                    <th>Time</th>
                    <th>ETA</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                        <td>Table <?php echo htmlspecialchars($order['table_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['item_count']); ?></td>
                        <td><?php echo formatCurrencyPHP($order['subtotal']); ?></td>
                        <td><?php echo formatCurrencyPHP($order['gst_amount']); ?></td>
                        <td><strong><?php echo formatCurrencyPHP($order['grand_total']); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y, h:i A', strtotime($order['order_time'])); ?></td>
                        <td>
                            <?php if (in_array($order['status'], ['pending', 'preparing'])): ?>
                                <?php 
                                    $targetTime = strtotime($order['order_time']) + ($etaMinutes * 60);
                                ?>
                                <span class="eta-countdown" data-target="<?php echo $targetTime; ?>" style="font-weight: 600; color: var(--primary);">
                                    Calculating...
                                </span>
                            <?php elseif ($order['status'] === 'served'): ?>
                                <span style="color: var(--success); font-weight: 600;">Served 🍽️</span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.9rem;">Paid ✅</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="bill.php?order_id=<?php echo $order['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                            
                            <?php if (in_array($role, ['admin', 'kitchen'])): ?>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <!-- Edit Order Button -->
                                    <button class="btn btn-warning btn-sm" onclick="openEditOrder(<?php echo $order['id']; ?>)">✏️ Edit</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="new_status" value="preparing">
                                        <input type="hidden" name="update_status" value="1">
                                        <button type="submit" class="btn btn-warning btn-sm">Start Preparing</button>
                                    </form>
                                <?php elseif ($order['status'] === 'preparing'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="new_status" value="served">
                                        <input type="hidden" name="update_status" value="1">
                                        <button type="submit" class="btn btn-success btn-sm">Mark Served</button>
                                    </form>
                                <?php elseif ($order['status'] === 'served'): ?>
                                    <a href="bill.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">Generate Bill</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Order Modal -->
<div class="modal-overlay" id="editOrderModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>✏️ Edit Order #<span id="editOrderId"></span></h3>
            <button class="modal-close" onclick="closeModal('editOrderModal')">&times;</button>
        </div>
        <form method="POST" action="orders.php">
            <input type="hidden" name="edit_order" value="1">
            <input type="hidden" name="order_id" id="editOrderIdInput">
            <div class="modal-body" id="editOrderBody">
                <p class="text-muted">Loading order details...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editOrderModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
async function openEditOrder(orderId) {
    document.getElementById('editOrderId').textContent = orderId;
    document.getElementById('editOrderIdInput').value = orderId;
    
    // Fetch order details via API
    try {
        const res = await fetch('../api/generate_bill.php?order_id=' + orderId);
        const data = await res.json();
        
        if (data.success && data.items) {
            let html = '<table class="data-table"><thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead><tbody>';
            data.items.forEach((item, idx) => {
                html += `<tr>
                    <td>${item.item_name}</td>
                    <td>₹${parseFloat(item.price).toFixed(2)}</td>
                    <td>
                        <input type="hidden" name="detail_ids[]" value="${item.id}">
                        <input type="hidden" name="prices[]" value="${item.price}">
                        <input type="number" name="quantities[]" value="${item.quantity}" min="0" max="50" class="form-input" style="width:70px;">
                    </td>
                    <td>₹${(item.price * item.quantity).toFixed(2)}</td>
                </tr>`;
            });
            html += '</tbody></table><p class="text-muted mt-2">Set quantity to 0 to remove an item.</p>';
            document.getElementById('editOrderBody').innerHTML = html;
        } else {
            document.getElementById('editOrderBody').innerHTML = '<p class="text-danger">Failed to load order details.</p>';
        }
    } catch (err) {
        document.getElementById('editOrderBody').innerHTML = '<p class="text-danger">Error loading order details.</p>';
    }
    
    openModal('editOrderModal');
}

<?php if (isset($_SESSION['toast'])): ?>
    showToast('<?php echo addslashes($_SESSION['toast']['msg']); ?>', '<?php echo $_SESSION['toast']['type']; ?>');
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
