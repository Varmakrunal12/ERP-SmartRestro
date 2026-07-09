<?php
session_start();
require_once '../config/database.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Redirect if already logged in (role-based)
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'user';
    switch ($role) {
        case 'admin': header('Location: dashboard.php'); break;
        case 'kitchen': header('Location: kitchen.php'); break;
        case 'user': header('Location: customer_menu.php'); break;
        default: header('Location: dashboard.php');
    }
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $loginError = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            switch ($user['role']) {
                case 'admin': header('Location: dashboard.php'); break;
                case 'kitchen': header('Location: kitchen.php'); break;
                case 'user': header('Location: customer_menu.php'); break;
                default: header('Location: dashboard.php');
            }
            exit;
        } else {
            $loginError = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmartRestro ERP</title>
    <meta name="description" content="Login to SmartRestro ERP - Restaurant Management System">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-page {
            position: relative;
            overflow: hidden;
        }

        .login-page::before {
            content: '🍕';
            position: absolute;
            top: 10%;
            left: 8%;
            font-size: 4rem;
            opacity: 0.15;
            animation: floatDecor 6s ease-in-out infinite;
        }

        .login-page::after {
            content: '🍷';
            position: absolute;
            bottom: 12%;
            right: 10%;
            font-size: 3.5rem;
            opacity: 0.15;
            animation: floatDecor 8s ease-in-out infinite reverse;
        }

        @keyframes floatDecor {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-15px) rotate(5deg); }
            50% { transform: translateY(-8px) rotate(-3deg); }
            75% { transform: translateY(-20px) rotate(8deg); }
        }

        .login-divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--text-muted, #8e7d69);
            font-size: 0.85rem;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(205, 189, 166, 0.3), transparent);
        }

        .login-divider span {
            padding: 0 1rem;
        }

        .dept-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 1.2rem;
        }

        .dept-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 14px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dept-card:hover {
            border-color: var(--primary);
            background: rgba(217, 119, 6, 0.15);
            transform: translateY(-2px);
        }

        .dept-card .dept-icon {
            font-size: 28px;
            display: block;
            margin-bottom: 6px;
        }

        .dept-card .dept-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            display: block;
        }

        .dept-card .dept-cred {
            font-size: 10px;
            color: var(--text-muted);
            display: block;
            margin-top: 3px;
        }

        .dept-card.admin-card:hover { border-color: var(--primary); box-shadow: 0 0 15px rgba(217,119,6,0.2); }
        .dept-card.kitchen-card:hover { border-color: var(--secondary); box-shadow: 0 0 15px rgba(234,88,12,0.2); }
        .dept-card.user-card:hover { border-color: var(--success); box-shadow: 0 0 15px rgba(22,163,74,0.2); }
    </style>
</head>
<body class="login-page">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">🍽️</div>
            <h1>SmartRestro</h1>
            <p class="text-muted">Restaurant Management System</p>
        </div>

        <?php if ($loginError): ?>
            <div class="login-error">
                <?php echo htmlspecialchars($loginError); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="on" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input"
                       placeholder="Enter your username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="Enter your password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">
                Sign In
            </button>
        </form>

        <div class="login-divider">
            <span>Quick Login</span>
        </div>

        <div class="dept-cards">
            <div class="dept-card admin-card" onclick="quickLogin('admin','admin123')">
                <span class="dept-icon">👨‍💼</span>
                <span class="dept-name">Admin</span>
                <span class="dept-cred">admin / admin123</span>
            </div>
            <div class="dept-card kitchen-card" onclick="quickLogin('kitchen','kitchen123')">
                <span class="dept-icon">👨‍🍳</span>
                <span class="dept-name">Kitchen</span>
                <span class="dept-cred">kitchen / kitchen123</span>
            </div>
            <div class="dept-card user-card" onclick="quickLogin('user','user123')">
                <span class="dept-icon">👤</span>
                <span class="dept-name">Customer</span>
                <span class="dept-cred">user / user123</span>
            </div>
        </div>
    </div>

    <script>
    function quickLogin(username, password) {
        document.getElementById('username').value = username;
        document.getElementById('password').value = password;
        document.getElementById('loginForm').submit();
    }
    </script>

</body>
</html>
