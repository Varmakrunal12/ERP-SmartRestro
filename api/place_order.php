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

    $table_id = isset($input['table_id']) ? (int)$input['table_id'] : 0;
    $items = isset($input['items']) ? $input['items'] : [];

    if (!$table_id) {
        echo json_encode(['success' => false, 'message' => 'Table ID is required']);
        exit;
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items in the order']);
        exit;
    }

    // Verify table exists
    $stmtTable = $pdo->prepare("SELECT id FROM restaurant_tables WHERE id = ?");
    $stmtTable->execute([$table_id]);
    if (!$stmtTable->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid table']);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (float)$item['price'] * (int)$item['quantity'];
    }
    $gst_amount = round($subtotal * 0.18, 2);
    $grand_total = round($subtotal + $gst_amount, 2);

    // Insert order
    $stmtOrder = $pdo->prepare("INSERT INTO orders (table_id, status, subtotal, gst_amount, grand_total, order_time) VALUES (?, 'pending', ?, ?, ?, NOW())");
    $stmtOrder->execute([$table_id, $subtotal, $gst_amount, $grand_total]);
    $order_id = $pdo->lastInsertId();

    // Insert order details
    $stmtDetail = $pdo->prepare("INSERT INTO order_details (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $menu_item_id = isset($item['menu_item_id']) ? (int)$item['menu_item_id'] : (isset($item['id']) ? (int)$item['id'] : 0);
        
        $stmtDetail->execute([
            $order_id,
            $menu_item_id,
            (int)$item['quantity'],
            (float)$item['price']
        ]);
    }

    // Decrement inventory for each item
    $stmtIngredients = $pdo->prepare("SELECT inventory_id, qty_required FROM menu_item_ingredients WHERE menu_item_id = ?");
    $stmtUpdateInv = $pdo->prepare("UPDATE inventory SET available_qty = available_qty - ? WHERE id = ? AND available_qty >= ?");

    foreach ($items as $item) {
        $menu_item_id = isset($item['menu_item_id']) ? (int)$item['menu_item_id'] : (isset($item['id']) ? (int)$item['id'] : 0);

        $stmtIngredients->execute([$menu_item_id]);
        $ingredients = $stmtIngredients->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ingredients as $ingredient) {
            $deductQty = (float)$ingredient['qty_required'] * (int)$item['quantity'];
            $stmtUpdateInv->execute([$deductQty, $ingredient['inventory_id'], $deductQty]);

            if ($stmtUpdateInv->rowCount() === 0) {
                // Check if it's because of insufficient stock
                $stmtCheckStock = $pdo->prepare("SELECT ingredient_name, available_qty FROM inventory WHERE id = ?");
                $stmtCheckStock->execute([$ingredient['inventory_id']]);
                $inv = $stmtCheckStock->fetch(PDO::FETCH_ASSOC);
                if ($inv && $inv['available_qty'] < $deductQty) {
                    $pdo->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Insufficient stock for ingredient: ' . $inv['ingredient_name'] . ' (Available: ' . $inv['available_qty'] . ', Required: ' . $deductQty . ')'
                    ]);
                    exit;
                }
            }
        }
    }

    // Update table status to occupied
    $stmtTableUpdate = $pdo->prepare("UPDATE restaurant_tables SET status = 'occupied' WHERE id = ?");
    $stmtTableUpdate->execute([$table_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => (int)$order_id,
        'message' => 'Order placed successfully!'
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
