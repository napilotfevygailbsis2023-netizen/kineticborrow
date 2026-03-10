-- Run this in phpMyAdmin SQL tab
USE kineticborrow;

-- Add ID image columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_image VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_status ENUM('none','pending','approved','rejected') DEFAULT 'none';
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_reject_reason VARCHAR(255) DEFAULT NULL;

-- Add image column to id_verifications
ALTER TABLE id_verifications ADD COLUMN IF NOT EXISTS id_image VARCHAR(255) DEFAULT NULL;
