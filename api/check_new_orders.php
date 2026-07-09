<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

// Allow kitchen staff and admin to check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['kitchen', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Return the max ID of pending orders
    $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM orders WHERE status = 'pending'");
    $max_id = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'max_order_id' => $max_id]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
