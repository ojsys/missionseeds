-- ============================================================================
-- Mission Seedlings — migration 003: email and invitations
-- ----------------------------------------------------------------------------
-- Replaces the "read the temporary password down the phone" flow with a proper
-- invitation: a Super Admin creates the account, the person receives an email,
-- and they choose their own password. Nobody but the account holder ever knows
-- it. Adds SMTP settings, a delivery log, and password-reset by email.
--
-- RUN ONCE, AFTER 002_platform.sql:
--   mysql -u USER -p DBNAME < migrations/003_email.sql
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. TOKENS
-- ---------------------------------------------------------------------------
-- password_resets already exists. It now carries invitations too — same
-- mechanism, different expiry and different email copy.
ALTER TABLE password_resets
  ADD COLUMN purpose ENUM('invite','reset') NOT NULL DEFAULT 'reset' AFTER user_id,
  ADD COLUMN requested_ip CHAR(64) DEFAULT NULL,
  ADD KEY purpose_idx (purpose, expires_at);

-- ---------------------------------------------------------------------------
-- 2. DELIVERY LOG
-- ---------------------------------------------------------------------------
-- Shared hosting mail is unreliable enough that "did it actually send?" needs a
-- straight answer. Deliberately stores no message body: invitation and reset
-- emails contain single-use tokens, and those must not be readable here.
CREATE TABLE IF NOT EXISTS email_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  to_email    VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  template    VARCHAR(60) DEFAULT NULL,
  transport   ENUM('smtp','mail','none') NOT NULL DEFAULT 'smtp',
  status      ENUM('sent','failed') NOT NULL,
  error       VARCHAR(500) DEFAULT NULL,
  user_id     INT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY created_idx (created_at),
  KEY status_idx (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 3. SMTP + EMAIL SETTINGS
-- ---------------------------------------------------------------------------
-- smtp_password_enc holds an AES-256-GCM ciphertext keyed on APP_KEY, never a
-- plaintext password. The admin screen writes it and never reads it back.
INSERT INTO settings (setting_key, setting_value) VALUES
('smtp_enabled',      '0'),
('smtp_host',         ''),
('smtp_port',         '587'),
('smtp_encryption',   'tls'),
('smtp_username',     ''),
('smtp_password_enc', ''),
('mail_from_email',   ''),
('mail_from_name',    'Mission Seedlings'),
('mail_reply_to',     ''),
('mail_signoff',      'The Mission Seedlings team')
ON DUPLICATE KEY UPDATE setting_value = settings.setting_value;
