-- ============================================================================
-- Mission Seedlings — migration 002: full CMS platform
-- ----------------------------------------------------------------------------
-- Turns the single-page campaign site into the multi-page platform:
-- roles and accounts, participating churches, the seven-stage pathway,
-- milestones, impact indicators, stories, media, resources, KoboToolbox form
-- links, enquiry submissions, and an audit trail.
--
-- RUN ONCE, AFTER install.sql, against the same database:
--   mysql -u USER -p DBNAME < migrations/002_platform.sql
-- or paste it into phpMyAdmin > SQL.
--
-- PRIVACY BY DESIGN: there is deliberately no column anywhere in this schema
-- for participant names, phone numbers, household income, national ID numbers,
-- household GPS coordinates, seed-fund balances, budgets, staff allowances, or
-- cooperative bank details. That data lives only in KoboToolbox. Please do not
-- add such columns here — the absence of them is what makes accidental public
-- exposure impossible rather than merely unlikely.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. ACCOUNTS
-- ---------------------------------------------------------------------------
-- admin_users held a single administrator. It now holds every account type,
-- so it gets a truthful name.
RENAME TABLE admin_users TO users;

ALTER TABLE users
  ADD COLUMN role ENUM('super_admin','project_manager','editor','church_coordinator')
      NOT NULL DEFAULT 'editor' AFTER password_hash,
  ADD COLUMN full_name  VARCHAR(120) DEFAULT NULL AFTER role,
  ADD COLUMN email      VARCHAR(190) DEFAULT NULL AFTER full_name,
  ADD COLUMN church_id  INT DEFAULT NULL AFTER email,
  ADD COLUMN is_active  TINYINT(1) NOT NULL DEFAULT 1 AFTER church_id,
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN last_login_at       DATETIME DEFAULT NULL,
  ADD COLUMN password_changed_at DATETIME DEFAULT NULL,
  ADD COLUMN created_by INT DEFAULT NULL,
  ADD UNIQUE KEY email_unique (email),
  ADD KEY church_idx (church_id),
  ADD KEY role_idx (role);

-- The account created by install.sql becomes the first super admin.
UPDATE users SET role = 'super_admin', full_name = 'Site Administrator' WHERE username = 'admin';

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(190) NOT NULL,
  ip_hash      CHAR(64) NOT NULL,
  success      TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY lookup_idx (identifier, attempted_at),
  KEY ip_idx (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY token_unique (token_hash),
  KEY user_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT DEFAULT NULL,
  username    VARCHAR(60) DEFAULT NULL,      -- snapshot: survives user deletion
  action      VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) DEFAULT NULL,
  entity_id   INT DEFAULT NULL,
  summary     VARCHAR(255) DEFAULT NULL,
  ip_hash     CHAR(64) DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY created_idx (created_at),
  KEY entity_idx (entity_type, entity_id),
  KEY user_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 2. PATHWAY + MILESTONES
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pathway_stages (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  stage_key         VARCHAR(20) NOT NULL,
  name              VARCHAR(60) NOT NULL,
  short_description TEXT,
  icon_key          VARCHAR(50) NOT NULL DEFAULT 'seedling',
  status            ENUM('not_started','in_progress','complete') NOT NULL DEFAULT 'not_started',
  status_note       VARCHAR(255) DEFAULT NULL,
  sort_order        INT NOT NULL DEFAULT 0,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY stage_key_unique (stage_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pathway_stages (stage_key, name, short_description, icon_key, status, status_note, sort_order) VALUES
('discover', 'Discover', 'Churches learn about the Mission Seedlings model, assess their land and people, and express interest in joining a cohort.', 'target', 'complete', 'Pilot cohort identified in Jos', 1),
('organize', 'Organize', 'Each participating church mobilises ten members into a cooperative, agrees its savings rules, and opens a three-signatory account.', 'network', 'in_progress', 'Cooperatives forming across pilot churches', 2),
('equip',    'Equip',    'Members receive agronomy training, cooperative governance coaching, and the tools they need before any seedling reaches the ground.', 'book', 'in_progress', 'Training rounds underway', 3),
('plant',    'Plant',    'Seedlings are distributed and planted — ten to twenty per participant — on church and member land.', 'seedling', 'not_started', 'Scheduled for the coming planting season', 4),
('nurture',  'Nurture',  'Monthly cooperative meetings, savings contributions, tree care, and progress reporting keep the young trees and the group healthy.', 'sun', 'not_started', NULL, 5),
('harvest',  'Harvest',  'Mature trees produce their first cherries and the cooperative is connected to fair-trade coffee buyers.', 'coffee', 'not_started', NULL, 6),
('flourish', 'Flourish', 'The eighteen-month fund matures, members access their savings, and the cooperative reinvests — becoming self-sustaining and ready to mentor the next church.', 'growth', 'not_started', NULL, 7)
ON DUPLICATE KEY UPDATE name = pathway_stages.name;

CREATE TABLE IF NOT EXISTS milestones (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  stage_key    VARCHAR(20) NOT NULL,
  title        VARCHAR(200) NOT NULL,
  description  TEXT,
  status       ENUM('planned','in_progress','complete','blocked') NOT NULL DEFAULT 'planned',
  target_date  DATE DEFAULT NULL,
  completed_on DATE DEFAULT NULL,
  is_public    TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0,
  updated_by   INT DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY stage_idx (stage_key, sort_order),
  KEY public_idx (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 3. PARTICIPATING CHURCHES
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS churches (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  slug                VARCHAR(140) NOT NULL,
  name                VARCHAR(180) NOT NULL,
  community           VARCHAR(120) DEFAULT NULL,
  lga                 VARCHAR(120) DEFAULT NULL,
  state               VARCHAR(80) NOT NULL DEFAULT 'Plateau',
  denomination        VARCHAR(120) DEFAULT NULL,
  stage_key           VARCHAR(20) NOT NULL DEFAULT 'discover',
  participant_count   INT NOT NULL DEFAULT 0,
  seedlings_allocated INT NOT NULL DEFAULT 0,
  trees_planted       INT NOT NULL DEFAULT 0,
  survival_rate       DECIMAL(5,2) DEFAULT NULL,   -- percent, NULL until measured
  meetings_held       INT NOT NULL DEFAULT 0,
  reports_submitted   INT NOT NULL DEFAULT 0,
  joined_on           DATE DEFAULT NULL,
  photo_path          VARCHAR(255) DEFAULT NULL,
  summary             TEXT,
  -- Church site coordinates for the future GIS map. Staff-only: never rendered
  -- publicly, and never a participant household location.
  latitude            DECIMAL(10,7) DEFAULT NULL,
  longitude           DECIMAL(10,7) DEFAULT NULL,
  is_published        TINYINT(1) NOT NULL DEFAULT 0,
  sort_order          INT NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY slug_unique (slug),
  KEY published_idx (is_published, sort_order),
  KEY stage_idx (stage_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 4. IMPACT INDICATORS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS indicators (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  indicator_key  VARCHAR(60) NOT NULL,
  label          VARCHAR(120) NOT NULL,
  value_num      DECIMAL(14,2) NOT NULL DEFAULT 0,
  value_text     VARCHAR(120) DEFAULT NULL,       -- used when display_format='text'
  unit           VARCHAR(30) DEFAULT NULL,
  icon_key       VARCHAR(50) NOT NULL DEFAULT 'growth',
  display_format ENUM('integer','decimal','percent','text') NOT NULL DEFAULT 'integer',
  is_public      TINYINT(1) NOT NULL DEFAULT 1,
  is_featured    TINYINT(1) NOT NULL DEFAULT 0,   -- shown in the homepage strip
  sort_order     INT NOT NULL DEFAULT 0,
  -- KoboToolbox wiring: dormant in phase one, populated when live sync is
  -- switched on. Present now so enabling it needs no schema change.
  source         ENUM('manual','kobo') NOT NULL DEFAULT 'manual',
  kobo_form_id   VARCHAR(80) DEFAULT NULL,
  kobo_field     VARCHAR(120) DEFAULT NULL,
  last_synced_at DATETIME DEFAULT NULL,
  updated_by     INT DEFAULT NULL,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY indicator_key_unique (indicator_key),
  KEY public_idx (is_public, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO indicators (indicator_key, label, value_num, unit, icon_key, display_format, is_public, is_featured, sort_order) VALUES
('churches_onboarded',   'Churches onboarded',        0, NULL,     'shield',    'integer', 1, 1, 1),
('participants',         'Participants registered',   0, NULL,     'people',    'integer', 1, 1, 2),
('cooperatives_formed',  'Cooperatives formed',       0, NULL,     'network',   'integer', 1, 1, 3),
('seedlings_distributed','Seedlings distributed',     0, NULL,     'seedling',  'integer', 1, 1, 4),
('trees_planted',        'Trees planted',             0, NULL,     'land',      'integer', 1, 0, 5),
('meetings_completed',   'Monthly meetings completed',0, NULL,     'calendar',  'integer', 1, 0, 6),
('reports_submitted',    'Reports submitted',         0, NULL,     'checklist', 'integer', 1, 0, 7),
('survival_rate',        'Seedling survival rate',    0, '%',      'sun',       'percent', 1, 0, 8)
ON DUPLICATE KEY UPDATE label = indicators.label;

CREATE TABLE IF NOT EXISTS indicator_history (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  indicator_id INT NOT NULL,
  value_num    DECIMAL(14,2) NOT NULL,
  recorded_on  DATE NOT NULL,
  recorded_by  INT DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY indicator_day (indicator_id, recorded_on),
  KEY indicator_idx (indicator_id, recorded_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5. MEDIA + STORIES
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  path        VARCHAR(255) NOT NULL,
  alt_text    VARCHAR(255) DEFAULT NULL,
  caption     VARCHAR(255) DEFAULT NULL,
  mime        VARCHAR(60) DEFAULT NULL,
  bytes       INT DEFAULT NULL,
  width       INT DEFAULT NULL,
  height      INT DEFAULT NULL,
  -- Photographs of identifiable people may only be published with recorded
  -- consent. Admin screens refuse to publish an image where this is 0.
  has_consent TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stories (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(180) NOT NULL,
  title          VARCHAR(220) NOT NULL,
  excerpt        VARCHAR(400) DEFAULT NULL,
  body           MEDIUMTEXT,
  cover_media_id INT DEFAULT NULL,
  category       ENUM('update','church_story','training','planting','meeting','testimony') NOT NULL DEFAULT 'update',
  church_id      INT DEFAULT NULL,
  author_id      INT DEFAULT NULL,
  status         ENUM('draft','pending','published','rejected') NOT NULL DEFAULT 'draft',
  published_at   DATETIME DEFAULT NULL,
  review_note    TEXT,
  reviewed_by    INT DEFAULT NULL,
  reviewed_at    DATETIME DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY slug_unique (slug),
  KEY feed_idx (status, published_at),
  KEY church_idx (church_id),
  KEY category_idx (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_media (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  story_id   INT NOT NULL,
  media_id   INT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY story_media_unique (story_id, media_id),
  KEY story_idx (story_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 6. RESOURCE HUB + KOBOTOOLBOX
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resources (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  resource_type ENUM('link','file') NOT NULL DEFAULT 'link',
  url           VARCHAR(600) DEFAULT NULL,
  file_path     VARCHAR(255) DEFAULT NULL,
  file_bytes    INT DEFAULT NULL,
  category      VARCHAR(80) NOT NULL DEFAULT 'General',
  visibility    ENUM('public','staff','coordinator') NOT NULL DEFAULT 'public',
  icon_key      VARCHAR(50) NOT NULL DEFAULT 'book',
  sort_order    INT NOT NULL DEFAULT 0,
  is_visible    TINYINT(1) NOT NULL DEFAULT 1,
  created_by    INT DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY visibility_idx (visibility, is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kobo_forms (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(180) NOT NULL,
  description    TEXT,
  purpose        ENUM('registration','monthly_report','tree_monitoring','other') NOT NULL DEFAULT 'other',
  form_uid       VARCHAR(80) DEFAULT NULL,       -- KoboToolbox asset uid
  enketo_url     VARCHAR(600) DEFAULT NULL,      -- the shareable form link
  visibility     ENUM('staff','coordinator') NOT NULL DEFAULT 'staff',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  sort_order     INT NOT NULL DEFAULT 0,
  -- Dormant until live API sync is enabled.
  last_synced_at DATETIME DEFAULT NULL,
  cached_summary LONGTEXT DEFAULT NULL,          -- JSON blob of approved summary counts
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY active_idx (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO kobo_forms (name, description, purpose, visibility, sort_order) VALUES
('Participant registration', 'Register cooperative members for a participating church. Collects personal data — field staff only.', 'registration', 'staff', 1),
('Monthly cooperative report', 'Meeting attendance, savings activity, and cooperative health for the month.', 'monthly_report', 'coordinator', 2),
('Tree monitoring', 'Per-plot survival counts and tree health observations.', 'tree_monitoring', 'coordinator', 3);

-- ---------------------------------------------------------------------------
-- 7. ENQUIRIES / EXPRESSIONS OF INTEREST
-- ---------------------------------------------------------------------------
-- Contains personal contact details supplied voluntarily by enquirers.
-- Admin-only: never rendered on a public page. See DOCS for the retention note.
CREATE TABLE IF NOT EXISTS submissions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  type          ENUM('contact','interest','partnership') NOT NULL DEFAULT 'contact',
  name          VARCHAR(160) NOT NULL,
  email         VARCHAR(190) DEFAULT NULL,
  phone         VARCHAR(40) DEFAULT NULL,
  organisation  VARCHAR(180) DEFAULT NULL,
  church_name   VARCHAR(180) DEFAULT NULL,
  community     VARCHAR(140) DEFAULT NULL,
  message       TEXT,
  status        ENUM('new','read','responded','archived') NOT NULL DEFAULT 'new',
  internal_note TEXT,
  handled_by    INT DEFAULT NULL,
  handled_at    DATETIME DEFAULT NULL,
  ip_hash       CHAR(64) DEFAULT NULL,           -- hashed, for rate limiting only
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY inbox_idx (status, created_at),
  KEY type_idx (type),
  KEY ip_idx (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 8. NEW SETTINGS (page copy + site-wide options)
-- ---------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES

('site_title', 'Mission Seedlings'),
('call_status', 'open'),
('contact_email', ''),
('share_image', ''),
('footer_address', 'Jos, Plateau State, Nigeria'),
('current_phase', 'Organize'),

-- Home ----------------------------------------------------------------------
('home_eyebrow',        'Church-based community enterprise'),
('home_heading',        'Growing local. {{Flourishing}} together.'),
('home_subheading',     'Mission Seedlings helps churches and faith organisations build sustainable, locally rooted cooperative enterprises — starting with coffee in Jos, Plateau State.'),
('home_pilot_eyebrow',  'Current pilot'),
('home_pilot_heading',  'Coffee Ministry Mission'),
('home_pilot_body',     'Our first pilot brings churches in Jos together to form cooperatives, plant and care for coffee seedlings, save together, and connect to fair-trade buyers.'),
('home_stats_heading',  'Where the project stands'),
('home_stats_intro',    'A live snapshot of what churches and cooperatives across the pilot have achieved so far.'),
('home_pathway_heading','Seven stages, one pathway'),
('home_pathway_intro',  'Every participating church walks the same route — from first conversation to a flourishing, self-sustaining cooperative.'),
('home_stories_heading','From the field'),
('home_stories_intro',  'Training days, planting seasons, cooperative meetings, and the people making it happen.'),
('home_cta_heading',    'Bring Mission Seedlings to your church'),
('home_cta_body',       'If your congregation has land, willing members, and a heart for long-term community transformation, we would like to hear from you.'),

-- About ---------------------------------------------------------------------
('about_eyebrow',           'About'),
('about_heading',           'A model built for the long harvest'),
('about_intro',             'Mission Seedlings exists so that churches can become engines of sustainable local enterprise — not recipients of short-term aid, but stewards of livelihoods that outlast a single season.'),
('about_vision_heading',    'Our vision'),
('about_vision_body',       'Communities across Nigeria where local churches anchor thriving, member-owned enterprises rooted in the land, the culture, and the gifts of the people who live there.'),
('about_mission_heading',   'Our mission'),
('about_mission_body',      'To equip churches and faith organisations to form cooperatives, cultivate locally suitable crops and enterprises, save and govern together, and reach fair markets with dignity.'),
('about_why_heading',       'Why we built this'),
('about_why_body',          'Too much community development arrives as a one-off gift and leaves when the funding does. Churches, meanwhile, are already trusted, already organised, and already staying. Mission Seedlings puts a durable enterprise model into hands that will still be there in ten years.'),
('about_model_heading',     'The church-based enterprise model'),
('about_model_body',        'A participating church mobilises ten members into a cooperative. Members receive training, seedlings, and mentorship. They save together each month, care for their trees, meet monthly, and report progress. The church provides land, trust, and continuity; the cooperative provides discipline, ownership, and shared risk.'),
('about_framework_heading', 'How sustainability is built in'),
('about_framework_body',    'Ownership sits with members, not with us. Savings are locked for eighteen months so the fund can mature. Governance runs through a three-signatory account. Training is repeated, not one-off. And market access is arranged before the first harvest, not after it.'),
('about_future_heading',    'Beyond coffee'),
('about_future_body',       'Coffee is where we start because Plateau State grows it well. The same pathway applies to any locally suitable crop or enterprise, and the platform is built to carry additional crops, cities, and community enterprises as the network grows.'),

-- Pathway -------------------------------------------------------------------
('pathway_eyebrow', 'The pathway'),
('pathway_heading', 'Seven stages from seed to harvest'),
('pathway_intro',   'Every church that joins Mission Seedlings walks the same seven stages. Each one has to be genuinely complete before the next begins — that is what keeps the enterprise standing years later.'),
('pathway_outro',   'Stages advance at the pace of the people doing the work, not a funding calendar.'),

-- Growth Tracker ------------------------------------------------------------
('tracker_eyebrow',            'Growth tracker'),
('tracker_heading',            'Project progress, in the open'),
('tracker_intro',              'Summary indicators across every participating church, updated by the project team as verified field reports come in.'),
('tracker_milestones_heading', 'Implementation milestones'),
('tracker_privacy_note',       'These figures are aggregate only. Participant names, contact details, household information, and all financial data are held privately and are never published.'),

-- Cooperative model ---------------------------------------------------------
('coop_eyebrow',            'Cooperative model'),
('coop_heading',            'How the cooperative works'),
('coop_intro',              'A simple, repeatable structure that gives members real ownership and keeps the group accountable to itself.'),
('coop_savings_heading',    'Saving together'),
('coop_savings_body',       'Each member contributes between ₦100 and ₦2,000 every month into their own seed fund, held within the cooperative. Contributions are locked for eighteen months so the fund has time to mature and the group builds the habit before the money moves.'),
('coop_governance_heading', 'Governance and accounts'),
('coop_governance_body',    'Every cooperative operates a three-signatory account. No single person can move funds alone, and the group agrees its own rules at formation. Balances and account details are the cooperative''s own business and are never published.'),
('coop_reporting_heading',  'Meetings and reporting'),
('coop_reporting_body',     'Members meet monthly to review tree care, record savings, and settle any issues. The cooperative submits a monthly report and a tree-monitoring update so the project team can see how the trees and the group are doing.'),
('coop_maturity_heading',   'At eighteen months'),
('coop_maturity_body',      'When the lock period ends, members access their seed funds. By then the trees are established, the group has a track record, and the cooperative can reinvest, expand, or mentor a neighbouring church.'),

-- Churches ------------------------------------------------------------------
('churches_eyebrow',      'Participating churches'),
('churches_heading',      'The churches growing with us'),
('churches_intro',        'Congregations across Plateau State working through the Seedlings pathway with their communities.'),
('churches_privacy_note', 'Church profiles show project information only. We never publish participant names, phone numbers, household data, or any financial details.'),

-- Stories -------------------------------------------------------------------
('stories_eyebrow', 'Growth stories'),
('stories_heading', 'Updates from the field'),
('stories_intro',   'Training days, planting seasons, cooperative meetings, and testimonies from the people doing the work.'),

-- Resources -----------------------------------------------------------------
('resources_eyebrow',    'Resource hub'),
('resources_heading',    'Documents, forms, and guidance'),
('resources_intro',      'Everything a participating church or field worker needs, in one place.'),
('resources_staff_note', 'Restricted resources are visible only when you are signed in with the right role. Field data collected through these forms stays in KoboToolbox and is never published here.'),

-- Contact -------------------------------------------------------------------
('contact_eyebrow',        'Get in touch'),
('contact_heading',        'Talk to the Mission Seedlings team'),
('contact_intro',          'Whether you lead a church, represent an organisation, or simply want to know more, we would be glad to hear from you.'),
('contact_eoi_heading',    'Express interest as a church'),
('contact_eoi_body',       'Churches interested in joining a future cohort can register their interest and we will be in touch when the next call opens.'),
('contact_partner_heading','Partnership enquiries'),
('contact_partner_body',   'We work with organisations investing in Plateau State for the long haul — training partners, funders, agronomists, and buyers.'),

-- Call page -----------------------------------------------------------------
('call_closed_note', 'Applications for this cohort are now closed. Churches are welcome to register interest for the next cycle.')

ON DUPLICATE KEY UPDATE setting_value = settings.setting_value;
-- ^ existing keys keep their edited values; only genuinely new keys are added.

-- The one exception: the project is now branded "Mission Seedlings". Only
-- rename if it is still the untouched v1 default.
UPDATE settings SET setting_value = 'Mission Seedlings'
  WHERE setting_key = 'site_title' AND setting_value = 'Seedlings';
