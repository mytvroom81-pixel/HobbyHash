-- Add searchable indexes for the admin audit log without changing existing rows.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND INDEX_NAME = 'idx_admin_audit_action') = 0,
    'ALTER TABLE admin_audit_log ADD INDEX idx_admin_audit_action (action)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND INDEX_NAME = 'idx_admin_audit_target_type') = 0,
    'ALTER TABLE admin_audit_log ADD INDEX idx_admin_audit_target_type (target_type)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log' AND INDEX_NAME = 'idx_admin_audit_target') = 0,
    'ALTER TABLE admin_audit_log ADD INDEX idx_admin_audit_target (target_type, target_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
