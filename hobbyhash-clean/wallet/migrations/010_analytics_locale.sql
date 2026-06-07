SET @has_locale := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'site_pageviews'
      AND COLUMN_NAME = 'locale'
) = 0;

SET @sql := IF(
    @has_locale,
    'ALTER TABLE site_pageviews ADD COLUMN locale VARCHAR(16) NULL AFTER utm_campaign, ADD INDEX idx_site_pageviews_locale (locale)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
