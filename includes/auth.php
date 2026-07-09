<?php
/**
 * Authentication Helper - SmartRestro ERP
 * Role-based access control for 3 departments
 */

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /pages/login.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has one of the allowed roles.
 * Redirects to their correct dashboard if unauthorized.
 * @param array $allowedRoles e.g. ['admin'], ['admin','kitchen'], ['user']
 */
function checkRole($allowedRoles) {
    checkAuth();
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowedRoles)) {
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
                header('Location: /pages/login.php');
        }
        exit;
    }
}

/**
 * Get the current user's role
 */
function getUserRole() {
    return $_SESSION['role'] ?? 'user';
}
