-- Add notification preference columns to users table
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS notif_promo_email   TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS notif_promo_sms     TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS notif_reminder_email TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS notif_reminder_sms  TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS notif_account_email TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS notif_account_sms   TINYINT(1) DEFAULT 1;
