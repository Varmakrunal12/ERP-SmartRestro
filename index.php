<?php
/**
 * SmartRestro ERP - Entry Point
 * Redirects based on role to correct dashboard
 */
session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'user';
    switch ($role) {
        case 'admin':
            header('Location: /pages/dashboard.php');
            break;
        case 'kitchen':
            header('Location: /pages/kitchen.php');
            break;
        case 'user':
            header('Location: /pages/customer_menu.php');
            break;
        default:
            header('Location: /pages/dashboard.php');
    }
} else {
    header('Location: /pages/login.php');
}
exit;
