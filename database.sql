-- KineticBorrow Database Setup
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS kineticborrow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kineticborrow;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    first_name  VARCHAR(50)  NOT NULL,
    last_name   VARCHAR(50)  NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    phone       VARCHAR(20)  NOT NULL,
    password    VARCHAR(255) NOT NULL,
    id_type     ENUM('student','senior','pwd','regular') DEFAULT 'regular',
    id_verified TINYINT(1) DEFAULT 0,
    loyalty_pts INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment table
CREATE TABLE IF NOT EXISTS equipment (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    category    VARCHAR(50)  NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    stock       INT DEFAULT 0,
    rating      DECIMAL(2,1) DEFAULT 0.0,
    review_count INT DEFAULT 0,
    icon        VARCHAR(10)  DEFAULT '🏋️',
    tag         VARCHAR(30)  DEFAULT NULL,
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rentals / Orders table
CREATE TABLE IF NOT EXISTS rentals (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_code   VARCHAR(20)  NOT NULL UNIQUE,
    user_id      INT          NOT NULL,
    equipment_id INT          NOT NULL,
    days         INT          NOT NULL DEFAULT 1,
    price_per_day DECIMAL(10,2) NOT NULL,
    discount_pct INT          DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status       ENUM('active','returned','cancelled') DEFAULT 'active',
    start_date   DATE         NOT NULL,
    end_date     DATE         NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)      REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
);

-- Sample equipment data
INSERT INTO equipment (name, category, price_per_day, stock, rating, review_count, icon, tag) VALUES
('Mountain Bike',   'Cycling',       350.00, 4,  4.8, 42, '🚵', 'Popular'),
('Badminton Set',   'Racket Sports', 120.00, 8,  4.6, 31, '🏸', 'Student Deal'),
('Surfboard',       'Water Sports',  500.00, 2,  4.9, 18, '🏄', 'Limited'),
('Boxing Gloves',   'Combat Sports', 180.00, 6,  4.5, 24, '🥊', NULL),
('Football Kit',    'Team Sports',   220.00, 5,  4.7, 36, '⚽', 'Popular'),
('Kayak + Paddle',  'Water Sports',  650.00, 3,  4.8, 14, '🛶', 'Weekend Special'),
('Tennis Racket',   'Racket Sports', 150.00, 7,  4.4, 20, '🎾', NULL),
('Volleyball Set',  'Team Sports',   200.00, 4,  4.6, 28, '🏐', 'Popular');

-- Sample user (password: password123)
INSERT INTO users (first_name, last_name, email, phone, password, id_type, id_verified, loyalty_pts) VALUES
('Harold', 'Reyes', 'harold.reyes@email.com', '09123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, 1820);
