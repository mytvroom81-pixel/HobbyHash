-- GeoIP enrichment columns for analytics (city, region, ASN).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_visitors' AND COLUMN_NAME = 'city_name') = 0,
    'ALTER TABLE site_visitors ADD COLUMN city_name VARCHAR(120) NULL AFTER country_code, ADD COLUMN region_name VARCHAR(120) NULL AFTER city_name, ADD COLUMN asn_number INT UNSIGNED NULL AFTER region_name, ADD COLUMN asn_org VARCHAR(255) NULL AFTER asn_number, ADD INDEX idx_site_visitors_city (city_name), ADD INDEX idx_site_visitors_asn (asn_number)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_pageviews' AND COLUMN_NAME = 'city_name') = 0,
    'ALTER TABLE site_pageviews ADD COLUMN city_name VARCHAR(120) NULL AFTER country_code, ADD COLUMN region_name VARCHAR(120) NULL AFTER city_name, ADD COLUMN asn_number INT UNSIGNED NULL AFTER region_name, ADD COLUMN asn_org VARCHAR(255) NULL AFTER asn_number, ADD INDEX idx_site_pageviews_city (city_name), ADD INDEX idx_site_pageviews_asn (asn_number)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bot_events' AND COLUMN_NAME = 'city_name') = 0,
    'ALTER TABLE bot_events ADD COLUMN city_name VARCHAR(120) NULL AFTER ip_address, ADD COLUMN region_name VARCHAR(120) NULL AFTER city_name, ADD COLUMN country_code CHAR(2) NULL AFTER region_name, ADD COLUMN asn_number INT UNSIGNED NULL AFTER country_code, ADD COLUMN asn_org VARCHAR(255) NULL AFTER asn_number, ADD INDEX idx_bot_events_city (city_name), ADD INDEX idx_bot_events_asn (asn_number)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
