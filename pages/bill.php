<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkRole(['admin', 'kitchen', 'user']);

$pageTitle = 'Bill';
$currentPage = 'orders';
require_once '../includes/header.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    echo '<div class="card text-center text-muted" style="padding:3rem;"><p>Invalid order ID.</p><a href="orders.php" class="btn btn-primary">Back to Orders</a></div>';
    require_once '../includes/footer.php';
    exit;
}

// Fetch order with table info
$stmtOrder = $pdo->prepare("SELECT o.*, rt.table_number FROM orders o JOIN restaurant_tables rt ON o.table_id = rt.id WHERE o.id = ?");
$stmtOrder->execute([$order_id]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="card text-center text-muted" style="padding:3rem;"><p>Order not found.</p><a href="orders.php" class="btn btn-primary">Back to Orders</a></div>';
    require_once '../includes/footer.php';
    exit;
}

// Fetch order items
$stmtItems = $pdo->prepare("SELECT od.*, mi.item_name FROM order_details od JOIN menu_items mi ON od.menu_item_id = mi.id WHERE od.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// QR code data
$qrData = json_encode([
    'bill_no' => $order['id'],
    'table' => $order['table_number'],
    'total' => $order['grand_total'],
    'date' => $order['order_time']
]);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrData);
?>

<div class="page-header flex-between">
    <h1 class="page-title">Bill #<?php echo $order_id; ?></h1>
    <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>
</div>

<div class="bill-container" id="billContent">
    <div class="bill-header">
        <div class="bill-logo">🍽️</div>
        <h2>SmartRestro Fine Dining</h2>
        <p>123 Gourmet Street, Food District</p>
        <p>Phone: +91 98765 43210</p>
    </div>

    <div class="bill-info">
        <div class="flex-between">
            <span><strong>Bill #:</strong> <?php echo $order['id']; ?></span>
            <span><strong>Table #:</strong> <?php echo htmlspecialchars($order['table_number']); ?></span>
        </div>
        <div class="flex-between">
            <span><strong>Date/Time:</strong> <?php echo date('d M Y, h:i A', strtotime($order['order_time'])); ?></span>
            <span><strong>Status:</strong> 
                <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                </span>
            </span>
        </div>
        <?php if (in_array($order['status'], ['pending', 'preparing'])): ?>
            <?php 
                $activePendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
                $etaMinutes = ($activePendingCount > 5) ? 25 : 15;
                $targetTime = strtotime($order['order_time']) + ($etaMinutes * 60);
            ?>
            <div class="flex-between" style="margin-top: 5px; border-top: 1px dashed rgba(255,255,255,0.05); padding-top: 5px;">
                <span><strong>Est. Food Ready:</strong></span>
                <span class="eta-countdown" data-target="<?php echo $targetTime; ?>" style="font-weight: 600; color: var(--primary);">
                    Calculating...
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="bill-items">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td>₹<?php echo number_format((float)$item['price'], 2); ?></td>
                        <td>₹<?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bill-totals">
        <div class="total-row">
            <span>Subtotal</span>
            <span>₹<?php echo number_format((float)$order['subtotal'], 2); ?></span>
        </div>
        <div class="total-row">
            <span>GST (18%)</span>
            <span>₹<?php echo number_format((float)$order['gst_amount'], 2); ?></span>
        </div>
        <div class="total-row grand-total">
            <span>Grand Total</span>
            <span>₹<?php echo number_format((float)$order['grand_total'], 2); ?></span>
        </div>
    </div>

    <div class="bill-qr text-center">
        <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="Bill QR Code" width="200" height="200">
        <p class="text-muted">Scan for digital receipt</p>
    </div>

    <div class="bill-footer text-center">
        <p><strong>Thank you for dining with us!</strong></p>
        <p class="text-muted">We hope to see you again soon. Have a wonderful day!</p>
    </div>
</div>

<div class="bill-actions text-center">
    <button class="btn btn-primary" onclick="printBill()">🖨️ Print Bill</button>
    <?php if ($order['status'] !== 'paid'): ?>
        <a href="payment.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success">💳 Proceed to Payment</a>
    <?php endif; ?>
    <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>
</div>

<script>
function printBill() {
    const billContent = document.getElementById('billContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Bill #<?php echo $order_id; ?></title>
            <style>
                body { font-family: 'Inter', sans-serif; padding: 20px; max-width: 400px; margin: 0 auto; background: white; color: black; }
                .bill-header { text-align: center; border-bottom: 2px dashed #ccc; padding-bottom: 15px; margin-bottom: 15px; }
                .bill-logo { font-size: 2.5rem; }
                .bill-info { margin: 15px 0; padding: 10px 0; border-bottom: 1px dashed #ccc; }
                .flex-between { display: flex; justify-content: space-between; margin-bottom: 5px; }
                .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                .data-table th, .data-table td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; }
                .data-table th { background: #f5f5f5; }
                .bill-totals { border-top: 2px solid #333; padding-top: 10px; }
                .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
                .grand-total { font-size: 1.2em; font-weight: bold; border-top: 2px solid #333; margin-top: 5px; padding-top: 10px; }
                .bill-qr { text-align: center; margin: 15px 0; }
                .bill-footer { text-align: center; margin-top: 15px; border-top: 2px dashed #ccc; padding-top: 15px; }
                .badge { padding: 2px 8px; border-radius: 4px; font-size: 0.8em; background: #eee; color: #333; }
                .text-muted { color: #666; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>${billContent}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php require_once '../includes/footer.php'; ?>
