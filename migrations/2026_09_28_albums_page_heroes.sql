-- =====================================================================
--  Upgrade for an EXISTING install (fresh installs: just import database.sql)
--   * Photo albums (bulk uploads, dated by day or month, downloads)
--   * Per-page hero videos
--   * Default logo = church badge
--  Run once:  mysql -u root kings_city < migrations/2026_09_28_albums_page_heroes.sql
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE photo_albums (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(200) NULL COMMENT 'Optional — the date is shown when empty',
  album_date     DATE NOT NULL,
  date_precision ENUM('day','month') NOT NULL DEFAULT 'day',
  type           ENUM('downloads','event') NOT NULL DEFAULT 'downloads' COMMENT 'downloads = members find & download their photos; event = reference gallery',
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

ALTER TABLE gallery
  MODIFY title VARCHAR(200) NULL,
  ADD COLUMN album_id INT UNSIGNED NULL AFTER id,
  ADD COLUMN thumb_path VARCHAR(255) NULL AFTER file_path,
  ADD COLUMN width SMALLINT UNSIGNED NULL AFTER thumb_path,
  ADD COLUMN height SMALLINT UNSIGNED NULL AFTER width,
  ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_published,
  ADD CONSTRAINT fk_gallery_album FOREIGN KEY (album_id) REFERENCES photo_albums(id) ON DELETE CASCADE,
  ADD INDEX idx_gallery_album (album_id, id);

-- Existing loose photos become "event" albums, one per category
INSERT INTO photo_albums (title, album_date, type, category_id, created_by)
  SELECT COALESCE(c.name, 'Church Moments'), MIN(DATE(g.created_at)), 'event', g.category_id, MIN(g.uploaded_by)
    FROM gallery g LEFT JOIN gallery_categories c ON c.id = g.category_id
   WHERE g.album_id IS NULL GROUP BY g.category_id, c.name;
UPDATE gallery g JOIN photo_albums a ON a.type = 'event' AND (a.category_id <=> g.category_id)
   SET g.album_id = a.id WHERE g.album_id IS NULL;

ALTER TABLE hero_videos
  ADD COLUMN page VARCHAR(40) NOT NULL DEFAULT 'home' AFTER id,
  ADD INDEX idx_hero_page (page, is_active);

UPDATE church_settings SET setting_value = '1024' WHERE setting_key = 'max_video_upload_mb' AND CAST(setting_value AS UNSIGNED) < 1024;
UPDATE church_settings SET setting_value = 'assets/images/logo.jpg' WHERE setting_key IN ('logo', 'favicon') AND setting_value LIKE 'assets/images/%.svg';
