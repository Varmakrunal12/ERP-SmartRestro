<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Read JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : '';

    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }

    $allowed_methods = ['cash', 'card', 'upi'];
    if (!in_array($payment_method, $allowed_methods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method. Allowed: cash, card, upi']);
        exit;
    }

    // Verify order exists and is not already paid
    $stmtOrder = $pdo->prepare("SELECT id, table_id, status FROM orders WHERE id = ?");
    $stmtOrder->execute([$order_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    if ($order['status'] === 'paid') {
        echo json_encode(['success' => false, 'message' => 'Order is already paid']);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update order status to paid
    $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
    $stmtUpdate->execute([$order_id]);

    // Free up the table - set status back to available
    $stmtTable = $pdo->prepare("UPDATE restaurant_tables SET status = 'available' WHERE id = ?");
    $stmtTable->execute([$order['table_id']]);

    // Generate transaction ID
    $transaction_id = 'TXN' . time() . rand(1000, 9999);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'transaction_id' => $transaction_id,
        'message' => 'Payment processed successfully via ' . $payment_method
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
