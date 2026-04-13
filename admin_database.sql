-- ============================================================
-- KineticBorrow Admin Tables — run this in phpMyAdmin
-- (after you already ran the original database.sql)
-- ============================================================
USE kineticborrow;

-- Admin accounts
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('superadmin','admin','staff') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Blocklist
CREATE TABLE IF NOT EXISTS blocklist (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL UNIQUE,
    reason     TEXT,
    status     ENUM('flagged','suspended','unblocked') DEFAULT 'flagged',
    flagged_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Promotions / Campaigns
CREATE TABLE IF NOT EXISTS promotions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(100) NOT NULL,
    code         VARCHAR(30)  NOT NULL UNIQUE,
    type         ENUM('percentage','fixed') DEFAULT 'percentage',
    value        DECIMAL(10,2) NOT NULL,
    min_days     INT DEFAULT 1,
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    is_active    TINYINT(1) DEFAULT 1,
    usage_count  INT DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ID Verification log
CREATE TABLE IF NOT EXISTS id_verifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    id_type     ENUM('student','senior','pwd','regular') NOT NULL,
    status      ENUM('pending','approved','rejected','escalated') DEFAULT 'pending',
    notes       TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add payment_status to rentals if not exists
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','refunded') DEFAULT 'paid';
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS return_date DATE DEFAULT NULL;
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS admin_notes TEXT DEFAULT NULL;

-- Add blocklist status to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_blocked TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS block_reason TEXT DEFAULT NULL;

-- Sample admin (password: admin123)
INSERT IGNORE INTO admins (name, email, password, role) VALUES
('Admin KineticBorrow', 'admin@kineticborrow.com', '$2y$10$TKh8H1.PfbuNIm4SUZgTiuqHnCGm7s2FJMfQ7A.BFRGZ4BjyRSVlG', 'superadmin');

-- Sample promotions
INSERT IGNORE INTO promotions (title, code, type, value, min_days, start_date, end_date) VALUES
('Summer Sale',       'SUMMER25', 'percentage', 25.00, 2, '2026-03-01', '2026-05-31'),
('Welcome Discount',  'WELCOME10', 'percentage', 10.00, 1, '2026-01-01', '2026-12-31'),
('Long Weekend Deal', 'WEEKEND50', 'fixed',      50.00, 3, '2026-03-01', '2026-04-30');

-- Sample ID verifications
INSERT IGNORE INTO id_verifications (user_id, id_type, status) VALUES
(1, 'student', 'approved');
