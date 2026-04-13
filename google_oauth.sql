-- ============================================================
-- KineticBorrow — Google OAuth Migration
-- Run this in phpMyAdmin ONCE before using Google Sign-In
-- ============================================================
USE kineticborrow;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) DEFAULT NULL UNIQUE,
  ADD COLUMN IF NOT EXISTS auth_provider ENUM('local','google') DEFAULT 'local',
  ADD COLUMN IF NOT EXISTS avatar VARCHAR(500) DEFAULT NULL;
