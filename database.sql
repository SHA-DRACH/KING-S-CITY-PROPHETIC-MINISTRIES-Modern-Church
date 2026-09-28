-- =====================================================================
--  King's City Prophetic Ministries — Church Website & Management System
--  MySQL 8+ schema + development seed data
--
--  Import:  mysql -u root -p < database.sql
--  Demo accounts (development only — every one is forced to change
--  password at first login):  password  KingsCity@2026
--     superadmin@kingscityministries.org   Super Administrator
--     admin@kingscityministries.org        Administrator
--     pastor@kingscityministries.org       Senior Pastor
--     assistant@kingscityministries.org    Admin Assistant
--     youth@kingscityministries.org        Department Head (Youth)
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS kings_city CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kings_city;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS photo_albums, rate_limits, password_resets, activity_logs, contact_messages, social_links, church_settings,
  service_times, hero_videos, media, pastor_profiles, leaders, giving_transactions, giving_methods, giving_categories,
  testimonies, prayer_requests, gallery, gallery_categories, pages, announcements, events, event_categories,
  sermons, sermon_categories, department_users, departments, user_permissions, users, role_permissions,
  permissions, roles;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  ACCESS CONTROL
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL,
  slug          VARCHAR(80)  NOT NULL UNIQUE,
  description   VARCHAR(255) NULL,
  level         SMALLINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Hierarchy: users may only manage roles/users below their own level',
  is_super      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bypasses all permission checks',
  is_system     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cannot be deleted',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL UNIQUE COMMENT 'e.g. sermons.create',
  label         VARCHAR(120) NOT NULL,
  module        VARCHAR(60)  NOT NULL,
  description   VARCHAR(255) NULL,
  is_critical   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Only a Super Administrator may grant',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_perm_module (module)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id              INT UNSIGNED NOT NULL,
  first_name           VARCHAR(80)  NOT NULL,
  last_name            VARCHAR(80)  NOT NULL,
  title                VARCHAR(120) NULL COMMENT 'Display title, e.g. Youth Ministry Head',
  email                VARCHAR(190) NOT NULL UNIQUE,
  phone                VARCHAR(40)  NULL,
  password_hash        VARCHAR(255) NOT NULL,
  avatar               VARCHAR(255) NULL,
  status               ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at        DATETIME NULL,
  last_login_ip        VARCHAR(45) NULL,
  created_by           INT UNSIGNED NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- Direct per-user overrides on top of the role: granted=1 adds, granted=0 revokes.
CREATE TABLE user_permissions (
  user_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  granted       TINYINT(1) NOT NULL DEFAULT 1,
  granted_by    INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
  FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE departments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(120) NOT NULL,
  slug             VARCHAR(140) NOT NULL UNIQUE,
  description      TEXT NULL,
  image            VARCHAR(255) NULL,
  icon             VARCHAR(60)  NULL DEFAULT 'fa-church',
  head_user_id     INT UNSIGNED NULL,
  head_name        VARCHAR(120) NULL COMMENT 'Shown publicly when the head has no account',
  contact_email    VARCHAR(190) NULL,
  contact_phone    VARCHAR(40)  NULL,
  meeting_schedule VARCHAR(255) NULL,
  is_public        TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Listed on the public Ministries page',
  sort_order       SMALLINT NOT NULL DEFAULT 0,
  status           ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_dept_status (status, sort_order)
) ENGINE=InnoDB;

CREATE TABLE department_users (
  department_id INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  is_head       TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (department_id, user_id),
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  CONTENT
-- ---------------------------------------------------------------------
CREATE TABLE sermon_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE sermons (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(200) NOT NULL,
  slug           VARCHAR(220) NOT NULL UNIQUE,
  speaker        VARCHAR(120) NOT NULL,
  sermon_date    DATE NOT NULL,
  category_id    INT UNSIGNED NULL,
  media_type     ENUM('video','audio') NOT NULL DEFAULT 'video',
  video_url      VARCHAR(255) NULL COMMENT 'YouTube / Facebook link',
  video_file     VARCHAR(255) NULL,
  audio_file     VARCHAR(255) NULL,
  thumbnail      VARCHAR(255) NULL,
  scripture      VARCHAR(160) NULL,
  description    TEXT NULL,
  is_featured    TINYINT(1) NOT NULL DEFAULT 0,
  allow_download TINYINT(1) NOT NULL DEFAULT 0,
  status         ENUM('draft','published') NOT NULL DEFAULT 'draft',
  views          INT UNSIGNED NOT NULL DEFAULT 0,
  created_by     INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES sermon_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_sermons_pub (status, sermon_date),
  FULLTEXT INDEX ft_sermons (title, speaker, scripture, description)
) ENGINE=InnoDB;

CREATE TABLE event_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE events (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title                 VARCHAR(200) NOT NULL,
  slug                  VARCHAR(220) NOT NULL UNIQUE,
  category_id           INT UNSIGNED NULL,
  department_id         INT UNSIGNED NULL,
  description           TEXT NULL,
  event_date            DATE NOT NULL,
  end_date              DATE NULL,
  start_time            TIME NULL,
  end_time              TIME NULL,
  location              VARCHAR(200) NULL,
  image                 VARCHAR(255) NULL,
  organizer             VARCHAR(120) NULL,
  requires_registration TINYINT(1) NOT NULL DEFAULT 0,
  registration_url      VARCHAR(255) NULL,
  is_featured           TINYINT(1) NOT NULL DEFAULT 0,
  status                ENUM('draft','published','cancelled') NOT NULL DEFAULT 'draft',
  created_by            INT UNSIGNED NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_events_pub (status, event_date)
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(200) NOT NULL,
  description   TEXT NULL,
  image         VARCHAR(255) NULL,
  link_url      VARCHAR(255) NULL,
  department_id INT UNSIGNED NULL,
  start_date    DATE NULL,
  end_date      DATE NULL,
  status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_by    INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ann_active (status, start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE pages (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(120) NOT NULL UNIQUE,
  title            VARCHAR(200) NOT NULL,
  content          MEDIUMTEXT NULL,
  meta_description VARCHAR(300) NULL,
  updated_by       INT UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE gallery_categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Albums group bulk-uploaded photos by a day or a month.
--   downloads = members find and download their own photos
--   event     = reference gallery of events and ministry life
CREATE TABLE photo_albums (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(200) NULL COMMENT 'Optional — the date is shown when empty',
  album_date     DATE NOT NULL,
  date_precision ENUM('day','month') NOT NULL DEFAULT 'day',
  type           ENUM('downloads','event') NOT NULL DEFAULT 'downloads',
  category_id    INT UNSIGNED NULL,
  department_id  INT UNSIGNED NULL,
  description    VARCHAR(300) NULL,
  allow_download TINYINT(1) NOT NULL DEFAULT 1,
  is_published   TINYINT(1) NOT NULL DEFAULT 1,
  cover_id       INT UNSIGNED NULL,
  created_by     INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES gallery_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_album_pub (is_published, type, album_date)
) ENGINE=InnoDB;

CREATE TABLE gallery (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id       INT UNSIGNED NULL,
  title          VARCHAR(200) NULL,
  category_id    INT UNSIGNED NULL,
  department_id  INT UNSIGNED NULL,
  media_type     ENUM('image','video') NOT NULL DEFAULT 'image',
  file_path      VARCHAR(255) NULL,
  thumb_path     VARCHAR(255) NULL,
  width          SMALLINT UNSIGNED NULL,
  height         SMALLINT UNSIGNED NULL,
  video_url      VARCHAR(255) NULL,
  caption        VARCHAR(300) NULL,
  is_published   TINYINT(1) NOT NULL DEFAULT 1,
  download_count INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by    INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (album_id) REFERENCES photo_albums(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES gallery_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_gallery_album (album_id, id),
  INDEX idx_gallery_pub (is_published, created_at)
) ENGINE=InnoDB;

CREATE TABLE media (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type     VARCHAR(100) NOT NULL,
  file_type     ENUM('image','video','audio','document') NOT NULL,
  size_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  module        VARCHAR(60) NULL,
  uploaded_by   INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_media_type (file_type, created_at)
) ENGINE=InnoDB;

-- One active background video per public page (home, about, sermons, …)
CREATE TABLE hero_videos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page        VARCHAR(40) NOT NULL DEFAULT 'home',
  title       VARCHAR(160) NOT NULL,
  description VARCHAR(300) NULL,
  video_mp4   VARCHAR(255) NULL,
  video_webm  VARCHAR(255) NULL,
  video_mobile VARCHAR(255) NULL COMMENT 'Optional lighter file for small screens',
  poster      VARCHAR(255) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_hero_page (page, is_active)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  MINISTRY
-- ---------------------------------------------------------------------
CREATE TABLE prayer_requests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NULL,
  phone       VARCHAR(40)  NULL,
  request     TEXT NOT NULL,
  is_public   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Visitor explicitly allowed public sharing',
  status      ENUM('new','assigned','in_prayer','answered','closed') NOT NULL DEFAULT 'new',
  assigned_to INT UNSIGNED NULL,
  notes       TEXT NULL COMMENT 'Internal staff notes, never public',
  ip_address  VARCHAR(45) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_prayer_status (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE testimonies (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                  VARCHAR(120) NOT NULL,
  email                 VARCHAR(190) NULL,
  title                 VARCHAR(200) NULL,
  testimony             TEXT NOT NULL,
  photo                 VARCHAR(255) NULL,
  permission_to_publish TINYINT(1) NOT NULL DEFAULT 0,
  status                ENUM('pending','approved','rejected','published') NOT NULL DEFAULT 'pending',
  reviewed_by           INT UNSIGNED NULL,
  reviewed_at           DATETIME NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_testimony_status (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE leaders (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  position   VARCHAR(120) NOT NULL,
  photo      VARCHAR(255) NULL,
  bio        TEXT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE pastor_profiles (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NULL,
  name             VARCHAR(120) NOT NULL,
  position         VARCHAR(120) NOT NULL DEFAULT 'Senior Pastor',
  photo            VARCHAR(255) NULL,
  short_bio        TEXT NULL,
  biography        MEDIUMTEXT NULL,
  ministry_journey MEDIUMTEXT NULL,
  vision           TEXT NULL,
  message          MEDIUMTEXT NULL,
  scripture        TEXT NULL,
  scripture_ref    VARCHAR(80) NULL,
  email            VARCHAR(190) NULL,
  phone            VARCHAR(40) NULL,
  is_primary       TINYINT(1) NOT NULL DEFAULT 1,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  GIVING
-- ---------------------------------------------------------------------
CREATE TABLE giving_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  icon        VARCHAR(60) NULL DEFAULT 'fa-hand-holding-heart',
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE giving_methods (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  type           ENUM('mobile_money','bank_transfer','online_gateway','cash','other') NOT NULL,
  provider       VARCHAR(100) NULL COMMENT 'e.g. Orange Money, MTN MoMo, Ecobank',
  account_name   VARCHAR(150) NULL,
  account_number VARCHAR(100) NULL,
  gateway_driver VARCHAR(60)  NULL COMMENT 'Online gateway driver key (see models/PaymentGateway.php)',
  instructions   TEXT NULL,
  icon           VARCHAR(60) NULL DEFAULT 'fa-wallet',
  sort_order     SMALLINT NOT NULL DEFAULT 0,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE giving_transactions (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference         VARCHAR(40) NOT NULL UNIQUE COMMENT 'System generated reference',
  category_id       INT UNSIGNED NULL,
  method_id         INT UNSIGNED NULL,
  amount            DECIMAL(12,2) NOT NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'USD',
  donor_name        VARCHAR(120) NULL,
  email             VARCHAR(190) NULL,
  phone             VARCHAR(40) NULL,
  is_anonymous      TINYINT(1) NOT NULL DEFAULT 0,
  payment_reference VARCHAR(100) NULL COMMENT 'Mobile money / bank transaction ID provided by the giver',
  status            ENUM('pending','confirmed','failed','refunded') NOT NULL DEFAULT 'pending',
  notes             TEXT NULL,
  source            ENUM('online','recorded') NOT NULL DEFAULT 'online',
  recorded_by       INT UNSIGNED NULL,
  confirmed_at      DATETIME NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES giving_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (method_id) REFERENCES giving_methods(id) ON DELETE SET NULL,
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_giving_status (status, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  SETTINGS / SYSTEM
-- ---------------------------------------------------------------------
CREATE TABLE church_settings (
  setting_key   VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE social_links (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform   VARCHAR(40) NOT NULL,
  url        VARCHAR(255) NOT NULL,
  icon       VARCHAR(60) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE service_times (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  day_of_week VARCHAR(20) NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NULL,
  description VARCHAR(255) NULL,
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  phone      VARCHAR(40) NULL,
  subject    VARCHAR(200) NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60) NOT NULL,
  module      VARCHAR(60) NOT NULL,
  description VARCHAR(500) NULL,
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_logs_created (created_at),
  INDEX idx_logs_module (module)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Generic throttle table (login attempts, public form submissions)
CREATE TABLE rate_limits (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rl_key     VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rl (rl_key, created_at)
) ENGINE=InnoDB;

-- =====================================================================
--  SEED: ROLES & PERMISSIONS
-- =====================================================================
INSERT INTO roles (id, name, slug, description, level, is_super, is_system) VALUES
 (1, 'Super Administrator', 'super_admin',     'Full system control.', 100, 1, 1),
 (2, 'Administrator',       'administrator',   'Manages the website within permissions granted by the Super Administrator.', 80, 0, 1),
 (3, 'Senior Pastor',       'senior_pastor',   'Pastoral office with a dedicated pastor dashboard.', 70, 0, 1),
 (4, 'Content Manager',     'content_manager', 'Manages sermons, events, gallery, announcements and pages.', 50, 0, 1),
 (5, 'Admin Assistant',     'admin_assistant', 'Performs administrative tasks assigned by an administrator.', 40, 0, 1),
 (6, 'Department Head',     'department_head', 'Leads a ministry department; scoped to their department.', 30, 0, 1);

INSERT INTO permissions (name, label, module, is_critical) VALUES
 ('dashboard.view','View Dashboard','Dashboard',0),
 ('statistics.view','View Church Statistics','Dashboard',0),
 ('users.view','View Users','Users',0),('users.create','Create Users','Users',0),('users.edit','Edit Users','Users',0),('users.delete','Delete Users','Users',0),
 ('departments.view','View Departments','Departments',0),('departments.create','Create Departments','Departments',0),('departments.edit','Edit Departments','Departments',0),
 ('departments.delete','Delete Departments','Departments',0),('departments.assign','Assign Department Members','Departments',0),
 ('departments.all_access','Access All Departments'' Content','Departments',0),
 ('roles.view','View Roles','Roles',0),('roles.create','Create Roles','Roles',1),('roles.edit','Edit Roles & Role Permissions','Roles',1),('roles.delete','Delete Roles','Roles',1),
 ('permissions.view','View Permissions','Permissions',0),('permissions.assign','Grant / Revoke User Permissions','Permissions',0),
 ('sermons.view','View Sermons','Sermons',0),('sermons.create','Create Sermons','Sermons',0),('sermons.edit','Edit Sermons','Sermons',0),('sermons.delete','Delete Sermons','Sermons',0),('sermons.publish','Publish Sermons','Sermons',0),
 ('events.view','View Events','Events',0),('events.create','Create Events','Events',0),('events.edit','Edit Events','Events',0),('events.delete','Delete Events','Events',0),('events.publish','Publish Events','Events',0),
 ('gallery.view','View Gallery','Gallery',0),('gallery.upload','Upload to Gallery','Gallery',0),('gallery.edit','Edit Gallery','Gallery',0),('gallery.delete','Delete Gallery Items','Gallery',0),
 ('media.view','View Media Library','Media',0),('media.upload','Upload Media','Media',0),('media.delete','Delete Media','Media',0),
 ('prayer_requests.view','View Prayer Requests','Prayer Requests',0),('prayer_requests.assign','Assign Prayer Requests','Prayer Requests',0),
 ('prayer_requests.update','Update Prayer Requests','Prayer Requests',0),('prayer_requests.delete','Delete Prayer Requests','Prayer Requests',0),
 ('testimonies.view','View Testimonies','Testimonies',0),('testimonies.approve','Approve / Publish Testimonies','Testimonies',0),
 ('testimonies.reject','Reject Testimonies','Testimonies',0),('testimonies.delete','Delete Testimonies','Testimonies',0),
 ('giving.view','View Giving','Giving',0),('giving.manage','Manage Giving & Payment Methods','Giving',0),
 ('announcements.view','View Announcements','Announcements',0),('announcements.create','Create Announcements','Announcements',0),
 ('announcements.edit','Edit Announcements','Announcements',0),('announcements.delete','Delete Announcements','Announcements',0),('announcements.publish','Publish Announcements','Announcements',0),
 ('pages.view','View Pages','Pages',0),('pages.edit','Edit Pages & Leadership','Pages',0),
 ('pastor.view','View Pastor Profile','Pastor',0),('pastor.edit','Edit Pastor Profile & Message','Pastor',0),
 ('messages.view','View Contact Messages','Messages',0),('messages.delete','Delete Contact Messages','Messages',0),
 ('website_settings.view','View Website Settings','Website',0),('website_settings.edit','Edit Website Settings','Website',0),
 ('hero_video.view','View Hero Videos','Website',0),('hero_video.upload','Upload / Activate Hero Videos','Website',0),('hero_video.delete','Delete Hero Videos','Website',0),
 ('system_settings.edit','Edit System & Security Settings','System',1),
 ('activity_logs.view','View Activity Logs','System',1);

-- Administrator: everything that is not critical, plus activity logs
INSERT INTO role_permissions (role_id, permission_id)
 SELECT 2, id FROM permissions WHERE is_critical = 0 OR name = 'activity_logs.view';

INSERT INTO role_permissions (role_id, permission_id)
 SELECT 3, id FROM permissions WHERE name IN (
  'dashboard.view','statistics.view','pastor.view','pastor.edit','sermons.view','sermons.create','sermons.edit','sermons.publish',
  'events.view','announcements.view','announcements.create','announcements.edit','announcements.publish',
  'prayer_requests.view','prayer_requests.update','testimonies.view','testimonies.approve','gallery.view','departments.view','departments.all_access','media.view','media.upload');

INSERT INTO role_permissions (role_id, permission_id)
 SELECT 4, id FROM permissions WHERE name IN (
  'dashboard.view','sermons.view','sermons.create','sermons.edit','sermons.delete','sermons.publish',
  'events.view','events.create','events.edit','events.delete','events.publish',
  'gallery.view','gallery.upload','gallery.edit','gallery.delete','announcements.view','announcements.create','announcements.edit',
  'announcements.delete','announcements.publish','pages.view','pages.edit','media.view','media.upload','media.delete','departments.all_access','testimonies.view');

INSERT INTO role_permissions (role_id, permission_id)
 SELECT 5, id FROM permissions WHERE name IN (
  'dashboard.view','sermons.view','sermons.create','sermons.edit','events.view','events.create','events.edit',
  'gallery.view','gallery.upload','gallery.edit','announcements.view','announcements.create','announcements.edit',
  'prayer_requests.view','media.view','media.upload','departments.all_access','messages.view');

INSERT INTO role_permissions (role_id, permission_id)
 SELECT 6, id FROM permissions WHERE name IN (
  'dashboard.view','departments.view','events.view','events.create','events.edit','announcements.view','announcements.create','announcements.edit','gallery.view','gallery.upload','media.upload');

-- =====================================================================
--  SEED: DEMO USERS (development only)
-- =====================================================================
INSERT INTO users (id, role_id, first_name, last_name, title, email, phone, password_hash, status, must_change_password) VALUES
 (1, 1, 'System', 'Administrator', 'Super Administrator', 'superadmin@kingscityministries.org', NULL, '$2y$10$ggOqGdySdQX70v6WmVTjx.UQDvohFDL0CvU.I12tYl3/xF7qNA4Mq', 'active', 1),
 (2, 2, 'Grace', 'Kollie', 'Church Administrator', 'admin@kingscityministries.org', '+231 77 000 0001', '$2y$10$ggOqGdySdQX70v6WmVTjx.UQDvohFDL0CvU.I12tYl3/xF7qNA4Mq', 'active', 1),
 (3, 3, 'Mark', 'Dorbor', 'Prophet', 'pastor@kingscityministries.org', '+231 88 895 1997', '$2y$10$ggOqGdySdQX70v6WmVTjx.UQDvohFDL0CvU.I12tYl3/xF7qNA4Mq', 'active', 1),
 (4, 5, 'Mary', 'Flomo', 'Admin Assistant', 'assistant@kingscityministries.org', NULL, '$2y$10$ggOqGdySdQX70v6WmVTjx.UQDvohFDL0CvU.I12tYl3/xF7qNA4Mq', 'active', 1),
 (5, 6, 'John', 'Doe', 'Youth Ministry Head', 'youth@kingscityministries.org', NULL, '$2y$10$ggOqGdySdQX70v6WmVTjx.UQDvohFDL0CvU.I12tYl3/xF7qNA4Mq', 'active', 1);

-- Example of a direct grant on top of a role: John (Department Head) may also manage the gallery
INSERT INTO user_permissions (user_id, permission_id, granted, granted_by)
 SELECT 5, id, 1, 2 FROM permissions WHERE name IN ('gallery.edit');

-- =====================================================================
--  SEED: DEPARTMENTS / MINISTRIES
-- =====================================================================
INSERT INTO departments (id, name, slug, description, image, icon, head_user_id, head_name, contact_phone, meeting_schedule, sort_order) VALUES
 (1, 'Pastoral Office', 'pastoral-office', 'The office of the Senior Pastor, providing spiritual oversight, counsel and prophetic direction for the whole ministry.', 'assets/images/placeholders/pastor.svg', 'fa-crown', 3, 'Prophet Mark Dorbor', '+231 88 895 1997', 'Office hours: Tue – Fri, 10:00 AM – 3:00 PM', 1),
 (2, 'Sunday Worship', 'sunday-worship', 'Our main gathering where the church comes together in praise, worship, the Word and prophetic ministry.', 'assets/images/placeholders/worship.svg', 'fa-church', NULL, 'Service Coordinator', NULL, 'Sundays, 10:00 AM – 12:30 PM', 2),
 (3, 'Prayer Ministry', 'prayer-ministry', 'Intercessors who stand in the gap for the church, the nation of Liberia and every prayer request we receive.', 'assets/images/placeholders/prayer.svg', 'fa-hands-praying', NULL, 'Prayer Coordinator', NULL, 'Wednesdays, 6:00 PM – 8:00 PM', 3),
 (4, 'Deliverance Ministry', 'deliverance-ministry', 'Ministering freedom, healing and restoration through the power of the Holy Spirit.', 'assets/images/placeholders/conference.svg', 'fa-dove', NULL, 'Deliverance Team Lead', NULL, 'Fridays, 5:00 PM – 8:00 PM', 4),
 (5, 'Youth Ministry', 'youth-ministry', 'Raising a generation of young people who are bold in faith, excellent in character and passionate for God.', 'assets/images/placeholders/youth.svg', 'fa-people-group', 5, 'John Doe', '+231 77 000 0005', 'Saturdays, 3:00 PM – 5:00 PM', 5),
 (6, 'Women''s Ministry', 'womens-ministry', 'Women of faith growing together in the Word, prayer, fellowship and service to the community.', 'assets/images/placeholders/women.svg', 'fa-person-dress', NULL, 'Women''s Ministry Leader', NULL, 'Last Saturday of the month, 10:00 AM', 6),
 (7, 'Men''s Ministry', 'mens-ministry', 'Building godly men who lead their homes, serve the church and impact their communities.', 'assets/images/placeholders/men.svg', 'fa-person', NULL, 'Men''s Ministry Leader', NULL, 'First Saturday of the month, 8:00 AM', 7),
 (8, 'Children Ministry', 'children-ministry', 'Teaching children the love of Jesus through Bible stories, songs and creative learning.', 'assets/images/placeholders/children.svg', 'fa-children', NULL, 'Children''s Church Teacher', NULL, 'Sundays during main service', 8),
 (9, 'Evangelism', 'evangelism', 'Taking the gospel to the streets, markets and communities of Paynesville and beyond.', 'assets/images/placeholders/community.svg', 'fa-bullhorn', NULL, 'Evangelism Coordinator', NULL, 'Saturdays, 9:00 AM – 12:00 PM', 9),
 (10, 'Music & Worship Ministry', 'music-worship-ministry', 'Singers and instrumentalists leading the congregation into the presence of God.', 'assets/images/placeholders/choir.svg', 'fa-music', NULL, 'Music Director', NULL, 'Rehearsals: Thursdays, 5:00 PM', 10),
 (11, 'Media Ministry', 'media-ministry', 'Sound, cameras, live streaming, photography and social media that carry the message further.', 'assets/images/placeholders/sermon.svg', 'fa-video', NULL, 'Media Lead', NULL, 'Saturdays, 1:00 PM', 11);

INSERT INTO department_users (department_id, user_id, is_head) VALUES (1, 3, 1), (5, 5, 1);

-- =====================================================================
--  SEED: CATEGORIES
-- =====================================================================
INSERT INTO sermon_categories (id, name, slug, sort_order) VALUES
 (1,'Prophetic','prophetic',1),(2,'Teaching','teaching',2),(3,'Deliverance','deliverance',3),(4,'Prayer','prayer',4),(5,'Faith','faith',5);

INSERT INTO event_categories (id, name, slug, sort_order) VALUES
 (1,'Worship Service','worship-service',1),(2,'Deliverance Service','deliverance-service',2),(3,'Conference','conference',3),
 (4,'Praise Fest','praise-fest',4),(5,'Crusade','crusade',5),(6,'Youth Program','youth-program',6),(7,'Women''s Program','womens-program',7),
 (8,'Men''s Program','mens-program',8),(9,'Prayer Program','prayer-program',9),(10,'Other','other',10);

INSERT INTO gallery_categories (id, name, slug, sort_order) VALUES
 (1,'Worship Services','worship-services',1),(2,'Conferences','conferences',2),(3,'Praise Fest','praise-fest',3),(4,'Youth','youth',4),
 (5,'Community','community',5),(6,'Outreach','outreach',6),(7,'Special Events','special-events',7),(8,'Pastor''s Ministry','pastors-ministry',8);

-- =====================================================================
--  SEED: SERMONS
-- =====================================================================
INSERT INTO sermons (title, slug, speaker, sermon_date, category_id, media_type, thumbnail, scripture, description, is_featured, allow_download, status, created_by) VALUES
 ('Walking in the Prophetic','walking-in-the-prophetic','Prophet Mark Dorbor','2026-09-21',1,'video','assets/images/placeholders/sermon.svg','1 Corinthians 14:1-3','Prophet Mark Dorbor teaches on hearing the voice of God, the purpose of prophecy in the church, and how every believer can walk in the prophetic with humility and accuracy.',1,1,'published',3),
 ('The Power of Prayer','the-power-of-prayer','Prophet Mark Dorbor','2026-09-14',4,'video','assets/images/placeholders/prayer.svg','James 5:16','Effective, fervent prayer changes situations. A call to rebuild the altar of prayer in our homes and in the nation.',0,1,'published',3),
 ('Faith Over Fear','faith-over-fear','Prophet Mark Dorbor','2026-09-07',5,'audio','assets/images/placeholders/bible.svg','2 Timothy 1:7','God has not given us a spirit of fear. Learn how faith rises when we fix our eyes on His promises.',0,1,'published',3),
 ('The Kingdom Mandate','the-kingdom-mandate','Prophet Mark Dorbor','2026-08-31',2,'video','assets/images/placeholders/worship.svg','Matthew 6:33','Understanding our assignment as citizens of the Kingdom of God and ambassadors of Christ in Liberia.',0,0,'published',3),
 ('Breaking Every Chain','breaking-every-chain','Prophet Mark Dorbor','2026-08-24',3,'video','assets/images/placeholders/conference.svg','Isaiah 10:27','The anointing destroys the yoke. A deliverance message on freedom from generational bondage.',0,1,'published',3),
 ('Grace for the Journey','grace-for-the-journey','Guest Minister','2026-08-17',2,'audio','assets/images/placeholders/bible.svg','2 Corinthians 12:9','His grace is sufficient. Encouragement for every believer walking through a difficult season.',0,1,'published',4);

-- =====================================================================
--  SEED: EVENTS (relative to Sept 2026)
-- =====================================================================
INSERT INTO events (title, slug, category_id, department_id, description, event_date, start_time, end_time, location, image, organizer, status, is_featured, created_by) VALUES
 ('Sunday Worship Service','sunday-worship-service-oct-12',1,2,'Join us for a powerful time of praise, worship and the prophetic Word. Everyone is welcome — bring a friend!','2026-10-12','10:00:00','12:30:00','Omega Community, Paynesville','assets/images/placeholders/worship.svg','King''s City Prophetic Ministries','published',1,2),
 ('Deliverance Service','deliverance-service-oct-18',2,4,'An evening of prayer, deliverance and healing ministry. Come expecting a touch from God.','2026-10-18','17:00:00','20:00:00','Omega Community, Paynesville','assets/images/placeholders/conference.svg','Deliverance Ministry','published',0,2),
 ('Praise Fest 2026','praise-fest-2026',4,10,'Our annual celebration of praise featuring choirs, worship teams and gospel artists from across Monrovia.','2026-11-01','16:00:00','21:00:00','Omega Community, Paynesville','assets/images/placeholders/choir.svg','Music & Worship Ministry','published',1,2),
 ('Youth Conference','youth-conference-2026',6,5,'Three hours of worship, teaching and mentorship for young people aged 13–35. Theme: "Arise and Shine".','2026-11-15','10:00:00','16:00:00','Omega Community, Paynesville','assets/images/placeholders/youth.svg','Youth Ministry','published',0,5),
 ('Women of Virtue Fellowship','women-of-virtue-fellowship',7,6,'A morning of fellowship, prayer and teaching for the women of King''s City.','2026-10-25','10:00:00','13:00:00','Omega Community, Paynesville','assets/images/placeholders/women.svg','Women''s Ministry','published',0,2),
 ('Night of Prayer','night-of-prayer-sept',9,3,'An all-night prayer meeting for families, the church and the nation.','2026-09-05','21:00:00','05:00:00','Omega Community, Paynesville','assets/images/placeholders/prayer.svg','Prayer Ministry','published',0,2),
 ('Community Outreach — Paynesville','community-outreach-paynesville',5,9,'Street evangelism, food distribution and free prayer for our neighbours in Paynesville.','2026-08-22','09:00:00','14:00:00','Paynesville Red Light Market Area','assets/images/placeholders/community.svg','Evangelism','published',0,2);

-- =====================================================================
--  SEED: ANNOUNCEMENTS
-- =====================================================================
INSERT INTO announcements (title, description, department_id, start_date, end_date, status, created_by) VALUES
 ('Praise Fest 2026 Choir Registration','Choirs and worship teams wishing to minister at Praise Fest 2026 should register with the Music Ministry before October 20.',10,'2026-09-20','2026-10-20','published',2),
 ('Midweek Prayer Moved to 6:00 PM','Starting this week, our Wednesday prayer meeting begins at 6:00 PM. Come and pray with us.',3,'2026-09-15','2026-10-31','published',3),
 ('Youth Conference Volunteers Needed','We need ushers, media and hospitality volunteers for the Youth Conference. Speak to the Youth Ministry Head.',5,'2026-09-25','2026-11-14','published',5);

-- =====================================================================
--  SEED: PAGES (editable in Admin → Pages)
-- =====================================================================
INSERT INTO pages (slug, title, content, meta_description) VALUES
 ('our-story','Our Story','<p>King''s City Prophetic Ministries was birthed out of a burden to see lives transformed by the power of God in the heart of Paynesville, Monrovia. What began as a small prayer gathering led by Prophet Mark Dorbor has grown into a vibrant apostolic and prophetic family.</p><p>Today we gather in the Omega Community, behind Conex Gas Station, as a church that believes in the power of prayer, the authority of the Word of God and the present-day ministry of the Holy Spirit. We are committed to reaching people, transforming lives and building faith across Liberia and beyond.</p>','The story of King''s City Prophetic Ministries in Paynesville, Monrovia.'),
 ('vision','Vision','<p>To raise a people of God who hear, believe and walk in the prophetic — a Kingdom community transforming Liberia and the nations.</p>',NULL),
 ('mission','Mission','<p>Reaching people with the gospel of Jesus Christ, transforming lives through the Word and the Spirit, and building faith that stands in every season.</p>',NULL),
 ('statement-of-faith','Statement of Faith','<ul><li>We believe the Bible is the inspired, infallible Word of God.</li><li>We believe in one God, eternally existing as Father, Son and Holy Spirit.</li><li>We believe in the deity, virgin birth, sinless life, atoning death and bodily resurrection of Jesus Christ.</li><li>We believe salvation is by grace through faith in Jesus Christ alone.</li><li>We believe in the baptism of the Holy Spirit and the operation of spiritual gifts today, including prophecy, healing and deliverance.</li><li>We believe in the return of our Lord Jesus Christ.</li></ul>',NULL),
 ('values','Our Values','<ul><li><strong>Prayer</strong> — the foundation of everything we do.</li><li><strong>The Word</strong> — Scripture is our final authority.</li><li><strong>The Spirit</strong> — we welcome His presence and gifts.</li><li><strong>Love</strong> — we are a family that cares for one another.</li><li><strong>Excellence</strong> — we serve God with our very best.</li><li><strong>Community</strong> — we exist to bless Paynesville and Liberia.</li></ul>',NULL),
 ('privacy-policy','Privacy Policy','<p>King''s City Prophetic Ministries respects your privacy. Information you submit through this website (such as prayer requests, testimonies, giving records and contact messages) is used only for ministry purposes and is accessible only to authorised church staff.</p><p>Prayer requests are kept private unless you explicitly choose to share them publicly. We never sell or share your personal information with third parties.</p><p>To request that your information be updated or removed, please contact the church office.</p>','Privacy policy for King''s City Prophetic Ministries.'),
 ('terms-of-use','Terms of Use','<p>By using this website you agree to use it lawfully and respectfully. Sermon media is provided for personal edification; please contact the church before redistributing it.</p><p>Content submitted by visitors (testimonies, prayer requests) may be reviewed and moderated by church staff before publication.</p>','Terms of use for the King''s City Prophetic Ministries website.');

-- =====================================================================
--  SEED: LEADERSHIP, PASTOR, TESTIMONIES, PRAYER
-- =====================================================================
INSERT INTO leaders (name, position, photo, bio, sort_order) VALUES
 ('Prophet Mark Dorbor','Senior Pastor & Founder','assets/images/placeholders/pastor.svg','Founder and Senior Pastor of King''s City Prophetic Ministries.',1),
 ('Grace Kollie','Church Administrator',NULL,'Oversees the day-to-day administration of the church.',2),
 ('John Doe','Youth Ministry Head',NULL,'Leads and mentors the youth of King''s City.',3);

INSERT INTO pastor_profiles (user_id, name, position, photo, short_bio, biography, ministry_journey, vision, message, scripture, scripture_ref, email, phone) VALUES
 (3,'Prophet Mark Dorbor','Senior Pastor','assets/images/placeholders/pastor.svg',
  'Prophet Mark Dorbor is the founder and Senior Pastor of King''s City Prophetic Ministries. He carries a strong apostolic and prophetic mandate to raise, equip and transform lives through the power of God''s Word.',
  'Prophet Mark Dorbor is a Liberian minister of the gospel, called to the prophetic office with a passion for prayer, the Word and the move of the Holy Spirit. Through his teaching and prophetic ministry, many lives have been restored, families strengthened and believers equipped for Kingdom service.\n\nHe is known for his heart for young people, his commitment to the local community in Paynesville, and his conviction that the church must be a place of transformation.',
  'From humble beginnings leading prayer meetings in Paynesville, Prophet Dorbor answered God''s call to establish a house of prayer and prophetic ministry. Over the years he has ministered in crusades, conferences and deliverance services across Monrovia, and continues to mentor leaders who carry the same fire.',
  'Building a people of God who hear, believe and walk in the prophetic.',
  'Beloved, I welcome you to King''s City Prophetic Ministries. Whatever season you are in, God has a plan for your life. Come as you are — there is a place for you in this family, and there is a word from the Lord for you.',
  'For I know the plans I have for you, declares the LORD, plans to prosper you and not to harm you, plans to give you hope and a future.','Jeremiah 29:11',
  'pastor@kingscityministries.org','+231 88 895 1997');

INSERT INTO testimonies (name, title, testimony, permission_to_publish, status, reviewed_by) VALUES
 ('Sis. Comfort T.','Healed After Prayer','For months I suffered with severe headaches. After the deliverance service, the pain left completely and has not returned. All glory to God!',1,'published',2),
 ('Bro. Emmanuel K.','A Job After Two Years','I had been looking for work for two years. Prophet Dorbor prayed with me and within three weeks I received a job offer. God is faithful!',1,'published',2),
 ('Martha S.','Family Restored','My family was divided, but through the prayers of the church we are reconciled and worshipping together again.',1,'pending',NULL);

INSERT INTO prayer_requests (name, email, request, is_public, status, assigned_to) VALUES
 ('Samuel B.','samuel@example.com','Please pray for my mother who is ill in the hospital.',0,'new',NULL),
 ('Anonymous',NULL,'Pray for our nation Liberia — for peace and good leadership.',1,'in_prayer',3),
 ('Ruth D.','ruth@example.com','Pray for my exams next month and for favour with my teachers.',0,'assigned',3);

-- =====================================================================
--  SEED: GIVING
-- =====================================================================
INSERT INTO giving_categories (name, description, icon, sort_order) VALUES
 ('Tithes','Honouring God with the first tenth of our increase.','fa-hand-holding-dollar',1),
 ('Offerings','Freewill offerings for the work of the ministry.','fa-hand-holding-heart',2),
 ('Special Donations','Support for special projects and needs.','fa-gift',3),
 ('Missions','Reaching communities across Liberia and beyond.','fa-earth-africa',4),
 ('Building Fund','Building a permanent house of worship.','fa-building-columns',5);

-- Placeholder methods: EDIT account details in Admin → Giving → Payment Methods before going live.
INSERT INTO giving_methods (name, type, provider, account_name, account_number, instructions, icon, sort_order, is_active) VALUES
 ('Mobile Money','mobile_money','Orange Money / Lonestar MTN MoMo','King''s City Prophetic Ministries','Configure in admin','Send your gift via mobile money, then enter the transaction ID in the form so we can confirm it.','fa-mobile-screen-button',1,1),
 ('Bank Transfer','bank_transfer','Configure bank name in admin','King''s City Prophetic Ministries','Configure in admin','Transfer directly to the church account and include your name and giving type in the reference.','fa-building-columns',2,1),
 ('Give in Person','cash',NULL,NULL,NULL,'Give during any of our services. Ushers will provide an envelope and receipt.','fa-church',3,1);

INSERT INTO giving_transactions (reference, category_id, method_id, amount, currency, donor_name, is_anonymous, status, source, recorded_by, created_at, confirmed_at) VALUES
 ('KC-DEMO-0001',1,1,50.00,'USD','Demo Giver',0,'confirmed','recorded',2,'2026-06-14 11:00:00','2026-06-14 11:00:00'),
 ('KC-DEMO-0002',2,1,25.00,'USD',NULL,1,'confirmed','recorded',2,'2026-07-12 11:00:00','2026-07-12 11:00:00'),
 ('KC-DEMO-0003',5,2,200.00,'USD','Demo Family',0,'confirmed','recorded',2,'2026-08-09 11:00:00','2026-08-09 11:00:00'),
 ('KC-DEMO-0004',1,1,60.00,'USD','Demo Giver',0,'confirmed','recorded',2,'2026-09-13 11:00:00','2026-09-13 11:00:00'),
 ('KC-DEMO-0005',2,1,15.00,'USD','Online Visitor',0,'pending','online',NULL,'2026-09-27 09:30:00',NULL);

-- =====================================================================
--  SEED: GALLERY
-- =====================================================================
INSERT INTO gallery (title, category_id, media_type, file_path, caption, uploaded_by) VALUES
 ('Sunday Worship',1,'image','assets/images/placeholders/worship.svg','Hands lifted in worship',2),
 ('Praise Team',3,'image','assets/images/placeholders/choir.svg','Our praise team ministering',2),
 ('Youth Fellowship',4,'image','assets/images/placeholders/youth.svg','Youth fellowship afternoon',5),
 ('Community Outreach',6,'image','assets/images/placeholders/community.svg','Serving our neighbours in Paynesville',2),
 ('Prayer Night',1,'image','assets/images/placeholders/prayer.svg','Night of prayer',2),
 ('Conference Ministry',2,'image','assets/images/placeholders/conference.svg','Conference session',2),
 ('The Word',8,'image','assets/images/placeholders/bible.svg','Ministering the Word',3),
 ('Women''s Fellowship',5,'image','assets/images/placeholders/women.svg','Women of Virtue fellowship',2);
UPDATE gallery SET department_id = 5 WHERE title = 'Youth Fellowship';
UPDATE gallery SET department_id = 6 WHERE title = 'Women''s Fellowship';

-- Group the sample photos into reference ("event") albums, one per category
INSERT INTO photo_albums (title, album_date, type, category_id, created_by)
  SELECT c.name, '2026-09-20', 'event', c.id, 2 FROM gallery_categories c WHERE c.id IN (SELECT category_id FROM gallery);
UPDATE gallery g JOIN photo_albums a ON a.category_id = g.category_id SET g.album_id = a.id;

-- =====================================================================
--  SEED: SETTINGS
-- =====================================================================
INSERT INTO church_settings (setting_key, setting_value, setting_group) VALUES
 ('church_name','King''s City Prophetic Ministries','church'),
 ('church_short_name','King''s City','church'),
 ('church_tagline','Apostolic & Prophetic Church','church'),
 ('church_motto','Reaching People • Transforming Lives • Building Faith','church'),
 ('phone','+231 88 895 1997','contact'),
 ('email','info@kingscityministries.org','contact'),
 ('whatsapp','231888951997','contact'),
 ('address','Omega Community, behind Conex Gas Station','contact'),
 ('city','Paynesville, Monrovia','contact'),
 ('country','Liberia','contact'),
 ('map_query','Omega Community, Paynesville, Monrovia, Liberia','contact'),
 ('logo','assets/images/logo.jpg','branding'),
 ('favicon','assets/images/logo.jpg','branding'),
 ('footer_text','A Christ-centred apostolic and prophetic church in Paynesville, Monrovia — reaching people, transforming lives and building faith.','branding'),
 ('hero_eyebrow','Welcome to','homepage'),
 ('hero_scripture','For I know the plans I have for you…','homepage'),
 ('hero_scripture_ref','Jeremiah 29:11','homepage'),
 ('welcome_title','A House of Prayer, the Word & the Prophetic','homepage'),
 ('welcome_text','King''s City Prophetic Ministries is an apostolic and prophetic church located in the Omega Community, Paynesville. We are a family of believers passionate about the presence of God, the power of prayer and the transformation of lives through the Word. Whether you are new to faith or have walked with God for years, there is a place for you here.','homepage'),
 ('live_stream_url','','homepage'),
 ('is_live','0','homepage'),
 ('giving_intro','Your generosity helps us reach more lives and build God''s Kingdom.','giving'),
 ('giving_scripture','Each one must give as he has decided in his heart, not reluctantly or under compulsion, for God loves a cheerful giver.','giving'),
 ('giving_scripture_ref','2 Corinthians 9:7','giving'),
 ('giving_currencies','USD,LRD','giving'),
 ('seo_description','King''s City Prophetic Ministries — an Apostolic & Prophetic church in Omega Community, Paynesville, Monrovia, Liberia. Reaching People, Transforming Lives, Building Faith.','seo'),
 ('seo_keywords','church in Paynesville, Monrovia church, prophetic ministry Liberia, King''s City Prophetic Ministries, Prophet Mark Dorbor','seo'),
 ('welcome_image','','homepage'),
 ('giving_image','','homepage'),
 ('og_image','assets/images/placeholders/hero-poster.svg','seo'),
 ('maintenance_mode','0','system'),
 ('session_timeout_minutes','30','system'),
 ('max_login_attempts','5','system'),
 ('max_video_upload_mb','1024','system'),
 ('prayer_wall_enabled','1','system');

INSERT INTO social_links (platform, url, icon, sort_order) VALUES
 ('Facebook','https://facebook.com/','fa-facebook-f',1),
 ('YouTube','https://youtube.com/','fa-youtube',2),
 ('TikTok','https://tiktok.com/','fa-tiktok',3),
 ('Instagram','https://instagram.com/','fa-instagram',4),
 ('WhatsApp','https://wa.me/231888951997','fa-whatsapp',5);

INSERT INTO service_times (name, day_of_week, start_time, end_time, description, sort_order) VALUES
 ('Sunday Worship','Sunday','10:00:00','12:30:00','Praise, worship and the prophetic Word',1),
 ('Midweek Prayer','Wednesday','18:00:00','20:00:00','Corporate prayer and intercession',2),
 ('Deliverance Service','Friday','17:00:00','20:00:00','Healing and deliverance ministry',3);

INSERT INTO hero_videos (title, description, poster, is_active, uploaded_by) VALUES
 ('Default Hero (poster only)','Upload a cinematic MP4/WebM in Admin → Website → Hero Video to replace this.','assets/images/placeholders/hero-poster.svg',1,1);

INSERT INTO activity_logs (user_id, action, module, description, ip_address) VALUES
 (1,'install','system','System installed with development seed data','127.0.0.1');
