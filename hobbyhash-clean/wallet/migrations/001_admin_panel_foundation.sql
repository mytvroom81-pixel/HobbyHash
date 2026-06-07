-- HOBC Admin Panel Foundation Migration
-- Safe to run on the production MySQL/MariaDB wallet database.
-- This migration creates missing admin panel tables and extends equivalent
-- existing tables without dropping or erasing existing data.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(190) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing equivalent table: admin_audit_log.
-- Keep current data and add the new admin-panel fields.
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND COLUMN_NAME = 'entity_type') = 0,
    'ALTER TABLE admin_audit_log ADD COLUMN entity_type VARCHAR(64) NULL AFTER action',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND COLUMN_NAME = 'entity_id') = 0,
    'ALTER TABLE admin_audit_log ADD COLUMN entity_id VARCHAR(128) NULL AFTER entity_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND COLUMN_NAME = 'ip_hash') = 0,
    'ALTER TABLE admin_audit_log ADD COLUMN ip_hash CHAR(64) NULL AFTER entity_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND COLUMN_NAME = 'metadata_json') = 0,
    'ALTER TABLE admin_audit_log ADD COLUMN metadata_json JSON NULL AFTER ip_hash',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE admin_audit_log
SET entity_type = COALESCE(entity_type, target_type),
    entity_id = COALESCE(entity_id, target_id),
    metadata_json = COALESCE(metadata_json, details_json)
WHERE entity_type IS NULL
   OR entity_id IS NULL
   OR metadata_json IS NULL;

CREATE OR REPLACE VIEW admin_audit_logs AS
SELECT
    id,
    admin_user_id,
    action,
    COALESCE(entity_type, target_type) AS entity_type,
    COALESCE(entity_id, target_id) AS entity_id,
    ip_hash,
    user_agent,
    COALESCE(metadata_json, details_json) AS metadata_json,
    created_at
FROM admin_audit_log;

CREATE TABLE IF NOT EXISTS site_visitors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id VARCHAR(128) NOT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    first_referrer TEXT NULL,
    last_referrer TEXT NULL,
    pageview_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    bot_name VARCHAR(120) NULL,
    country_code CHAR(2) NULL,
    device_type VARCHAR(40) NULL,
    browser_name VARCHAR(80) NULL,
    os_name VARCHAR(80) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_visitors_visitor_id (visitor_id),
    KEY idx_site_visitors_first_seen (first_seen_at),
    KEY idx_site_visitors_last_seen (last_seen_at),
    KEY idx_site_visitors_is_bot (is_bot),
    KEY idx_site_visitors_bot_name (bot_name),
    KEY idx_site_visitors_country_code (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_pageviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(128) NULL,
    visitor_id VARCHAR(128) NULL,
    url TEXT NOT NULL,
    route_name VARCHAR(190) NULL,
    page_title VARCHAR(255) NULL,
    referrer TEXT NULL,
    utm_source VARCHAR(190) NULL,
    utm_medium VARCHAR(190) NULL,
    utm_campaign VARCHAR(190) NULL,
    device_type VARCHAR(40) NULL,
    browser_name VARCHAR(80) NULL,
    os_name VARCHAR(80) NULL,
    country_code CHAR(2) NULL,
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    bot_name VARCHAR(120) NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    response_status SMALLINT UNSIGNED NULL,
    load_time_ms INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_pageviews_created_at (created_at),
    KEY idx_site_pageviews_session_id (session_id),
    KEY idx_site_pageviews_visitor_id (visitor_id),
    KEY idx_site_pageviews_is_bot (is_bot),
    KEY idx_site_pageviews_bot_name (bot_name),
    KEY idx_site_pageviews_route_name (route_name),
    KEY idx_site_pageviews_url (url(191)),
    KEY idx_site_pageviews_status (response_status),
    KEY idx_site_pageviews_utm_source (utm_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bot_name VARCHAR(120) NULL,
    bot_type VARCHAR(80) NULL,
    user_agent TEXT NULL,
    ip_hash CHAR(64) NULL,
    url TEXT NULL,
    referrer TEXT NULL,
    event_type VARCHAR(80) NOT NULL,
    threat_level ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'info',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bot_events_created_at (created_at),
    KEY idx_bot_events_bot_name (bot_name),
    KEY idx_bot_events_event_type (event_type),
    KEY idx_bot_events_threat_level (threat_level),
    KEY idx_bot_events_url (url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_security_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(80) NOT NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    username_attempted VARCHAR(190) NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    details_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_security_events_created_at (created_at),
    KEY idx_admin_security_events_admin_user_id (admin_user_id),
    KEY idx_admin_security_events_event_type (event_type),
    KEY idx_admin_security_events_username_attempted (username_attempted),
    CONSTRAINT fk_admin_security_events_admin
        FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_errors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    level ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'error',
    message TEXT NOT NULL,
    file_path VARCHAR(512) NULL,
    line_number INT UNSIGNED NULL,
    url TEXT NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    context_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_errors_created_at (created_at),
    KEY idx_site_errors_level (level),
    KEY idx_site_errors_url (url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_health_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    php_version VARCHAR(80) NULL,
    mysql_version VARCHAR(120) NULL,
    disk_free_bytes BIGINT UNSIGNED NULL,
    disk_total_bytes BIGINT UNSIGNED NULL,
    memory_usage_bytes BIGINT UNSIGNED NULL,
    load_average VARCHAR(190) NULL,
    queue_status VARCHAR(80) NULL,
    cron_status VARCHAR(80) NULL,
    node_status_json JSON NULL,
    pool_status_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_system_health_snapshots_created_at (created_at),
    KEY idx_system_health_snapshots_queue_status (queue_status),
    KEY idx_system_health_snapshots_cron_status (cron_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(190) NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string','integer','decimal','boolean','json','text') NOT NULL DEFAULT 'string',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_settings_key (setting_key),
    KEY idx_admin_settings_updated_by (updated_by),
    KEY idx_admin_settings_updated_at (updated_at),
    CONSTRAINT fk_admin_settings_updated_by
        FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_announcements_slug (slug),
    KEY idx_announcements_status (status),
    KEY idx_announcements_pinned (pinned),
    KEY idx_announcements_published_at (published_at),
    KEY idx_announcements_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS burn_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    amount DECIMAL(24,8) NOT NULL DEFAULT 0.00000000,
    txid VARCHAR(128) NULL,
    burn_address VARCHAR(128) NULL,
    proof_url TEXT NULL,
    status ENUM('draft','pending','confirmed','rejected','archived') NOT NULL DEFAULT 'pending',
    event_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_burn_events_status (status),
    KEY idx_burn_events_created_at (created_at),
    KEY idx_burn_events_event_date (event_date),
    KEY idx_burn_events_txid (txid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS treasury_reserve_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    percentage DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_treasury_reserve_categories_slug (slug),
    KEY idx_treasury_reserve_categories_status (status),
    KEY idx_treasury_reserve_categories_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS treasury_reserve_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NULL,
    amount DECIMAL(24,8) NOT NULL DEFAULT 0.00000000,
    txid VARCHAR(128) NULL,
    movement_type ENUM('allocation','incoming','outgoing','adjustment') NOT NULL DEFAULT 'outgoing',
    status ENUM('draft','pending','confirmed','rejected','archived') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_treasury_reserve_movements_category_id (category_id),
    KEY idx_treasury_reserve_movements_status (status),
    KEY idx_treasury_reserve_movements_created_at (created_at),
    KEY idx_treasury_reserve_movements_txid (txid),
    CONSTRAINT fk_treasury_reserve_movements_category
        FOREIGN KEY (category_id) REFERENCES treasury_reserve_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS downloads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    platform VARCHAR(80) NOT NULL,
    file_url TEXT NOT NULL,
    version VARCHAR(80) NULL,
    checksum_sha256 CHAR(64) NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_downloads_platform (platform),
    KEY idx_downloads_status (status),
    KEY idx_downloads_created_at (created_at),
    KEY idx_downloads_file_url (file_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS download_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    download_id BIGINT UNSIGNED NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    referrer TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_download_events_download_id (download_id),
    KEY idx_download_events_created_at (created_at),
    CONSTRAINT fk_download_events_download
        FOREIGN KEY (download_id) REFERENCES downloads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    message MEDIUMTEXT NOT NULL,
    status ENUM('new','open','waiting_user','waiting_admin','closed','archived') NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_messages_status (status),
    KEY idx_support_messages_created_at (created_at),
    KEY idx_support_messages_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash CHAR(64) NULL,
    route VARCHAR(190) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    count_value INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_limit_events_created_at (created_at),
    KEY idx_rate_limit_events_route (route),
    KEY idx_rate_limit_events_event_type (event_type),
    KEY idx_rate_limit_events_ip_hash (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration)
VALUES ('001_admin_panel_foundation')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
