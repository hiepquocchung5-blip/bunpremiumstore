-- Create Database
CREATE DATABASE IF NOT EXISTS scottsub_db;
USE scottsub_db;

-- 1. Admin Users (Security)
CREATE TABLE adm_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'support') DEFAULT 'super_admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Users (Customers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100),
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Regions (For Filtering)
CREATE TABLE regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL, 
    code VARCHAR(5) NOT NULL
);

-- 4. Agent Passes (Discounts)
CREATE TABLE passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    discount_percent INT NOT NULL,
    duration_days INT NOT NULL,
    description TEXT,
    is_active TINYINT DEFAULT 1
);

-- 5. User Active Passes
CREATE TABLE user_passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pass_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('active', 'expired') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (pass_id) REFERENCES passes(id)
);

-- 6. Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    type ENUM('subscription', 'game', 'gift_card') NOT NULL,
    icon_class VARCHAR(50) DEFAULT 'fa-cube',
    description TEXT
);

-- 7. Products (Updated with Delivery Types)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    region_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    
    -- Delivery Logic
    delivery_type ENUM('unique', 'universal', 'form') NOT NULL DEFAULT 'universal',
    universal_content TEXT DEFAULT NULL, 
    form_fields JSON DEFAULT NULL,       
    
    duration_days INT DEFAULT NULL,
    user_instruction TEXT,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (region_id) REFERENCES regions(id)
);

-- 8. Product Keys (For 'Unique' Type)
CREATE TABLE product_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    key_content TEXT NOT NULL,
    is_sold TINYINT DEFAULT 0,
    order_id INT DEFAULT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 9. Product Checkbox Instructions
CREATE TABLE product_instructions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    instruction_text VARCHAR(255) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 10. Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    email_delivery_type ENUM('own', 'admin_provided') NOT NULL,
    delivery_email VARCHAR(100),
    form_data JSON DEFAULT NULL, 
    transaction_last_6 VARCHAR(6) NOT NULL,
    proof_image_path VARCHAR(255) NOT NULL,
    total_price_paid DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'active', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 11. Chat
CREATE TABLE order_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    sender_type ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    is_credential TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- 12. Banners (NEW: For Home Page Swipe)
CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    image_path VARCHAR(255) NOT NULL,
    target_url VARCHAR(255), -- Link to product or social media
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 13. Expenses (NEW: For Tracking Costs)
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL, -- Expense cost
    category VARCHAR(50) DEFAULT 'General', -- e.g., 'Server', 'Marketing', 'Stock'
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);