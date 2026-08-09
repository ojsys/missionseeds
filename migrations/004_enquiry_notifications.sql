-- ============================================================================
-- Mission Seedlings — migration 004: enquiry notifications
-- ----------------------------------------------------------------------------
-- Tells the team when someone uses the contact form, so the inbox does not have
-- to be checked on the off-chance.
--
-- RUN ONCE, AFTER 003_email.sql:
--   mysql -u USER -p DBNAME < migrations/004_enquiry_notifications.sql
-- ============================================================================

SET NAMES utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
('notify_enquiries',    '0'),
('notify_enquiries_to', '')
ON DUPLICATE KEY UPDATE setting_value = settings.setting_value;
