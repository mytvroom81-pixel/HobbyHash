-- Add raw IP columns for admin-only analytics reporting.
-- Existing hashed columns remain in place for bot rules and backwards compatibility.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_pageviews' AND COLUMN_NAME = 'ip_address') = 0,
    'ALTER TABLE site_pageviews ADD COLUMN ip_address VARCHAR(64) NULL AFTER ip_hash, ADD INDEX idx_site_pageviews_ip_address (ip_address)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_visitors' AND COLUMN_NAME = 'ip_address') = 0,
    'ALTER TABLE site_visitors ADD COLUMN ip_address VARCHAR(64) NULL AFTER visitor_id, ADD INDEX idx_site_visitors_ip_address (ip_address)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bot_events' AND COLUMN_NAME = 'ip_address') = 0,
    'ALTER TABLE bot_events ADD COLUMN ip_address VARCHAR(64) NULL AFTER ip_hash, ADD INDEX idx_bot_events_ip_address (ip_address)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'download_events' AND COLUMN_NAME = 'ip_address') = 0,
    'ALTER TABLE download_events ADD COLUMN ip_address VARCHAR(64) NULL AFTER ip_hash, ADD INDEX idx_download_events_ip_address (ip_address)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
