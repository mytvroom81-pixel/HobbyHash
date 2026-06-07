-- HOBC Bot Management Migration
-- Adds admin-managed bot notes and safe app-level allow/block rules.
-- No firewall or server-level blocking is performed by this schema.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bot_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_type ENUM('allow','block') NOT NULL,
    target_type ENUM('user_agent','ip_hash','bot_name') NOT NULL,
    pattern VARCHAR(512) NOT NULL,
    match_type ENUM('contains','exact','regex') NOT NULL DEFAULT 'contains',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    threat_level ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
    notes TEXT NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bot_rules_rule_type (rule_type),
    KEY idx_bot_rules_target_type (target_type),
    KEY idx_bot_rules_status (status),
    KEY idx_bot_rules_created_at (created_at),
    CONSTRAINT fk_bot_rules_created_by_admin
        FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_type ENUM('user_agent','ip_hash','bot_name') NOT NULL,
    subject_value VARCHAR(512) NOT NULL,
    classification ENUM('unknown','harmless','suspicious') NOT NULL DEFAULT 'unknown',
    notes TEXT NULL,
    updated_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bot_notes_subject (subject_type, subject_value),
    KEY idx_bot_notes_classification (classification),
    KEY idx_bot_notes_updated_at (updated_at),
    CONSTRAINT fk_bot_notes_updated_by_admin
        FOREIGN KEY (updated_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_rule_hits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id BIGINT UNSIGNED NULL,
    rule_type ENUM('allow','block') NOT NULL,
    target_type ENUM('user_agent','ip_hash','bot_name') NOT NULL,
    pattern VARCHAR(512) NOT NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    url TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bot_rule_hits_rule_id (rule_id),
    KEY idx_bot_rule_hits_rule_type (rule_type),
    KEY idx_bot_rule_hits_created_at (created_at),
    KEY idx_bot_rule_hits_ip_hash (ip_hash),
    CONSTRAINT fk_bot_rule_hits_rule
        FOREIGN KEY (rule_id) REFERENCES bot_rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration)
VALUES ('002_bot_management')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
