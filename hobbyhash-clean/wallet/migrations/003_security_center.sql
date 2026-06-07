-- HOBC Security Center Migration
-- Adds safe admin session tracking, security watchlists, and default security settings.
-- This migration does not remove users or change existing passwords.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admin_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    session_id_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_by_admin_id BIGINT UNSIGNED NULL,
    revoke_reason VARCHAR(190) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_sessions_session_hash (session_id_hash),
    KEY idx_admin_sessions_admin_user_id (admin_user_id),
    KEY idx_admin_sessions_last_seen_at (last_seen_at),
    KEY idx_admin_sessions_expires_at (expires_at),
    KEY idx_admin_sessions_revoked_at (revoked_at),
    CONSTRAINT fk_admin_sessions_admin
        FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_sessions_revoked_by
        FOREIGN KEY (revoked_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_watchlist (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('ip_hash','user_agent') NOT NULL,
    pattern VARCHAR(512) NOT NULL,
    match_type ENUM('contains','exact','regex') NOT NULL DEFAULT 'contains',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
    notes TEXT NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_security_watchlist_target_type (target_type),
    KEY idx_security_watchlist_status (status),
    KEY idx_security_watchlist_severity (severity),
    CONSTRAINT fk_security_watchlist_created_by
        FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_settings (setting_key, setting_value, setting_type)
VALUES
    ('security.admin_failed_login_threshold', '6', 'integer'),
    ('security.admin_lockout_seconds', '900', 'integer'),
    ('security.registration_enabled', '1', 'boolean'),
    ('security.wallet_signups_enabled', '1', 'boolean'),
    ('security.notice_banner', '', 'text')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO schema_migrations (migration)
VALUES ('003_security_center')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
