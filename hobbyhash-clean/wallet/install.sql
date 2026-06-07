-- HOBC Web Wallet MVP schema (MariaDB/MySQL)
-- Database: hobbyhash_wallet

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(40) NOT NULL,
    email VARCHAR(320) NOT NULL,
    email_verified_at DATETIME NULL,
    phone_number VARCHAR(32) NULL,
    phone_verified_at DATETIME NULL,
    sms_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pending_registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(40) NOT NULL,
    email VARCHAR(320) NOT NULL,
    phone_number VARCHAR(32) NULL,
    password_hash VARCHAR(255) NOT NULL,
    verification_method ENUM('sms','email') NOT NULL,
    sms_challenge_id BIGINT UNSIGNED NULL,
    email_code_hash VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    sms_verified_at DATETIME NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    request_ip VARCHAR(64) NULL,
    request_user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pending_registrations_email (email),
    KEY idx_pending_registrations_phone (phone_number),
    KEY idx_pending_registrations_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_recovery_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    verification_method ENUM('sms','email') NOT NULL,
    sms_challenge_id BIGINT UNSIGNED NULL,
    email_code_hash VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    sms_verified_at DATETIME NULL,
    totp_required TINYINT(1) NOT NULL DEFAULT 0,
    totp_verified_at DATETIME NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    request_ip VARCHAR(64) NULL,
    request_user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recovery_user (user_id),
    KEY idx_recovery_expires (expires_at),
    CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_security (
    user_id BIGINT UNSIGNED NOT NULL,
    twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    twofa_secret_encrypted TEXT NULL,
    failed_login_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_security_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    php_session_id VARCHAR(128) NOT NULL,
    csrf_token_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(512) NULL,
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_php_session_id (php_session_id),
    KEY idx_sessions_user_id (user_id),
    KEY idx_sessions_expires_at (expires_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deposit_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    address VARCHAR(128) NOT NULL,
    label VARCHAR(128) NULL,
    address_role ENUM('main','sub') NOT NULL DEFAULT 'sub',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    archived_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_deposit_addresses_address (address),
    KEY idx_deposit_addresses_user_id (user_id),
    KEY idx_deposit_addresses_user_role (user_id, address_role),
    CONSTRAINT fk_deposit_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deposits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    deposit_address_id BIGINT UNSIGNED NOT NULL,
    txid VARCHAR(128) NOT NULL,
    vout INT NOT NULL,
    amount DECIMAL(24,8) NOT NULL,
    block_hash VARCHAR(128) NULL,
    block_height BIGINT NULL,
    confirmations INT NOT NULL DEFAULT 0,
    status ENUM('detected','confirming','credited','orphaned') NOT NULL DEFAULT 'detected',
    credit_behavior ENUM('external','internal_withdrawal') NOT NULL DEFAULT 'external',
    credited_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_deposits_txid_vout (txid, vout),
    KEY idx_deposits_user_id (user_id),
    KEY idx_deposits_status (status),
    KEY idx_deposits_credit_behavior (credit_behavior),
    CONSTRAINT fk_deposits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_deposits_address FOREIGN KEY (deposit_address_id) REFERENCES deposit_addresses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    requested_address VARCHAR(128) NOT NULL,
    requested_amount DECIMAL(24,8) NOT NULL,
    fee_amount DECIMAL(24,8) NOT NULL DEFAULT 0,
    status ENUM('pending','awaiting_approval','approved','broadcasted','confirming','confirmed','failed','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requires_admin_approval TINYINT(1) NOT NULL DEFAULT 0,
    txid VARCHAR(128) NULL,
    chain_confirmations INT NOT NULL DEFAULT 0,
    confirmed_at DATETIME NULL,
    failure_reason VARCHAR(255) NULL,
    approved_by_admin_id BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_by_admin_id BIGINT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    request_ip VARCHAR(64) NULL,
    request_user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_withdrawals_user_id (user_id),
    KEY idx_withdrawals_status (status),
    CONSTRAINT fk_withdrawals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    entry_type ENUM('deposit_credit','withdraw_debit','withdraw_fee','adjustment','reorg_reversal','refund_credit') NOT NULL,
    amount DECIMAL(24,8) NOT NULL,
    reference_type VARCHAR(64) NOT NULL,
    reference_id BIGINT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    actor_type ENUM('system','scanner','withdrawal_worker','admin','user') NOT NULL DEFAULT 'system',
    actor_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ledger_user_id (user_id),
    KEY idx_ledger_ref (reference_type, reference_id),
    CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_ledger_entries_no_update;
CREATE TRIGGER trg_ledger_entries_no_update
BEFORE UPDATE ON ledger_entries
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries are immutable';

DROP TRIGGER IF EXISTS trg_ledger_entries_no_delete;
CREATE TRIGGER trg_ledger_entries_no_delete
BEFORE DELETE ON ledger_entries
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries are immutable';

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(40) NOT NULL,
    email VARCHAR(320) NOT NULL,
    phone_number VARCHAR(32) NULL,
    sms_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    totp_secret VARCHAR(64) NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username),
    UNIQUE KEY uq_admin_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(128) NOT NULL,
    target_type VARCHAR(64) NULL,
    target_id VARCHAR(128) NULL,
    details_json TEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_audit_admin_user_id (admin_user_id),
    KEY idx_admin_audit_created_at (created_at),
    CONSTRAINT fk_admin_audit_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_type ENUM('user','admin') NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    phone_number VARCHAR(32) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    request_ip VARCHAR(64) NULL,
    request_user_agent VARCHAR(512) NULL,
    provider_message_id VARCHAR(128) NULL,
    send_status VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sms_challenges_subject (subject_type, subject_id, purpose),
    KEY idx_sms_challenges_expires (expires_at),
    KEY idx_sms_challenges_consumed (consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faq_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_faq_items_question (question),
    KEY idx_faq_items_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    public_token CHAR(64) NOT NULL,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    status ENUM('open','waiting_user','waiting_admin','closed') NOT NULL DEFAULT 'open',
    source ENUM('public','wallet') NOT NULL DEFAULT 'public',
    source_context VARCHAR(190) NULL,
    created_ip VARCHAR(64) NULL,
    created_user_agent VARCHAR(512) NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_tickets_token (public_token),
    KEY idx_support_tickets_user_id (user_id),
    KEY idx_support_tickets_status (status),
    CONSTRAINT fk_support_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('user','guest','admin','system') NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    sender_admin_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_ticket_messages_ticket_id (ticket_id),
    CONSTRAINT fk_support_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_ticket_messages_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_support_ticket_messages_admin FOREIGN KEY (sender_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS smtp_settings (
    id TINYINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    host VARCHAR(190) NULL,
    port INT NOT NULL DEFAULT 587,
    username VARCHAR(190) NULL,
    password_enc TEXT NULL,
    encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    from_email VARCHAR(190) NULL,
    from_name VARCHAR(120) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO smtp_settings (id, from_name)
VALUES (1, 'HobbyHashCoin Support')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO faq_items (question, answer, sort_order, is_active)
VALUES
('How do receive wallets work?', 'Receive wallets are named labels for addresses. Your spendable balance is one total wallet balance, and receive wallets help you organize where deposits came from.', 10, 1),
('Why does a deposit need confirmations?', 'Confirmations protect your wallet from chain reorganizations. Deposits become credited after the confirmation requirement set by the wallet admin.', 20, 1),
('Can I send coins to myself?', 'The wallet blocks withdrawals to your own receive addresses to avoid confusing self-transfers. Your balance is already available in the dashboard.', 30, 1),
('How do I track a support ticket?', 'Public contact tickets create a tracking link. Logged-in users can also see their ticket links from the Support page.', 40, 1)
ON DUPLICATE KEY UPDATE question = question;

CREATE TABLE IF NOT EXISTS wallet_settings (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
    deposits_paused TINYINT(1) NOT NULL DEFAULT 0,
    withdrawals_paused TINYINT(1) NOT NULL DEFAULT 0,
    scanner_paused TINYINT(1) NOT NULL DEFAULT 0,
    admin_sms_2fa_required TINYINT(1) NOT NULL DEFAULT 1,
    wallet_sms_registration_required TINYINT(1) NOT NULL DEFAULT 1,
    wallet_sms_login_required TINYINT(1) NOT NULL DEFAULT 1,
    wallet_sms_withdrawal_required TINYINT(1) NOT NULL DEFAULT 1,
    sms_provider_mode VARCHAR(32) NOT NULL DEFAULT 'manual',
    twilio_verify_service_sid VARCHAR(64) NULL,
    admin_totp_required TINYINT(1) NOT NULL DEFAULT 0,
    wallet_totp_login_required TINYINT(1) NOT NULL DEFAULT 1,
    wallet_totp_withdrawal_required TINYINT(1) NOT NULL DEFAULT 1,
    deposit_confirmations_required INT NOT NULL DEFAULT 6,
    withdrawal_confirmations_required INT NOT NULL DEFAULT 1,
    admin_approval_threshold DECIMAL(24,8) NOT NULL DEFAULT 5000.00000000,
    daily_hot_wallet_broadcast_limit DECIMAL(24,8) NOT NULL DEFAULT 2000000.00000000,
    per_withdrawal_min_amount DECIMAL(24,8) NOT NULL DEFAULT 0.00000001,
    per_withdrawal_max_amount DECIMAL(24,8) NOT NULL DEFAULT 50000.00000000,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO wallet_settings (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

CREATE TABLE IF NOT EXISTS site_settings (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    site_mode ENUM('pre_launch','maintenance','full_launch') NOT NULL DEFAULT 'full_launch',
    bypass_ip VARCHAR(64) NULL,
    pre_launch_title VARCHAR(190) NOT NULL DEFAULT 'HOBC is getting ready to launch',
    pre_launch_message TEXT NULL,
    pre_launch_eta VARCHAR(190) NULL,
    maintenance_title VARCHAR(190) NOT NULL DEFAULT 'HOBC is under maintenance',
    maintenance_message TEXT NULL,
    maintenance_start_at VARCHAR(190) NULL,
    maintenance_end_at VARCHAR(190) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings
    (id, site_mode, pre_launch_message, maintenance_message)
VALUES
    (1, 'full_launch',
     'The HobbyHash Coin command center is being prepared. Coming soon: home solo mining guides, main and nano solo pools, explorer, stats, downloads, launch reserve transparency, burn tracking, docs, and custodial wallet access with clear risk notices.',
     'The HOBC website is temporarily unavailable while maintenance is completed.')
ON DUPLICATE KEY UPDATE id = VALUES(id);

CREATE TABLE IF NOT EXISTS wallet_hot_balance_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trusted_balance DECIMAL(24,8) NOT NULL DEFAULT 0,
    untrusted_pending DECIMAL(24,8) NOT NULL DEFAULT 0,
    immature_balance DECIMAL(24,8) NOT NULL DEFAULT 0,
    liabilities_total DECIMAL(24,8) NOT NULL DEFAULT 0,
    delta_hot_minus_liabilities DECIMAL(24,8) NOT NULL DEFAULT 0,
    warning_flag TINYINT(1) NOT NULL DEFAULT 0,
    block_height BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wallet_hot_snapshots_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chain_scan_state (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    last_scanned_height BIGINT NOT NULL DEFAULT 0,
    last_scanned_blockhash VARCHAR(128) NULL,
    scanner_status ENUM('ok','offline','error','paused') NOT NULL DEFAULT 'ok',
    rpc_status ENUM('ok','offline','error') NOT NULL DEFAULT 'ok',
    scanner_last_error VARCHAR(255) NULL,
    rpc_last_error VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chain_scan_state (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

CREATE TABLE IF NOT EXISTS security_event_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    details_json TEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(512) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_security_events_user_id (user_id),
    KEY idx_security_events_created_at (created_at),
    CONSTRAINT fk_security_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(64) NOT NULL,
    bucket_key VARCHAR(128) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_limits_bucket_key (bucket, bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reconciliation_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    liabilities_total DECIMAL(24,8) NOT NULL,
    trusted_balance DECIMAL(24,8) NOT NULL,
    delta_hot_minus_liabilities DECIMAL(24,8) NOT NULL,
    status ENUM('ok','warning') NOT NULL,
    details_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reconciliation_reports_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
