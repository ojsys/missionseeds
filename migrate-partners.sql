-- Seedlings CMS — Partners feature migration
-- Run this ONCE against an existing Seedlings database that was created before
-- the Partners section existed. Fresh installs get all of this from install.sql
-- already, so you do not need to run this after importing install.sql.
--
-- cPanel/hPanel: phpMyAdmin > select your database > SQL tab > paste > Go
-- CLI:           mysql -u USER -p DBNAME < migrate-partners.sql
--
-- Safe to re-run: the table is created only if missing, and the settings use
-- INSERT IGNORE so any wording you have already edited is left untouched.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS partners (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(160) NOT NULL,
  logo_path   VARCHAR(255) NOT NULL,
  link_url    VARCHAR(500) DEFAULT NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_visible  TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY sort_order_idx (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('partners_eyebrow',  'Partners'),
('partners_heading',  'Growing together'),
('partners_intro',    'Seedlings grows through partnership — with churches, cooperatives, and organisations investing in Plateau State for the long haul.');
