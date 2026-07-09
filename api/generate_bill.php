<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }

    // Fetch order with table info
    $stmtOrder = $pdo->prepare("SELECT o.*, rt.table_number FROM orders o JOIN restaurant_tables rt ON o.table_id = rt.id WHERE o.id = ?");
    $stmtOrder->execute([$order_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Fetch order items
    $stmtItems = $pdo->prepare("SELECT od.id, od.order_id, od.menu_item_id, od.quantity, od.price, mi.item_name, mi.category FROM order_details od JOIN menu_items mi ON od.menu_item_id = mi.id WHERE od.order_id = ?");
    $stmtItems->execute([$order_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Calculate item totals
    $itemsWithTotals = [];
    foreach ($items as $item) {
        $item['total'] = round((float)$item['price'] * (int)$item['quantity'], 2);
        $itemsWithTotals[] = $item;
    }

    // Build bill response
    $bill = [
        'success' => true,
        'bill' => [
            'restaurant' => [
                'name' => 'SmartRestro Fine Dining',
                'address' => '123 Gourmet Street, Food District',
                'phone' => '+91 98765 43210'
            ],
            'order' => [
                'id' => (int)$order['id'],
                'table_number' => $order['table_number'],
                'status' => $order['status'],
                'order_time' => $order['order_time'],
                'formatted_time' => date('d M Y, h:i A', strtotime($order['order_time']))
            ],
            'items' => $itemsWithTotals,
            'totals' => [
                'subtotal' => (float)$order['subtotal'],
                'gst_rate' => 18,
                'gst_amount' => (float)$order['gst_amount'],
                'grand_total' => (float)$order['grand_total']
            ],
            'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode(json_encode([
                'bill_no' => $order['id'],
                'table' => $order['table_number'],
                'total' => $order['grand_total'],
                'date' => $order['order_time']
            ]))
        ]
    ];

    echo json_encode(array_merge($bill, ['items' => $itemsWithTotals]));

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
