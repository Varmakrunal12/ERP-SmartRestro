<?php
$role = $_SESSION['role'] ?? 'admin';
$roleIcons = ['admin' => '👨💼', 'kitchen' => '👨🍳', 'user' => '👤'];
$roleLabels = ['admin' => 'Admin', 'kitchen' => 'Kitchen', 'user' => 'Customer'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'SmartRestro') ?> - SmartRestro ERP</title>
    <meta name="description" content="SmartRestro ERP - Premium Restaurant Management System">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="logo">🍽️</span>
            <h1 class="logo-text">SmartRestro</h1>
        </div>

        <ul class="nav-links">
            <?php if ($role === 'admin'): ?>
            <!-- Admin Navigation -->
            <li>
                <a href="/pages/dashboard.php" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="/pages/tables.php" class="<?= ($currentPage ?? '') === 'tables' ? 'active' : '' ?>">
                    <span class="nav-icon">🪑</span>
                    <span>Tables</span>
                </a>
            </li>
            <li>
                <a href="/pages/menu.php" class="<?= ($currentPage ?? '') === 'menu' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>
                    <span>Menu</span>
                </a>
            </li>
            <li>
                <a href="/pages/orders.php" class="<?= ($currentPage ?? '') === 'orders' ? 'active' : '' ?>">
                    <span class="nav-icon">🛒</span>
                    <span>Orders</span>
                </a>
            </li>
            <li>
                <a href="/pages/inventory.php" class="<?= ($currentPage ?? '') === 'inventory' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Inventory</span>
                </a>
            </li>
            <li>
                <a href="/pages/settings.php" class="<?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </li>

            <?php elseif ($role === 'kitchen'): ?>
            <!-- Kitchen Navigation -->
            <li>
                <a href="/pages/kitchen.php" class="<?= ($currentPage ?? '') === 'kitchen' ? 'active' : '' ?>">
                    <span class="nav-icon">👨🍳</span>
                    <span>Kitchen Dashboard</span>
                </a>
            </li>
            <li>
                <a href="/pages/orders.php" class="<?= ($currentPage ?? '') === 'orders' ? 'active' : '' ?>">
                    <span class="nav-icon">🛒</span>
                    <span>Orders</span>
                </a>
            </li>
            <li>
                <a href="/pages/inventory.php" class="<?= ($currentPage ?? '') === 'inventory' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Inventory</span>
                </a>
            </li>
            <li>
                <a href="/pages/menu.php" class="<?= ($currentPage ?? '') === 'menu' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>
                    <span>Menu</span>
                </a>
            </li>

            <?php elseif ($role === 'user'): ?>
            <!-- Customer Navigation -->
            <li>
                <a href="/pages/customer_menu.php" class="<?= ($currentPage ?? '') === 'customer_menu' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>
                    <span>Menu</span>
                </a>
            </li>
            <li>
                <a href="/pages/book_table.php" class="<?= ($currentPage ?? '') === 'book_table' ? 'active' : '' ?>">
                    <span class="nav-icon">🪑</span>
                    <span>Book Table</span>
                </a>
            </li>
            <li>
                <a href="/pages/orders.php" class="<?= ($currentPage ?? '') === 'orders' ? 'active' : '' ?>">
                    <span class="nav-icon">🛒</span>
                    <span>My Orders</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-footer">
            <div class="user-info">
                <span class="user-avatar"><?= $roleIcons[$role] ?? '👤' ?></span>
                <div>
                    <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></span>
                    <span class="role-badge role-<?= $role ?>"><?= $roleLabels[$role] ?? 'User' ?></span>
                </div>
            </div>
            <a href="/pages/login.php?logout=1" class="logout-btn">🚪 Logout</a>
        </div>
    </nav>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <span class="mobile-logo">🍽️ SmartRestro</span>
        <span class="role-badge role-<?= $role ?>"><?= $roleLabels[$role] ?? '' ?></span>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Main Content -->
    <main class="main-content">
