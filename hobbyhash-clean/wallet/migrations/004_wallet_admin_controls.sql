-- HOBC Custodial Wallet Admin Controls Migration
-- Adds wallet holds, admin notes, and manual-review withdrawal status.
-- No transactions are created and no private keys/seeds are stored.

SET NAMES utf8mb4;

ALTER TABLE withdrawals
    MODIFY COLUMN status ENUM('pending','awaiting_approval','manual_review','approved','broadcasted','confirming','confirmed','failed','rejected','cancelled') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS wallet_user_holds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    hold_reason VARCHAR(255) NOT NULL,
    status ENUM('active','released') NOT NULL DEFAULT 'active',
    placed_by_admin_id BIGINT UNSIGNED NULL,
    released_by_admin_id BIGINT UNSIGNED NULL,
    placed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_wallet_user_holds_user_status (user_id, status),
    KEY idx_wallet_user_holds_status (status),
    CONSTRAINT fk_wallet_user_holds_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallet_user_holds_placed_by
        FOREIGN KEY (placed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT fk_wallet_user_holds_released_by
        FOREIGN KEY (released_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_admin_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    withdrawal_id BIGINT UNSIGNED NULL,
    note_type ENUM('user','withdrawal','reserve','operation') NOT NULL DEFAULT 'user',
    note TEXT NOT NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wallet_admin_notes_user_id (user_id),
    KEY idx_wallet_admin_notes_withdrawal_id (withdrawal_id),
    KEY idx_wallet_admin_notes_note_type (note_type),
    KEY idx_wallet_admin_notes_created_at (created_at),
    CONSTRAINT fk_wallet_admin_notes_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_wallet_admin_notes_withdrawal
        FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE SET NULL,
    CONSTRAINT fk_wallet_admin_notes_admin
        FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration)
VALUES ('004_wallet_admin_controls')
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
