<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to submit feedback.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Get POST data
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

// Validate inputs
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please select a rating between 1 and 5 stars.']);
    exit;
}

try {
    // 1. Verify that the order exists and is paid/completed
    $stmtOrder = $pdo->prepare("SELECT id, status FROM orders WHERE id = ?");
    $stmtOrder->execute([$order_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    if ($order['status'] !== 'paid') {
        echo json_encode(['success' => false, 'message' => 'Feedback can only be submitted for completed and paid orders.']);
        exit;
    }

    // 2. Check if feedback has already been submitted for this order
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE order_id = ?");
    $stmtCheck->execute([$order_id]);
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Feedback has already been submitted for this order.']);
        exit;
    }

    // 3. Insert feedback safely
    $stmtInsert = $pdo->prepare("INSERT INTO feedback (order_id, rating, comments, created_at) VALUES (?, ?, ?, NOW())");
    $stmtInsert->execute([$order_id, $rating, $comments ?: null]);

    echo json_encode(['success' => true, 'message' => 'Thank you! Your feedback has been submitted successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
