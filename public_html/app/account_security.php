<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mailer_i18n.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/security_log.php';

function account_security_ensure_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = wallet_db();
    $userColumns = [
        'email_verified_at' => "ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email",
    ];
    foreach ($userColumns as $column => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column));
        if (!$stmt->fetch()) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pending_registrations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS account_recovery_requests (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pendingColumns = [
        'email_verified_at' => "ALTER TABLE pending_registrations ADD COLUMN email_verified_at DATETIME NULL AFTER email_code_hash",
        'sms_verified_at' => "ALTER TABLE pending_registrations ADD COLUMN sms_verified_at DATETIME NULL AFTER email_verified_at",
    ];
    foreach ($pendingColumns as $column => $sql) {
        $col = $pdo->query("SHOW COLUMNS FROM pending_registrations LIKE " . $pdo->quote($column));
        if (!$col->fetch()) {
            $pdo->exec($sql);
        }
    }

    $recoveryColumns = [
        'email_verified_at' => "ALTER TABLE account_recovery_requests ADD COLUMN email_verified_at DATETIME NULL AFTER email_code_hash",
        'sms_verified_at' => "ALTER TABLE account_recovery_requests ADD COLUMN sms_verified_at DATETIME NULL AFTER email_verified_at",
        'totp_verified_at' => "ALTER TABLE account_recovery_requests ADD COLUMN totp_verified_at DATETIME NULL AFTER totp_required",
    ];
    foreach ($recoveryColumns as $column => $sql) {
        $col = $pdo->query("SHOW COLUMNS FROM account_recovery_requests LIKE " . $pdo->quote($column));
        if (!$col->fetch()) {
            $pdo->exec($sql);
        }
    }

    $ensured = true;
}

function account_email_domain_valid(string $email): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $domain = substr(strrchr($email, '@') ?: '', 1);
    return $domain !== '' && (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA'));
}

function account_email_status(string $email): array
{
    account_security_ensure_schema();
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }
    if (!account_email_domain_valid($email)) {
        return ['ok' => false, 'message' => 'That email domain does not appear to accept mail.'];
    }
    $stmt = wallet_db()->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'message' => 'That email already has an account.'];
    }
    return ['ok' => true, 'message' => 'Email format and mail domain look valid.'];
}

function account_phone_in_use(string $phone, ?int $exceptUserId = null): bool
{
    $phone = sms_normalize_phone($phone);
    if ($phone === '') {
        return false;
    }
    $sql = "SELECT id FROM users WHERE phone_number = ?";
    $params = [$phone];
    if ($exceptUserId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $exceptUserId;
    }
    $sql .= " LIMIT 1";
    $stmt = wallet_db()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

function account_email_template(string $title, string $bodyHtml, string $code): array
{
    return account_email_template_localized($title, $bodyHtml, $code, mailer_i18n_locale());
}

function account_send_email_code(string $email, string $title, string $bodyHtml, string $code, ?string $locale = null): bool
{
    if ($locale === null && $title === 'Verify your HOBC wallet email') {
        return account_send_email_code_localized(
            $email,
            'email.security.register.title',
            'email.security.register.body',
            $code,
            $locale
        );
    }
    if ($locale === null && $title === 'Reset your HOBC wallet password') {
        return account_send_email_code_localized(
            $email,
            'email.security.recovery.title',
            'email.security.recovery.body',
            $code,
            $locale
        );
    }

    [$text, $html] = account_email_template_localized($title, $bodyHtml, $code, $locale);
    $sent = mailer_send($email, $title, $text, $html);
    if (!$sent) {
        throw new RuntimeException('Verification email could not be sent.');
    }

    return true;
}

function account_registration_requires_sms(string $phone): bool
{
    return sms_is_enabled()
        && sms_normalize_phone($phone) !== '';
}

function account_create_pending_registration(string $username, string $email, string $phone, string $password): array
{
    account_security_ensure_schema();
    sms_ensure_schema();

    $email = strtolower(trim($email));
    $phone = sms_normalize_phone($phone);
    $method = 'email';
    $algo = wallet_config()['security']['password_algo'] ?? PASSWORD_ARGON2ID;
    $opts = wallet_config()['security']['password_options'] ?? [];
    $hash = password_hash($password, $algo, $opts);
    $code = sprintf('%06d', random_int(0, 999999));

    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE pending_registrations SET consumed_at = UTC_TIMESTAMP() WHERE (email = ? OR (phone_number IS NOT NULL AND phone_number = ?)) AND consumed_at IS NULL")
            ->execute([$email, $phone]);
        $stmt = $pdo->prepare(
            "INSERT INTO pending_registrations
                (username, email, phone_number, password_hash, verification_method, email_code_hash, max_attempts, expires_at, request_ip, request_user_agent)
             VALUES (?, ?, ?, ?, ?, ?, 5, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 MINUTE), ?, ?)"
        );
        $stmt->execute([
            $username,
            $email,
            $phone !== '' ? $phone : null,
            $hash,
            $method,
            $method === 'email' ? password_hash($code, PASSWORD_DEFAULT) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
        $pendingId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    account_send_email_code_localized(
        $email,
        'email.security.register.title',
        'email.security.register.body',
        $code,
        function_exists('mailer_i18n_locale') ? mailer_i18n_locale() : null
    );

    return ['id' => $pendingId, 'method' => $method, 'email' => $email, 'phone' => $phone];
}

function account_complete_pending_registration(int $pendingId, string $code): bool
{
    return account_verify_pending_registration_step($pendingId, $code) === 'complete';
}

function account_finish_pending_registration(array $pending): bool
{
    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $fresh = $pdo->prepare("SELECT * FROM pending_registrations WHERE id = ? AND consumed_at IS NULL FOR UPDATE");
        $fresh->execute([(int)$pending['id']]);
        $pending = $fresh->fetch();
        if (!$pending) {
            $pdo->commit();
            return false;
        }
        $emailExists = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $emailExists->execute([(string)$pending['email'], (string)$pending['username']]);
        if ($emailExists->fetch() || account_phone_in_use((string)($pending['phone_number'] ?? ''))) {
            $pdo->prepare("UPDATE pending_registrations SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(int)$pending['id']]);
            $pdo->commit();
            return false;
        }
        $insert = $pdo->prepare(
            "INSERT INTO users (username, email, email_verified_at, phone_number, phone_verified_at, sms_2fa_enabled, password_hash)
             VALUES (?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?)"
        );
        $hasPhone = trim((string)($pending['phone_number'] ?? '')) !== '';
        $insert->execute([
            (string)$pending['username'],
            (string)$pending['email'],
            $hasPhone ? (string)$pending['phone_number'] : null,
            ((string)($pending['sms_verified_at'] ?? '') !== '' && $hasPhone) ? gmdate('Y-m-d H:i:s') : null,
            $hasPhone ? 1 : 0,
            (string)$pending['password_hash'],
        ]);
        $userId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_security (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE pending_registrations SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(int)$pending['id']]);
        $pdo->commit();
        security_log_event($userId, 'register_success', 'info');
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function account_send_registration_sms_step(array $pending): void
{
    $phone = sms_normalize_phone((string)($pending['phone_number'] ?? ''));
    $challenge = sms_create_challenge('user', (int)$pending['id'], 'wallet_registration', $phone);
    sms_send_code((int)$challenge['id'], (string)$challenge['phone_number'], (string)$challenge['code'], 'wallet registration');
    wallet_db()->prepare("UPDATE pending_registrations SET verification_method = 'sms', sms_challenge_id = ?, attempt_count = 0 WHERE id = ?")
        ->execute([(int)$challenge['id'], (int)$pending['id']]);
}

function account_verify_pending_registration_step(int $pendingId, string $code): string
{
    account_security_ensure_schema();
    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM pending_registrations WHERE id = ? AND consumed_at IS NULL FOR UPDATE");
        $stmt->execute([$pendingId]);
        $pending = $stmt->fetch();
        if (!$pending) {
            $pdo->commit();
            return false;
        }
        $expiresAt = new DateTimeImmutable((string)$pending['expires_at'], new DateTimeZone('UTC'));
        if (new DateTimeImmutable('now', new DateTimeZone('UTC')) > $expiresAt || (int)$pending['attempt_count'] >= (int)$pending['max_attempts']) {
            $pdo->prepare("UPDATE pending_registrations SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$pendingId]);
            $pdo->commit();
            return false;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ((string)($pending['email_verified_at'] ?? '') === '') {
        $valid = password_verify(preg_replace('/\D+/', '', $code), (string)$pending['email_code_hash']);
        if (!$valid) {
            $pdo->prepare("UPDATE pending_registrations SET attempt_count = attempt_count + 1 WHERE id = ?")->execute([$pendingId]);
            return 'invalid';
        }
        $pdo->prepare("UPDATE pending_registrations SET email_verified_at = UTC_TIMESTAMP(), attempt_count = 0 WHERE id = ?")->execute([$pendingId]);
        $pending['email_verified_at'] = gmdate('Y-m-d H:i:s');
        if (account_registration_requires_sms((string)($pending['phone_number'] ?? ''))) {
            account_send_registration_sms_step($pending);
            return 'sms_sent';
        }
        return account_finish_pending_registration($pending) ? 'complete' : 'invalid';
    }

    if (account_registration_requires_sms((string)($pending['phone_number'] ?? '')) && (string)($pending['sms_verified_at'] ?? '') === '') {
        $valid = sms_verify_challenge((int)$pending['sms_challenge_id'], 'user', $pendingId, 'wallet_registration', $code);
        if (!$valid) {
            $pdo->prepare("UPDATE pending_registrations SET attempt_count = attempt_count + 1 WHERE id = ?")->execute([$pendingId]);
            return 'invalid';
        }
        $pdo->prepare("UPDATE pending_registrations SET sms_verified_at = UTC_TIMESTAMP(), attempt_count = 0 WHERE id = ?")->execute([$pendingId]);
        $pending['sms_verified_at'] = gmdate('Y-m-d H:i:s');
    }

    return account_finish_pending_registration($pending) ? 'complete' : 'invalid';
}

function account_user_has_totp(int $userId): bool
{
    $stmt = wallet_db()->prepare("SELECT twofa_enabled, twofa_secret_encrypted FROM user_security WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return (bool)($row['twofa_enabled'] ?? false) && trim((string)($row['twofa_secret_encrypted'] ?? '')) !== '';
}

function account_create_recovery_request(array $user): array
{
    account_security_ensure_schema();
    $method = 'email';
    $code = sprintf('%06d', random_int(0, 999999));
    $totpRequired = account_user_has_totp((int)$user['id']) ? 1 : 0;
    $pdo = wallet_db();
    $stmt = $pdo->prepare(
        "INSERT INTO account_recovery_requests
            (user_id, verification_method, email_code_hash, totp_required, max_attempts, expires_at, request_ip, request_user_agent)
         VALUES (?, ?, ?, ?, 5, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 MINUTE), ?, ?)"
    );
    $stmt->execute([
        (int)$user['id'],
        $method,
        password_hash($code, PASSWORD_DEFAULT),
        $totpRequired,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
    $requestId = (int)$pdo->lastInsertId();

    account_send_email_code_localized(
        (string)$user['email'],
        'email.security.recovery.title',
        'email.security.recovery.body',
        $code,
        function_exists('mailer_i18n_locale') ? mailer_i18n_locale() : null
    );

    security_log_event((int)$user['id'], 'password_reset_code_sent', 'info', ['method' => $method]);
    return ['id' => $requestId, 'method' => $method, 'totp_required' => $totpRequired === 1];
}

function account_complete_recovery(int $requestId, string $code, string $totpCode, string $newPassword): bool
{
    return account_complete_recovery_step($requestId, $code, $totpCode, $newPassword) === 'complete';
}

function account_recovery_requires_sms(array $request): bool
{
    return sms_is_enabled() && sms_normalize_phone((string)($request['phone_number'] ?? '')) !== '';
}

function account_send_recovery_sms_step(array $request): void
{
    $phone = sms_normalize_phone((string)($request['phone_number'] ?? ''));
    $challenge = sms_create_challenge('user', (int)$request['id'], 'password_reset', $phone);
    sms_send_code((int)$challenge['id'], (string)$challenge['phone_number'], (string)$challenge['code'], 'password reset');
    wallet_db()->prepare("UPDATE account_recovery_requests SET verification_method = 'sms', sms_challenge_id = ?, attempt_count = 0 WHERE id = ?")
        ->execute([(int)$challenge['id'], (int)$request['id']]);
}

function account_finish_recovery(array $request, string $newPassword): bool
{
    $algo = wallet_config()['security']['password_algo'] ?? PASSWORD_ARGON2ID;
    $opts = wallet_config()['security']['password_options'] ?? [];
    $hash = password_hash($newPassword, $algo, $opts);
    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, (int)$request['user_id']]);
        $pdo->prepare("UPDATE account_recovery_requests SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(int)$request['id']]);
        $pdo->prepare("UPDATE sessions SET is_revoked = 1 WHERE user_id = ?")->execute([(int)$request['user_id']]);
        $pdo->commit();
        security_log_event((int)$request['user_id'], 'password_reset_completed', 'info');
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function account_complete_recovery_step(int $requestId, string $code, string $totpCode, string $newPassword): string
{
    account_security_ensure_schema();
    if (strlen($newPassword) < 12) {
        return 'invalid';
    }

    $pdo = wallet_db();
    $stmt = $pdo->prepare(
        "SELECT r.*, u.email, u.phone_number, u.sms_2fa_enabled
         FROM account_recovery_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ? AND r.consumed_at IS NULL
         LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) {
        return 'invalid';
    }
    $expiresAt = new DateTimeImmutable((string)$request['expires_at'], new DateTimeZone('UTC'));
    if (new DateTimeImmutable('now', new DateTimeZone('UTC')) > $expiresAt || (int)$request['attempt_count'] >= (int)$request['max_attempts']) {
        $pdo->prepare("UPDATE account_recovery_requests SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$requestId]);
        return 'invalid';
    }

    if ((string)($request['email_verified_at'] ?? '') === '') {
        $valid = password_verify(preg_replace('/\D+/', '', $code), (string)$request['email_code_hash']);
        if (!$valid) {
            $pdo->prepare("UPDATE account_recovery_requests SET attempt_count = attempt_count + 1 WHERE id = ?")->execute([$requestId]);
            return 'invalid';
        }
        $pdo->prepare("UPDATE account_recovery_requests SET email_verified_at = UTC_TIMESTAMP(), attempt_count = 0 WHERE id = ?")->execute([$requestId]);
        $request['email_verified_at'] = gmdate('Y-m-d H:i:s');
        if (account_recovery_requires_sms($request)) {
            account_send_recovery_sms_step($request);
            return 'sms_sent';
        }
        return ((int)$request['totp_required'] === 1) ? 'totp_needed' : (account_finish_recovery($request, $newPassword) ? 'complete' : 'invalid');
    }

    if (account_recovery_requires_sms($request) && (string)($request['sms_verified_at'] ?? '') === '') {
        $valid = sms_verify_challenge((int)$request['sms_challenge_id'], 'user', $requestId, 'password_reset', $code);
        if (!$valid) {
            $pdo->prepare("UPDATE account_recovery_requests SET attempt_count = attempt_count + 1 WHERE id = ?")->execute([$requestId]);
            return 'invalid';
        }
        $pdo->prepare("UPDATE account_recovery_requests SET sms_verified_at = UTC_TIMESTAMP(), attempt_count = 0 WHERE id = ?")->execute([$requestId]);
        $request['sms_verified_at'] = gmdate('Y-m-d H:i:s');
    }

    if ((int)$request['totp_required'] === 1 && (string)($request['totp_verified_at'] ?? '') === '') {
        $secret = totp_user_secret((int)$request['user_id']);
        if ($secret === '' || !totp_verify($secret, $totpCode)) {
            $pdo->prepare("UPDATE account_recovery_requests SET attempt_count = attempt_count + 1 WHERE id = ?")->execute([$requestId]);
            return 'totp_needed';
        }
        $pdo->prepare("UPDATE account_recovery_requests SET totp_verified_at = UTC_TIMESTAMP(), attempt_count = 0 WHERE id = ?")->execute([$requestId]);
        $request['totp_verified_at'] = gmdate('Y-m-d H:i:s');
    }

    return account_finish_recovery($request, $newPassword) ? 'complete' : 'invalid';
}
