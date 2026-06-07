<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

function sms_config(): array
{
    return wallet_config()['sms'] ?? [];
}

function sms_is_enabled(): bool
{
    $cfg = sms_config();
    $providerMode = sms_provider_mode();
    return (bool)($cfg['enabled'] ?? false)
        && (string)($cfg['provider'] ?? '') === 'twilio'
        && trim((string)($cfg['account_sid'] ?? '')) !== ''
        && trim((string)($cfg['api_key_sid'] ?? '')) !== ''
        && trim((string)($cfg['api_key_secret'] ?? '')) !== ''
        && (
            ($providerMode === 'twilio_verify' && sms_twilio_verify_service_sid() !== '')
            || ($providerMode === 'manual' && (
                trim((string)($cfg['messaging_service_sid'] ?? '')) !== ''
                || trim((string)($cfg['from_number'] ?? '')) !== ''
            ))
        );
}

function sms_ensure_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = wallet_db();
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'phone_number'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN phone_number VARCHAR(32) NULL AFTER email");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'sms_2fa_enabled'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN sms_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER phone_number");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone_number'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone_number VARCHAR(32) NULL AFTER email");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone_verified_at'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone_verified_at DATETIME NULL AFTER phone_number");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'sms_2fa_enabled'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN sms_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER phone_verified_at");
        }

        $walletSettingColumns = [
            'admin_sms_2fa_required' => "ALTER TABLE wallet_settings ADD COLUMN admin_sms_2fa_required TINYINT(1) NOT NULL DEFAULT 0 AFTER scanner_paused",
            'wallet_sms_registration_required' => "ALTER TABLE wallet_settings ADD COLUMN wallet_sms_registration_required TINYINT(1) NOT NULL DEFAULT 0 AFTER admin_sms_2fa_required",
            'wallet_sms_login_required' => "ALTER TABLE wallet_settings ADD COLUMN wallet_sms_login_required TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_sms_registration_required",
            'wallet_sms_withdrawal_required' => "ALTER TABLE wallet_settings ADD COLUMN wallet_sms_withdrawal_required TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_sms_login_required",
            'sms_provider_mode' => "ALTER TABLE wallet_settings ADD COLUMN sms_provider_mode VARCHAR(32) NOT NULL DEFAULT 'manual' AFTER wallet_sms_withdrawal_required",
            'twilio_verify_service_sid' => "ALTER TABLE wallet_settings ADD COLUMN twilio_verify_service_sid VARCHAR(64) NULL AFTER sms_provider_mode",
        ];
        foreach ($walletSettingColumns as $column => $sql) {
            $stmt = $pdo->query("SHOW COLUMNS FROM wallet_settings LIKE " . $pdo->quote($column));
            if (!$stmt->fetch()) {
                $pdo->exec($sql);
            }
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sms_challenges (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        wallet_log_error('SMS schema check failed: ' . $e->getMessage());
        throw $e;
    }

    $ensured = true;
}

function sms_provider_mode(): string
{
    sms_ensure_schema();
    try {
        $stmt = wallet_db()->query("SELECT sms_provider_mode FROM wallet_settings WHERE id = 1");
        $mode = (string)($stmt->fetchColumn() ?: 'manual');
    } catch (Throwable $e) {
        wallet_log_error('SMS provider mode read failed: ' . $e->getMessage());
        $mode = 'manual';
    }

    return $mode === 'twilio_verify' ? 'twilio_verify' : 'manual';
}

function sms_twilio_verify_service_sid(): string
{
    sms_ensure_schema();
    try {
        $stmt = wallet_db()->query("SELECT twilio_verify_service_sid FROM wallet_settings WHERE id = 1");
        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        wallet_log_error('Twilio Verify Service SID read failed: ' . $e->getMessage());
        return '';
    }
}

function sms_normalize_phone(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }
    if ($phone[0] === '+') {
        return '+' . preg_replace('/\D+/', '', substr($phone, 1));
    }
    $digits = preg_replace('/\D+/', '', $phone);
    return $digits !== '' ? '+' . $digits : '';
}

function sms_admin_requires_2fa(array $admin): bool
{
    return sms_is_enabled()
        && sms_setting_enabled('admin_sms_2fa_required')
        && (bool)($admin['sms_2fa_enabled'] ?? false)
        && sms_normalize_phone((string)($admin['phone_number'] ?? '')) !== '';
}

function sms_setting_enabled(string $setting): bool
{
    sms_ensure_schema();
    $allowed = [
        'admin_sms_2fa_required',
        'wallet_sms_registration_required',
        'wallet_sms_login_required',
        'wallet_sms_withdrawal_required',
    ];
    if (!in_array($setting, $allowed, true)) {
        return false;
    }

    try {
        $stmt = wallet_db()->query("SELECT {$setting} FROM wallet_settings WHERE id = 1");
        $row = $stmt->fetch();
        return (bool)($row[$setting] ?? false);
    } catch (Throwable $e) {
        wallet_log_error('SMS setting read failed: ' . $e->getMessage());
        return false;
    }
}

function sms_user_requires_login_2fa(array $user): bool
{
    return sms_is_enabled()
        && sms_setting_enabled('wallet_sms_login_required')
        && (bool)($user['sms_2fa_enabled'] ?? false)
        && sms_normalize_phone((string)($user['phone_number'] ?? '')) !== '';
}

function sms_user_requires_withdrawal_2fa(array $user): bool
{
    return sms_is_enabled()
        && sms_setting_enabled('wallet_sms_withdrawal_required')
        && (bool)($user['sms_2fa_enabled'] ?? false)
        && sms_normalize_phone((string)($user['phone_number'] ?? '')) !== '';
}

function sms_create_challenge(string $subjectType, int $subjectId, string $purpose, string $phoneNumber): array
{
    sms_ensure_schema();

    $cfg = sms_config();
    $ttl = max(60, (int)($cfg['code_ttl_seconds'] ?? 600));
    $maxAttempts = max(1, (int)($cfg['max_attempts'] ?? 5));
    $phoneNumber = sms_normalize_phone($phoneNumber);
    if ($phoneNumber === '') {
        throw new RuntimeException('SMS phone number is missing.');
    }

    $code = sprintf('%06d', random_int(0, 999999));
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $close = $pdo->prepare(
            "UPDATE sms_challenges
             SET consumed_at = UTC_TIMESTAMP()
             WHERE subject_type = ?
               AND subject_id = ?
               AND purpose = ?
               AND consumed_at IS NULL"
        );
        $close->execute([$subjectType, $subjectId, $purpose]);

        $stmt = $pdo->prepare(
            "INSERT INTO sms_challenges
                (subject_type, subject_id, purpose, phone_number, code_hash, max_attempts, expires_at, request_ip, request_user_agent, send_status)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), ?, ?, 'created')"
        );
        $stmt->execute([
            $subjectType,
            $subjectId,
            substr($purpose, 0, 40),
            $phoneNumber,
            $hash,
            $maxAttempts,
            $ttl,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
        $challengeId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['id' => $challengeId, 'code' => $code, 'phone_number' => $phoneNumber];
}

function sms_send_code(int $challengeId, string $phoneNumber, string $code, string $label): void
{
    if (sms_provider_mode() === 'twilio_verify') {
        $verificationSid = sms_send_twilio_verify_code($phoneNumber);
        $stmt = wallet_db()->prepare("UPDATE sms_challenges SET provider_message_id = ?, send_status = 'sent_verify' WHERE id = ?");
        $stmt->execute([$verificationSid, $challengeId]);
        return;
    }

    $body = 'Your HOBC ' . $label . ' verification code is ' . $code . '. It expires in 10 minutes. Do not share this code.';
    $messageId = sms_send_twilio_message($phoneNumber, $body);

    $stmt = wallet_db()->prepare("UPDATE sms_challenges SET provider_message_id = ?, send_status = 'sent' WHERE id = ?");
    $stmt->execute([$messageId, $challengeId]);
}

function sms_send_twilio_message(string $to, string $body): string
{
    if (!sms_is_enabled()) {
        throw new RuntimeException('SMS is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Twilio SMS.');
    }

    $cfg = sms_config();
    $accountSid = trim((string)$cfg['account_sid']);
    $apiKeySid = trim((string)$cfg['api_key_sid']);
    $apiKeySecret = (string)$cfg['api_key_secret'];
    $messagingServiceSid = trim((string)($cfg['messaging_service_sid'] ?? ''));
    $fromNumber = trim((string)($cfg['from_number'] ?? ''));

    $payload = [
        'To' => $to,
        'Body' => $body,
    ];
    if ($messagingServiceSid !== '') {
        $payload['MessagingServiceSid'] = $messagingServiceSid;
    } else {
        $payload['From'] = $fromNumber;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERPWD => $apiKeySid . ':' . $apiKeySecret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $errno !== 0) {
        throw new RuntimeException('Twilio SMS request failed: ' . $error);
    }

    $data = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($data) ? (string)($data['message'] ?? 'Twilio rejected SMS request.') : 'Twilio rejected SMS request.';
        throw new RuntimeException($message);
    }

    return is_array($data) ? (string)($data['sid'] ?? '') : '';
}

function sms_send_twilio_verify_code(string $to): string
{
    if (!sms_is_enabled()) {
        throw new RuntimeException('Twilio Verify is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Twilio Verify.');
    }

    $cfg = sms_config();
    $apiKeySid = trim((string)$cfg['api_key_sid']);
    $apiKeySecret = (string)$cfg['api_key_secret'];
    $serviceSid = sms_twilio_verify_service_sid();
    if ($serviceSid === '') {
        throw new RuntimeException('Twilio Verify Service SID is missing.');
    }

    $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode($serviceSid) . '/Verifications';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'To' => sms_normalize_phone($to),
            'Channel' => 'sms',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERPWD => $apiKeySid . ':' . $apiKeySecret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $errno !== 0) {
        throw new RuntimeException('Twilio Verify request failed: ' . $error);
    }

    $data = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($data) ? (string)($data['message'] ?? 'Twilio rejected Verify request.') : 'Twilio rejected Verify request.';
        throw new RuntimeException($message);
    }

    return is_array($data) ? (string)($data['sid'] ?? '') : '';
}

function sms_check_twilio_verify_code(string $to, string $code): bool
{
    if (!sms_is_enabled()) {
        throw new RuntimeException('Twilio Verify is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for Twilio Verify.');
    }

    $cfg = sms_config();
    $apiKeySid = trim((string)$cfg['api_key_sid']);
    $apiKeySecret = (string)$cfg['api_key_secret'];
    $serviceSid = sms_twilio_verify_service_sid();
    if ($serviceSid === '') {
        throw new RuntimeException('Twilio Verify Service SID is missing.');
    }

    $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode($serviceSid) . '/VerificationCheck';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'To' => sms_normalize_phone($to),
            'Code' => preg_replace('/\D+/', '', $code),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERPWD => $apiKeySid . ':' . $apiKeySecret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $errno !== 0) {
        throw new RuntimeException('Twilio Verify check failed: ' . $error);
    }

    $data = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($data) ? (string)($data['message'] ?? 'Twilio rejected Verify check.') : 'Twilio rejected Verify check.';
        throw new RuntimeException($message);
    }

    return is_array($data) && (string)($data['status'] ?? '') === 'approved';
}

function sms_verify_challenge(int $challengeId, string $subjectType, int $subjectId, string $purpose, string $code): bool
{
    sms_ensure_schema();

    $code = preg_replace('/\D+/', '', $code);
    if ($code === '' || strlen($code) !== 6) {
        return false;
    }
    if (sms_provider_mode() === 'twilio_verify') {
        return sms_verify_twilio_challenge($challengeId, $subjectType, $subjectId, $purpose, $code);
    }

    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM sms_challenges
             WHERE id = ?
               AND subject_type = ?
               AND subject_id = ?
               AND purpose = ?
               AND consumed_at IS NULL
             FOR UPDATE"
        );
        $stmt->execute([$challengeId, $subjectType, $subjectId, $purpose]);
        $challenge = $stmt->fetch();
        if (!$challenge) {
            $pdo->commit();
            return false;
        }

        $expiresAt = new DateTimeImmutable((string)$challenge['expires_at'], new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($now > $expiresAt || (int)$challenge['attempt_count'] >= (int)$challenge['max_attempts']) {
            $pdo->prepare("UPDATE sms_challenges SET consumed_at = UTC_TIMESTAMP(), send_status = 'expired' WHERE id = ?")->execute([$challengeId]);
            $pdo->commit();
            return false;
        }

        if (!password_verify($code, (string)$challenge['code_hash'])) {
            $pdo->prepare("UPDATE sms_challenges SET attempt_count = attempt_count + 1, send_status = 'failed' WHERE id = ?")->execute([$challengeId]);
            $pdo->commit();
            return false;
        }

        $pdo->prepare("UPDATE sms_challenges SET consumed_at = UTC_TIMESTAMP(), send_status = 'verified' WHERE id = ?")->execute([$challengeId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function sms_verify_twilio_challenge(int $challengeId, string $subjectType, int $subjectId, string $purpose, string $code): bool
{
    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM sms_challenges
             WHERE id = ?
               AND subject_type = ?
               AND subject_id = ?
               AND purpose = ?
               AND consumed_at IS NULL
             FOR UPDATE"
        );
        $stmt->execute([$challengeId, $subjectType, $subjectId, $purpose]);
        $challenge = $stmt->fetch();
        if (!$challenge) {
            $pdo->commit();
            return false;
        }

        $expiresAt = new DateTimeImmutable((string)$challenge['expires_at'], new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($now > $expiresAt || (int)$challenge['attempt_count'] >= (int)$challenge['max_attempts']) {
            $pdo->prepare("UPDATE sms_challenges SET consumed_at = UTC_TIMESTAMP(), send_status = 'expired' WHERE id = ?")->execute([$challengeId]);
            $pdo->commit();
            return false;
        }

        $approved = sms_check_twilio_verify_code((string)$challenge['phone_number'], $code);
        if (!$approved) {
            $pdo->prepare("UPDATE sms_challenges SET attempt_count = attempt_count + 1, send_status = 'failed_verify' WHERE id = ?")->execute([$challengeId]);
            $pdo->commit();
            return false;
        }

        $pdo->prepare("UPDATE sms_challenges SET consumed_at = UTC_TIMESTAMP(), send_status = 'verified' WHERE id = ?")->execute([$challengeId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
