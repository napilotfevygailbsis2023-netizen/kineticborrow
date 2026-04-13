-- ============================================================
-- KineticBorrow — Admin ↔ Handler Chat System
-- Run this in phpMyAdmin ONCE
-- ============================================================
USE kineticborrow;

-- Chat threads (one per admin-handler pair)
CREATE TABLE IF NOT EXISTS chat_threads (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT NOT NULL,
    handler_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pair (admin_id, handler_id),
    FOREIGN KEY (admin_id)   REFERENCES admins(id)   ON DELETE CASCADE,
    FOREIGN KEY (handler_id) REFERENCES handlers(id) ON DELETE CASCADE
);

-- Chat messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    thread_id   INT NOT NULL,
    sender_type ENUM('admin','handler') NOT NULL,
    sender_id   INT NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE
);
