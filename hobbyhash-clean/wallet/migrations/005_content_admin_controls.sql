-- HOBC Public Content Admin Controls Migration
-- Adds admin-managed content tables/fields while preserving existing hardcoded public pages.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS docs_pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    category VARCHAR(120) NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    seo_title VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_docs_pages_slug (slug),
    KEY idx_docs_pages_status_order (status, sort_order),
    KEY idx_docs_pages_category (category),
    CONSTRAINT fk_docs_pages_created_by
        FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_docs_pages_updated_by
        FOREIGN KEY (updated_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE announcements
    MODIFY COLUMN status ENUM('draft','published','archived','unpublished') NOT NULL DEFAULT 'draft';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'show_on_homepage') = 0,
    'ALTER TABLE announcements ADD COLUMN show_on_homepage TINYINT(1) NOT NULL DEFAULT 0 AFTER pinned',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'show_in_wallet_dashboard') = 0,
    'ALTER TABLE announcements ADD COLUMN show_in_wallet_dashboard TINYINT(1) NOT NULL DEFAULT 0 AFTER show_on_homepage',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'seo_title') = 0,
    'ALTER TABLE announcements ADD COLUMN seo_title VARCHAR(190) NULL AFTER body',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'seo_description') = 0,
    'ALTER TABLE announcements ADD COLUMN seo_description VARCHAR(320) NULL AFTER seo_title',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads' AND COLUMN_NAME = 'description') = 0,
    'ALTER TABLE downloads ADD COLUMN description TEXT NULL AFTER title',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads' AND COLUMN_NAME = 'is_recommended') = 0,
    'ALTER TABLE downloads ADD COLUMN is_recommended TINYINT(1) NOT NULL DEFAULT 0 AFTER download_count',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads' AND COLUMN_NAME = 'is_deprecated') = 0,
    'ALTER TABLE downloads ADD COLUMN is_deprecated TINYINT(1) NOT NULL DEFAULT 0 AFTER is_recommended',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads' AND COLUMN_NAME = 'sort_order') = 0,
    'ALTER TABLE downloads ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_deprecated',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE burn_events
    MODIFY COLUMN status ENUM('draft','planned','pending','completed','confirmed','cancelled','rejected','archived') NOT NULL DEFAULT 'pending';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'burn_events' AND COLUMN_NAME = 'is_published') = 0,
    'ALTER TABLE burn_events ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'burn_events' AND COLUMN_NAME = 'public_notes') = 0,
    'ALTER TABLE burn_events ADD COLUMN public_notes TEXT NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE treasury_reserve_categories
    MODIFY COLUMN status ENUM('pending_launch','active','paused','completed','inactive','archived') NOT NULL DEFAULT 'active';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'treasury_reserve_categories' AND COLUMN_NAME = 'is_public') = 0,
    'ALTER TABLE treasury_reserve_categories ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE treasury_reserve_movements
    MODIFY COLUMN status ENUM('draft','pending','completed','confirmed','cancelled','rejected','archived') NOT NULL DEFAULT 'pending';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'treasury_reserve_movements' AND COLUMN_NAME = 'is_public') = 0,
    'ALTER TABLE treasury_reserve_movements ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE support_messages
    MODIFY COLUMN status ENUM('new','open','waiting','waiting_user','waiting_admin','closed','spam','archived') NOT NULL DEFAULT 'new';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_messages' AND COLUMN_NAME = 'is_read') = 0,
    'ALTER TABLE support_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_messages' AND COLUMN_NAME = 'is_spam') = 0,
    'ALTER TABLE support_messages ADD COLUMN is_spam TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO schema_migrations (migration)
VALUES ('005_content_admin_controls')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
