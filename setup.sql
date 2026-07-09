-- =====================================================
-- RestroERP - Restaurant Management System
-- Database Setup Script (v2.0 - Multi-Department)
-- =====================================================

CREATE DATABASE IF NOT EXISTS restaurant_erp;
USE restaurant_erp;

-- Drop existing tables (in reverse dependency order)
DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS menu_item_ingredients;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS restaurant_tables;
DROP TABLE IF EXISTS users;

-- =====================================================
-- USERS TABLE (3 Department Roles)
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users will be auto-created by database.php on first page load with proper hashed passwords:
-- admin/admin123, kitchen/kitchen123, user/user123

-- =====================================================
-- RESTAURANT TABLES
-- =====================================================
CREATE TABLE restaurant_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number INT NOT NULL UNIQUE,
    status ENUM('available','reserved','occupied') DEFAULT 'available',
    qr_code_path VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MENU ITEMS (with image_url)
-- =====================================================
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    is_available TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INVENTORY
-- =====================================================
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_name VARCHAR(100) NOT NULL,
    available_qty DECIMAL(10,2) DEFAULT 0,
    min_req_qty DECIMAL(10,2) DEFAULT 0,
    unit VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MENU ITEM INGREDIENTS (Junction Table)
-- =====================================================
CREATE TABLE menu_item_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    inventory_id INT NOT NULL,
    qty_required DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ORDERS
-- =====================================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    status ENUM('pending','preparing','served','paid') DEFAULT 'pending',
    subtotal DECIMAL(10,2) DEFAULT 0,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    grand_total DECIMAL(10,2) DEFAULT 0,
    order_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES restaurant_tables(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ORDER DETAILS
-- =====================================================
CREATE TABLE order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Restaurant Tables (1-10)
INSERT INTO restaurant_tables (table_number, status) VALUES
(1, 'available'), (2, 'available'), (3, 'available'),
(4, 'available'), (5, 'available'), (6, 'available'),
(7, 'available'), (8, 'available'), (9, 'available'),
(10, 'available');

-- Menu Items (with image URLs)
-- Starters
INSERT INTO menu_items (item_name, category, price, image_url) VALUES
('Paneer Tikka', 'Starters', 249.00, 'https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=400&h=300&fit=crop'),
('Chicken Wings', 'Starters', 299.00, 'https://images.unsplash.com/photo-1608039829572-9b0ba489c297?w=400&h=300&fit=crop'),
('Veg Spring Rolls', 'Starters', 199.00, 'https://images.unsplash.com/photo-1697207983757-e3e12a425812?w=400&h=300&fit=crop'),
('Tomato Soup', 'Starters', 149.00, 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=400&h=300&fit=crop'),
('Mushroom Galette', 'Starters', 229.00, 'https://images.unsplash.com/photo-1504544750208-dc0358e63f7f?w=400&h=300&fit=crop');

-- Main Course
INSERT INTO menu_items (item_name, category, price, image_url) VALUES
('Butter Chicken', 'Main Course', 349.00, 'https://images.unsplash.com/photo-1603894584373-5ac82b2ae398?w=400&h=300&fit=crop'),
('Dal Makhani', 'Main Course', 249.00, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=400&h=300&fit=crop'),
('Chicken Biryani', 'Main Course', 299.00, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=400&h=300&fit=crop'),
('Pasta Alfredo', 'Main Course', 279.00, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=400&h=300&fit=crop'),
('Veg Thali', 'Main Course', 229.00, 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&h=300&fit=crop');

-- Beverages
INSERT INTO menu_items (item_name, category, price, image_url) VALUES
('Mango Lassi', 'Beverages', 99.00, 'https://images.unsplash.com/photo-1626200419199-391ae4be7a41?w=400&h=300&fit=crop'),
('Fresh Lime Soda', 'Beverages', 79.00, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed514?w=400&h=300&fit=crop'),
('Cold Coffee', 'Beverages', 129.00, 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=400&h=300&fit=crop'),
('Masala Chai', 'Beverages', 49.00, 'https://images.unsplash.com/photo-1571934811356-5cc061b6821f?w=400&h=300&fit=crop'),
('Mojito', 'Beverages', 149.00, 'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=400&h=300&fit=crop');

-- Desserts
INSERT INTO menu_items (item_name, category, price, image_url) VALUES
('Gulab Jamun', 'Desserts', 129.00, 'https://images.unsplash.com/photo-1666190077619-601a65aad266?w=400&h=300&fit=crop'),
('Brownie with Ice Cream', 'Desserts', 199.00, 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400&h=300&fit=crop'),
('Rasmalai', 'Desserts', 149.00, 'https://images.unsplash.com/photo-1668235273115-2f3a76e368ce?w=400&h=300&fit=crop');

-- Inventory
INSERT INTO inventory (ingredient_name, available_qty, min_req_qty, unit) VALUES
('Paneer', 5.00, 1.00, 'kg'),
('Chicken', 8.00, 2.00, 'kg'),
('Rice', 10.00, 3.00, 'kg'),
('Flour', 15.00, 5.00, 'kg'),
('Tomato', 5.00, 1.00, 'kg'),
('Onion', 8.00, 2.00, 'kg'),
('Cream', 3.00, 1.00, 'liters'),
('Butter', 2.00, 0.50, 'kg'),
('Mushroom', 2.00, 0.50, 'kg'),
('Mango Pulp', 5.00, 1.00, 'liters'),
('Coffee Beans', 2.00, 0.50, 'kg'),
('Sugar', 10.00, 2.00, 'kg'),
('Milk', 10.00, 3.00, 'liters'),
('Cooking Oil', 5.00, 2.00, 'liters'),
('Spices Mix', 3.00, 1.00, 'kg'),
('Lemon', 3.00, 0.50, 'kg'),
('Pasta', 4.00, 1.00, 'kg'),
('Chocolate', 2.00, 0.50, 'kg');

-- Menu Item Ingredients Mapping
INSERT INTO menu_item_ingredients (menu_item_id, inventory_id, qty_required) VALUES
(1, 1, 0.20), (1, 15, 0.05), (1, 7, 0.05),
(2, 2, 0.30), (2, 15, 0.05), (2, 14, 0.10),
(3, 4, 0.15), (3, 14, 0.10), (3, 6, 0.10),
(4, 5, 0.30), (4, 7, 0.05), (4, 8, 0.05),
(5, 9, 0.25), (5, 4, 0.15), (5, 8, 0.10),
(6, 2, 0.30), (6, 8, 0.10), (6, 7, 0.10), (6, 5, 0.20), (6, 15, 0.10),
(7, 7, 0.10), (7, 8, 0.10), (7, 15, 0.05),
(8, 3, 0.30), (8, 2, 0.30), (8, 6, 0.20), (8, 15, 0.10), (8, 14, 0.10),
(9, 17, 0.25), (9, 7, 0.15), (9, 8, 0.10), (9, 9, 0.10),
(10, 3, 0.20), (10, 4, 0.15), (10, 1, 0.10), (10, 5, 0.10), (10, 15, 0.10),
(11, 10, 0.20), (11, 13, 0.15), (11, 12, 0.05),
(12, 16, 0.10), (12, 12, 0.05),
(13, 11, 0.10), (13, 13, 0.20), (13, 12, 0.05), (13, 7, 0.05),
(14, 13, 0.15), (14, 12, 0.03), (14, 15, 0.02),
(15, 16, 0.10), (15, 12, 0.05),
(16, 13, 0.15), (16, 12, 0.10), (16, 4, 0.10),
(17, 18, 0.15), (17, 4, 0.10), (17, 12, 0.10), (17, 7, 0.10), (17, 8, 0.05),
(18, 13, 0.20), (18, 12, 0.10), (18, 7, 0.10);
