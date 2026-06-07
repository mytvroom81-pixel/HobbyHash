-- HOBC Admin Roles and Permissions Migration
-- Adds role support without changing existing admin login credentials.
-- Existing admin accounts become Super Admin automatically.

SET NAMES utf8mb4;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'role') = 0,
    "ALTER TABLE admin_users ADD COLUMN role ENUM('super_admin','site_admin','content_manager','support_manager','wallet_manager','pool_manager','analytics_viewer','read_only') NOT NULL DEFAULT 'super_admin' AFTER email",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE admin_users
SET role = 'super_admin'
WHERE role IS NULL OR role = '';

INSERT INTO schema_migrations (migration)
VALUES ('006_admin_roles_permissions')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
