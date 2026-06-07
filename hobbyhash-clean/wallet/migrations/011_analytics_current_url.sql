SET @has_current_url := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'site_visitors'
      AND COLUMN_NAME = 'current_url'
) = 0;

SET @sql := IF(
    @has_current_url,
    'ALTER TABLE site_visitors
        ADD COLUMN current_url VARCHAR(2000) NULL AFTER last_referrer,
        ADD COLUMN current_route_name VARCHAR(190) NULL AFTER current_url,
        ADD INDEX idx_site_visitors_current_route (current_route_name)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
