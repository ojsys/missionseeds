-- Seedlings CMS — schema + starter content
-- Import this in cPanel's phpMyAdmin (or via `mysql -u USER -p DBNAME < install.sql`)
-- into the empty database you created for this site.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(100) PRIMARY KEY,
  setting_value LONGTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS list_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  group_key   VARCHAR(50) NOT NULL,
  icon_key    VARCHAR(50) NOT NULL DEFAULT 'seedling',
  title       VARCHAR(255) DEFAULT NULL,
  body        TEXT,
  sort_order  INT NOT NULL DEFAULT 0,
  is_visible  TINYINT(1) NOT NULL DEFAULT 1,
  KEY group_key_idx (group_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin login: username "admin", password "SeedlingsAdmin2026!"
-- CHANGE THIS PASSWORD immediately after your first login (Admin > Change password).
INSERT INTO admin_users (username, password_hash) VALUES
('admin', '$2y$10$uUFVxgFPKP/C/REZkRCrDOrqVmCRbVDs1nb1BrUssK.WodDLguyh.');

INSERT INTO settings (setting_key, setting_value) VALUES
('site_title',        'Seedlings'),
('tagline',            'Growing Local. Flourishing Together.'),

('hero_eyebrow',       'Pilot location — Jos, Plateau State'),
('hero_heading',       'Calling churches to grow community enterprises'),
('hero_subheading',    'We''re seeking churches in Plateau State ready to become centers of community transformation — cultivating sustainable, locally rooted enterprises alongside the people they serve.'),
('hero_image',         'assets/uploads/hero.jpg'),

('seed_eyebrow',       '01 — The seed'),
('seed_heading',       'A pilot rooted in coffee'),
('seed_body',          'The first Seedlings pilot focuses on {{coffee cultivation}}, bringing together churches, women, and young people to build long-term livelihoods through cooperative enterprise, stewardship, and partnership.\n\nThis isn''t a one-off donation or a short program. It''s an invitation for your church to become a center of gravity — a place where land, faith, and neighbours turn into a working, generation-spanning enterprise.'),

('who_eyebrow',        '02 — The roots'),
('who_heading',        'Who should apply'),
('who_intro',          'Seedlings is built for churches with the land, the will, and the people to grow something that outlasts a single harvest.'),

('pilot_eyebrow',      'Pilot initiative'),
('pilot_heading',      'Coffee Ministry Mission'),
('pilot_body',         'A Seedlings pilot — the first church-led coffee cooperative of its kind in Plateau State.'),

('receive_eyebrow',    '03 — What grows'),
('receive_heading',    'What participating churches receive'),

('harvest_eyebrow',    '04 — The harvest'),
('harvest_heading',    'Join the first Seedlings pilot'),
('harvest_subheading', 'Become part of a growing network of churches cultivating hope through sustainable community enterprise.'),

('deadline_label',     'Expression of interest deadline'),
('deadline_date',      '2026-07-31'),
('deadline_note',      'Applications from churches in Plateau State close on this date. Late enquiries are still welcome for the next pilot cycle.'),

('eoi_form_url',       'https://forms.gle/replace-with-your-form-link'),
('eoi_button_label',   'Apply now'),

('whatsapp_number',    '2348137912872'),
('whatsapp_display',   '+234 813 791 2872'),

('footer_tagline',     'Planting hope. Growing enterprises. Transforming communities for generations.');

INSERT INTO list_items (group_key, icon_key, title, body, sort_order) VALUES
('criteria', 'land',      NULL, 'Have land suitable for community enterprise.', 1),
('criteria', 'people',    NULL, 'Are passionate about empowering women and youth.', 2),
('criteria', 'target',    NULL, 'Desire sustainable, measurable community impact.', 3),
('criteria', 'handshake', NULL, 'Are willing to participate in long-term cooperative development.', 4);

INSERT INTO list_items (group_key, icon_key, title, body, sort_order) VALUES
('receive', 'book',      'Technical training', '', 1),
('receive', 'network',   'Cooperative development support', '', 2),
('receive', 'seedling',  'Coffee seedlings', '', 3),
('receive', 'mentor',    'Enterprise mentorship', '', 4),
('receive', 'checklist', 'Ongoing monitoring and coaching', '', 5),
('receive', 'market',    'Connection to long-term market opportunities', '', 6);
