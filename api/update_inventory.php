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

    // Read JSON input or Form POST input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }

    $inventory_id = isset($input['ingredient_id']) ? (int)$input['ingredient_id'] : (isset($input['inventory_id']) ? (int)$input['inventory_id'] : 0);

    if (!$inventory_id) {
        echo json_encode(['success' => false, 'message' => 'Inventory/Ingredient ID is required']);
        exit;
    }

    // Verify inventory item exists
    $stmtCheck = $pdo->prepare("SELECT id, ingredient_name, available_qty FROM inventory WHERE id = ?");
    $stmtCheck->execute([$inventory_id]);
    $item = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Inventory item not found']);
        exit;
    }

    if (isset($input['quantity'])) {
        $new_qty = (float)$input['quantity'];

        if ($new_qty < 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE inventory SET available_qty = ? WHERE id = ?");
        $stmt->execute([$new_qty, $inventory_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Inventory updated for ' . $item['ingredient_name'],
            'new_qty' => $new_qty
        ]);

    } elseif (isset($input['new_quantity'])) {
        // Set absolute quantity
        $new_qty = (float)$input['new_quantity'];

        if ($new_qty < 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE inventory SET available_qty = ? WHERE id = ?");
        $stmt->execute([$new_qty, $inventory_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Inventory updated for ' . $item['ingredient_name'],
            'new_qty' => $new_qty
        ]);

    } elseif (isset($input['quantity_change'])) {
        // Relative quantity change
        $change = (float)$input['quantity_change'];
        $projected_qty = (float)$item['available_qty'] + $change;

        if ($projected_qty < 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient stock. Available: ' . $item['available_qty'] . ', Change: ' . $change
            ]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE inventory SET available_qty = available_qty + ? WHERE id = ? AND available_qty >= ?");
        $stmt->execute([$change, $inventory_id, abs($change)]);

        echo json_encode([
            'success' => true,
            'message' => 'Inventory updated for ' . $item['ingredient_name'],
            'new_qty' => $projected_qty
        ]);

    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Quantity or quantity change is required'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
