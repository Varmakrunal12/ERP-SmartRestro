<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

// Verify login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

    if (!$table_id) {
        echo json_encode(['success' => false, 'message' => 'Table ID is required']);
        exit;
    }

    $allowed_statuses = ['available', 'reserved', 'occupied'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status. Allowed: available, reserved, occupied']);
        exit;
    }

    // Verify table exists
    $stmtCheck = $pdo->prepare("SELECT id, table_number FROM restaurant_tables WHERE id = ?");
    $stmtCheck->execute([$table_id]);
    $table = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if (!$table) {
        echo json_encode(['success' => false, 'message' => 'Table not found']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE restaurant_tables SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $table_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Table status updated to ' . $new_status,
        'table_number' => $table['table_number']
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
