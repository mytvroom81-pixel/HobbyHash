-- HOBC Web Wallet - Custodial schema
-- Target: PostgreSQL 14+
-- Database: hobbyhash_wallet

BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'withdrawal_status') THEN
        CREATE TYPE withdrawal_status AS ENUM (
            'pending',
            'awaiting_approval',
            'approved',
            'broadcasted',
            'confirming',
            'confirmed',
            'failed',
            'rejected',
            'cancelled'
        );
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'deposit_status') THEN
        CREATE TYPE deposit_status AS ENUM (
            'detected',
            'confirming',
            'credited',
            'orphaned'
        );
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ledger_entry_type') THEN
        CREATE TYPE ledger_entry_type AS ENUM (
            'deposit_credit',
            'withdraw_debit',
            'withdraw_fee',
            'adjustment',
            'reorg_reversal',
            'refund_credit'
        );
    END IF;
END$$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'actor_type') THEN
        CREATE TYPE actor_type AS ENUM (
            'system',
            'scanner',
            'withdrawal_worker',
            'admin',
            'user'
        );
    END IF;
END$$;

CREATE TABLE IF NOT EXISTS users (
    id                      BIGSERIAL PRIMARY KEY,
    username                VARCHAR(40) NOT NULL UNIQUE,
    email                   VARCHAR(320) NOT NULL UNIQUE,
    password_hash           TEXT NOT NULL,
    is_email_verified       BOOLEAN NOT NULL DEFAULT FALSE,
    is_active               BOOLEAN NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT users_username_format_chk CHECK (username ~ '^[A-Za-z0-9_]{3,40}$')
);

CREATE TABLE IF NOT EXISTS user_security (
    user_id                         BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    twofa_enabled                   BOOLEAN NOT NULL DEFAULT FALSE,
    twofa_secret_encrypted          TEXT,
    twofa_recovery_codes_encrypted  TEXT,
    failed_login_count              INTEGER NOT NULL DEFAULT 0,
    locked_until                    TIMESTAMPTZ,
    last_login_at                   TIMESTAMPTZ,
    last_login_ip                   INET,
    password_changed_at             TIMESTAMPTZ,
    created_at                      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sessions (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id                 BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token_hash      TEXT NOT NULL UNIQUE,
    csrf_token_hash         TEXT NOT NULL,
    ip_address              INET,
    user_agent              TEXT,
    is_revoked              BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at              TIMESTAMPTZ NOT NULL,
    last_seen_at            TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS sessions_user_id_idx ON sessions(user_id);
CREATE INDEX IF NOT EXISTS sessions_expires_at_idx ON sessions(expires_at);

CREATE TABLE IF NOT EXISTS deposit_addresses (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    address                 VARCHAR(128) NOT NULL UNIQUE,
    derivation_path         TEXT,
    label                   TEXT,
    assigned_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_checked_at         TIMESTAMPTZ,
    is_active               BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS deposit_addresses_user_id_idx ON deposit_addresses(user_id);

CREATE TABLE IF NOT EXISTS deposits (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    deposit_address_id      BIGINT NOT NULL REFERENCES deposit_addresses(id) ON DELETE RESTRICT,
    txid                    VARCHAR(128) NOT NULL,
    vout                    INTEGER NOT NULL,
    amount                  NUMERIC(24,8) NOT NULL CHECK (amount > 0),
    block_hash              VARCHAR(128),
    block_height            BIGINT,
    detected_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    confirmed_at            TIMESTAMPTZ,
    credited_at             TIMESTAMPTZ,
    confirmations           INTEGER NOT NULL DEFAULT 0 CHECK (confirmations >= 0),
    status                  deposit_status NOT NULL DEFAULT 'detected',
    is_credit_posted        BOOLEAN NOT NULL DEFAULT FALSE,
    credit_ledger_entry_id  BIGINT,
    raw_tx_json             JSONB,
    UNIQUE (txid, vout),
    CONSTRAINT deposits_status_credit_consistency_chk
        CHECK (
            (status = 'credited' AND is_credit_posted = TRUE)
            OR (status <> 'credited')
        )
);

CREATE INDEX IF NOT EXISTS deposits_user_id_idx ON deposits(user_id);
CREATE INDEX IF NOT EXISTS deposits_status_idx ON deposits(status);
CREATE INDEX IF NOT EXISTS deposits_block_height_idx ON deposits(block_height);

CREATE TABLE IF NOT EXISTS withdrawals (
    id                              BIGSERIAL PRIMARY KEY,
    user_id                         BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    requested_address               VARCHAR(128) NOT NULL,
    requested_amount                NUMERIC(24,8) NOT NULL CHECK (requested_amount > 0),
    network_fee_estimate            NUMERIC(24,8) NOT NULL DEFAULT 0 CHECK (network_fee_estimate >= 0),
    fee_charged_to_user             NUMERIC(24,8) NOT NULL DEFAULT 0 CHECK (fee_charged_to_user >= 0),
    amount_debited                  NUMERIC(24,8) NOT NULL CHECK (amount_debited > 0),
    status                          withdrawal_status NOT NULL DEFAULT 'pending',
    requires_admin_approval         BOOLEAN NOT NULL DEFAULT FALSE,
    admin_approved_by               BIGINT,
    admin_approved_at               TIMESTAMPTZ,
    admin_rejected_by               BIGINT,
    admin_rejected_at               TIMESTAMPTZ,
    rejection_reason                TEXT,
    txid                            VARCHAR(128),
    broadcast_at                    TIMESTAMPTZ,
    chain_confirmations             INTEGER NOT NULL DEFAULT 0 CHECK (chain_confirmations >= 0),
    confirmed_at                    TIMESTAMPTZ,
    failed_at                       TIMESTAMPTZ,
    failure_reason                  TEXT,
    created_at                      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                      TIMESTAMPTZ NOT NULL DEFAULT now(),
    request_ip                      INET,
    request_user_agent              TEXT,
    debit_ledger_entry_id           BIGINT,
    fee_ledger_entry_id             BIGINT,
    refund_ledger_entry_id          BIGINT,
    CONSTRAINT withdrawals_status_txid_chk
        CHECK (
            (status IN ('broadcasted','confirming','confirmed') AND txid IS NOT NULL)
            OR (status NOT IN ('broadcasted','confirming','confirmed'))
        ),
    CONSTRAINT withdrawals_confirmed_consistency_chk
        CHECK (
            (status = 'confirmed' AND confirmed_at IS NOT NULL AND chain_confirmations > 0)
            OR (status <> 'confirmed')
        )
);

CREATE INDEX IF NOT EXISTS withdrawals_user_id_idx ON withdrawals(user_id);
CREATE INDEX IF NOT EXISTS withdrawals_status_idx ON withdrawals(status);
CREATE INDEX IF NOT EXISTS withdrawals_created_at_idx ON withdrawals(created_at);

CREATE TABLE IF NOT EXISTS ledger_entries (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    asset_symbol            VARCHAR(16) NOT NULL DEFAULT 'HOBC',
    entry_type              ledger_entry_type NOT NULL,
    amount                  NUMERIC(24,8) NOT NULL,
    reference_table         VARCHAR(64) NOT NULL,
    reference_id            BIGINT NOT NULL,
    note                    TEXT,
    actor                   actor_type NOT NULL DEFAULT 'system',
    actor_user_id           BIGINT,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ledger_entries_nonzero_amount_chk CHECK (amount <> 0),
    CONSTRAINT ledger_entries_reference_chk CHECK (reference_table IN ('deposits','withdrawals','admin_adjustments'))
);

CREATE INDEX IF NOT EXISTS ledger_entries_user_id_idx ON ledger_entries(user_id);
CREATE INDEX IF NOT EXISTS ledger_entries_reference_idx ON ledger_entries(reference_table, reference_id);
CREATE INDEX IF NOT EXISTS ledger_entries_created_at_idx ON ledger_entries(created_at);

ALTER TABLE deposits
    ADD CONSTRAINT deposits_credit_ledger_fk
    FOREIGN KEY (credit_ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL;

ALTER TABLE withdrawals
    ADD CONSTRAINT withdrawals_debit_ledger_fk
    FOREIGN KEY (debit_ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL;

ALTER TABLE withdrawals
    ADD CONSTRAINT withdrawals_fee_ledger_fk
    FOREIGN KEY (fee_ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL;

ALTER TABLE withdrawals
    ADD CONSTRAINT withdrawals_refund_ledger_fk
    FOREIGN KEY (refund_ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS admin_users (
    id                      BIGSERIAL PRIMARY KEY,
    username                VARCHAR(40) NOT NULL UNIQUE,
    email                   VARCHAR(320) NOT NULL UNIQUE,
    password_hash           TEXT NOT NULL,
    role                    VARCHAR(32) NOT NULL DEFAULT 'admin',
    twofa_enabled           BOOLEAN NOT NULL DEFAULT FALSE,
    twofa_secret_encrypted  TEXT,
    is_active               BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at           TIMESTAMPTZ,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id                      BIGSERIAL PRIMARY KEY,
    admin_user_id           BIGINT REFERENCES admin_users(id) ON DELETE SET NULL,
    action                  VARCHAR(128) NOT NULL,
    target_type             VARCHAR(64),
    target_id               VARCHAR(128),
    details                 JSONB NOT NULL DEFAULT '{}'::jsonb,
    request_ip              INET,
    user_agent              TEXT,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS admin_audit_log_admin_user_idx ON admin_audit_log(admin_user_id);
CREATE INDEX IF NOT EXISTS admin_audit_log_created_at_idx ON admin_audit_log(created_at);

CREATE TABLE IF NOT EXISTS wallet_settings (
    id                                  SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    maintenance_mode                    BOOLEAN NOT NULL DEFAULT FALSE,
    deposits_paused                     BOOLEAN NOT NULL DEFAULT FALSE,
    withdrawals_paused                  BOOLEAN NOT NULL DEFAULT FALSE,
    scanner_paused                      BOOLEAN NOT NULL DEFAULT FALSE,
    deposit_confirmations_required      INTEGER NOT NULL DEFAULT 6 CHECK (deposit_confirmations_required >= 1),
    withdrawal_confirmations_required   INTEGER NOT NULL DEFAULT 1 CHECK (withdrawal_confirmations_required >= 1),
    admin_approval_threshold            NUMERIC(24,8) NOT NULL DEFAULT 5000 CHECK (admin_approval_threshold >= 0),
    per_withdrawal_min_amount           NUMERIC(24,8) NOT NULL DEFAULT 1 CHECK (per_withdrawal_min_amount >= 0),
    per_withdrawal_max_amount           NUMERIC(24,8) NOT NULL DEFAULT 100000 CHECK (per_withdrawal_max_amount > 0),
    daily_user_withdrawal_limit         NUMERIC(24,8) NOT NULL DEFAULT 250000 CHECK (daily_user_withdrawal_limit > 0),
    daily_hot_wallet_broadcast_limit    NUMERIC(24,8) NOT NULL DEFAULT 2000000 CHECK (daily_hot_wallet_broadcast_limit > 0),
    liabilities_alert_threshold_ratio   NUMERIC(12,6) NOT NULL DEFAULT 1.000000 CHECK (liabilities_alert_threshold_ratio > 0),
    updated_by_admin_id                 BIGINT REFERENCES admin_users(id) ON DELETE SET NULL,
    updated_at                          TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO wallet_settings (id) VALUES (1)
ON CONFLICT (id) DO NOTHING;

CREATE TABLE IF NOT EXISTS wallet_hot_balance_snapshots (
    id                      BIGSERIAL PRIMARY KEY,
    wallet_name             VARCHAR(128) NOT NULL,
    trusted_balance         NUMERIC(24,8) NOT NULL DEFAULT 0,
    untrusted_pending       NUMERIC(24,8) NOT NULL DEFAULT 0,
    immature_balance        NUMERIC(24,8) NOT NULL DEFAULT 0,
    liabilities_total       NUMERIC(24,8) NOT NULL DEFAULT 0,
    delta_hot_minus_liab    NUMERIC(24,8) NOT NULL DEFAULT 0,
    warning_flag            BOOLEAN NOT NULL DEFAULT FALSE,
    source_block_height     BIGINT,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS wallet_hot_balance_snapshots_created_at_idx
    ON wallet_hot_balance_snapshots(created_at);

CREATE TABLE IF NOT EXISTS chain_scan_state (
    id                          SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    scanner_name                VARCHAR(64) NOT NULL DEFAULT 'main',
    last_scanned_height         BIGINT NOT NULL DEFAULT 0,
    last_scanned_blockhash      VARCHAR(128),
    last_processed_at           TIMESTAMPTZ,
    reorg_safety_window         INTEGER NOT NULL DEFAULT 20 CHECK (reorg_safety_window >= 1),
    scanner_paused_override     BOOLEAN NOT NULL DEFAULT FALSE,
    notes                       TEXT,
    updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO chain_scan_state (id) VALUES (1)
ON CONFLICT (id) DO NOTHING;

CREATE OR REPLACE VIEW v_user_balances AS
SELECT
    u.id AS user_id,
    u.username,
    COALESCE(SUM(le.amount), 0)::NUMERIC(24,8) AS balance_hobc
FROM users u
LEFT JOIN ledger_entries le ON le.user_id = u.id AND le.asset_symbol = 'HOBC'
GROUP BY u.id, u.username;

CREATE OR REPLACE VIEW v_total_liabilities AS
SELECT COALESCE(SUM(balance_hobc), 0)::NUMERIC(24,8) AS liabilities_hobc
FROM v_user_balances;

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_set_updated_at ON users;
CREATE TRIGGER trg_users_set_updated_at
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_user_security_set_updated_at ON user_security;
CREATE TRIGGER trg_user_security_set_updated_at
BEFORE UPDATE ON user_security
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_withdrawals_set_updated_at ON withdrawals;
CREATE TRIGGER trg_withdrawals_set_updated_at
BEFORE UPDATE ON withdrawals
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_admin_users_set_updated_at ON admin_users;
CREATE TRIGGER trg_admin_users_set_updated_at
BEFORE UPDATE ON admin_users
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_wallet_settings_set_updated_at ON wallet_settings;
CREATE TRIGGER trg_wallet_settings_set_updated_at
BEFORE UPDATE ON wallet_settings
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_chain_scan_state_set_updated_at ON chain_scan_state;
CREATE TRIGGER trg_chain_scan_state_set_updated_at
BEFORE UPDATE ON chain_scan_state
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE OR REPLACE FUNCTION prevent_ledger_mutation()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'ledger_entries are immutable; use compensating entries';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_ledger_no_update ON ledger_entries;
CREATE TRIGGER trg_ledger_no_update
BEFORE UPDATE ON ledger_entries
FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation();

DROP TRIGGER IF EXISTS trg_ledger_no_delete ON ledger_entries;
CREATE TRIGGER trg_ledger_no_delete
BEFORE DELETE ON ledger_entries
FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation();

CREATE TABLE IF NOT EXISTS security_event_log (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT REFERENCES users(id) ON DELETE SET NULL,
    session_id              UUID REFERENCES sessions(id) ON DELETE SET NULL,
    event_type              VARCHAR(64) NOT NULL,
    severity                VARCHAR(16) NOT NULL DEFAULT 'info',
    request_ip              INET,
    user_agent              TEXT,
    details                 JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS security_event_log_user_id_idx ON security_event_log(user_id);
CREATE INDEX IF NOT EXISTS security_event_log_created_at_idx ON security_event_log(created_at);

COMMIT;
