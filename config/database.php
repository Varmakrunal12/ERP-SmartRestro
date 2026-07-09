<?php
/**
 * Database Configuration - SmartRestro ERP
 * PDO Connection with MySQL + Auto-Migration
 */

$host = 'localhost';
$dbname = 'restaurant_erp';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // ── Auto-Migration: Add image_url column if missing ──
    $cols = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'image_url'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN image_url VARCHAR(500) DEFAULT NULL AFTER price");

        // Seed default food images
        $images = [
            1  => 'https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=400&h=300&fit=crop',
            2  => 'https://images.unsplash.com/photo-1608039829572-9b0ba489c297?w=400&h=300&fit=crop',
            3  => 'https://images.unsplash.com/photo-1697207983757-e3e12a425812?w=400&h=300&fit=crop',
            4  => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=400&h=300&fit=crop',
            5  => 'https://images.unsplash.com/photo-1504544750208-dc0358e63f7f?w=400&h=300&fit=crop',
            6  => 'https://images.unsplash.com/photo-1603894584373-5ac82b2ae398?w=400&h=300&fit=crop',
            7  => 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=400&h=300&fit=crop',
            8  => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=400&h=300&fit=crop',
            9  => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=400&h=300&fit=crop',
            10 => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&h=300&fit=crop',
            11 => 'https://images.unsplash.com/photo-1626200419199-391ae4be7a41?w=400&h=300&fit=crop',
            12 => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed514?w=400&h=300&fit=crop',
            13 => 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=400&h=300&fit=crop',
            14 => 'https://images.unsplash.com/photo-1571934811356-5cc061b6821f?w=400&h=300&fit=crop',
            15 => 'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=400&h=300&fit=crop',
            16 => 'https://images.unsplash.com/photo-1666190077619-601a65aad266?w=400&h=300&fit=crop',
            17 => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400&h=300&fit=crop',
            18 => 'https://images.unsplash.com/photo-1668235273115-2f3a76e368ce?w=400&h=300&fit=crop',
        ];
        $stmt = $pdo->prepare("UPDATE menu_items SET image_url = ? WHERE id = ?");
        foreach ($images as $id => $url) {
            $stmt->execute([$url, $id]);
        }
    }

    // ── Auto-Migration: Create feedback table if missing ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
            comments TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Auto-Migration: Ensure 3 department users exist with correct passwords ──
    $roles = [
        ['admin', 'admin123', 'admin'],
        ['kitchen', 'kitchen123', 'kitchen'],
        ['user', 'user123', 'user']
    ];
    foreach ($roles as $r) {
        $check = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $check->execute([$r[0]]);
        $existing = $check->fetch();
        
        if (!$existing) {
            $hash = password_hash($r[1], PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $ins->execute([$r[0], $hash, $r[2]]);
        } elseif (!password_verify($r[1], $existing['password'])) {
            $hash = password_hash($r[1], PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hash, $existing['id']]);
        }
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
