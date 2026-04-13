-- ============================================================
-- KineticBorrow Handler Tables — run in phpMyAdmin SQL tab
-- ============================================================
USE kineticborrow;

-- Handler accounts
CREATE TABLE IF NOT EXISTS handlers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment condition logs (check-out & check-in)
CREATE TABLE IF NOT EXISTS condition_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    rental_id    INT NOT NULL,
    handler_id   INT NOT NULL,
    type         ENUM('checkout','checkin') NOT NULL,
    condition_rating ENUM('excellent','good','fair','poor') DEFAULT 'good',
    notes        TEXT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_id)  REFERENCES rentals(id) ON DELETE CASCADE,
    FOREIGN KEY (handler_id) REFERENCES handlers(id) ON DELETE CASCADE
);

-- Incident / damage reports
CREATE TABLE IF NOT EXISTS incident_reports (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    rental_id    INT DEFAULT NULL,
    equipment_id INT NOT NULL,
    handler_id   INT NOT NULL,
    type         ENUM('damage','loss','incident','maintenance') NOT NULL,
    severity     ENUM('minor','moderate','severe') DEFAULT 'minor',
    description  TEXT NOT NULL,
    status       ENUM('open','reviewed','resolved') DEFAULT 'open',
    admin_notes  TEXT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (handler_id)   REFERENCES handlers(id)  ON DELETE CASCADE
);

-- Add handler_id columns to rentals for tracking who processed checkout/return
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS checkout_by  INT DEFAULT NULL;
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS checkin_by   INT DEFAULT NULL;
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS checkout_at  DATETIME DEFAULT NULL;
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS checkin_at   DATETIME DEFAULT NULL;

-- Sample handler (password: handler123)
INSERT IGNORE INTO handlers (name, email, password) VALUES
('Ken Handler', 'handler@kineticborrow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
