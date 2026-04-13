-- Run this in phpMyAdmin once
USE kineticborrow;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS verify_code VARCHAR(6) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS verify_code_expires DATETIME DEFAULT NULL;
