<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_translate_google.php';
require_once __DIR__ . '/i18n_translate_mask.php';

/**
 * Support ticket message translation:
 * - User/guest messages → English for admin
 * - Admin replies → requester locale for user + email
 */
final class HobcSupportI18n
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $pdo = wallet_db();
            $col = $pdo->query("SHOW COLUMNS FROM support_tickets LIKE 'requester_locale'");
            if (!$col->fetch()) {
                $pdo->exec("ALTER TABLE support_tickets ADD COLUMN requester_locale VARCHAR(16) NULL AFTER source_context");
            }

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS support_message_translations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    message_id BIGINT UNSIGNED NOT NULL,
                    target_locale VARCHAR(16) NOT NULL,
                    translated_message MEDIUMTEXT NOT NULL,
                    source_hash CHAR(64) NOT NULL DEFAULT \'\',
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_support_msg_i18n (message_id, target_locale),
                    KEY idx_support_msg_i18n_msg (message_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS support_ticket_translations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    ticket_id BIGINT UNSIGNED NOT NULL,
                    target_locale VARCHAR(16) NOT NULL,
                    field_name VARCHAR(32) NOT NULL,
                    translated_value VARCHAR(190) NOT NULL,
                    source_hash CHAR(64) NOT NULL DEFAULT \'\',
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_support_ticket_i18n (ticket_id, target_locale, field_name),
                    KEY idx_support_ticket_i18n_ticket (ticket_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            self::$schemaReady = true;
        } catch (Throwable $e) {
            wallet_log_error('support_i18n schema ensure failed: ' . $e->getMessage());
        }
    }

    public static function defaultLocale(): string
    {
        return function_exists('hobc_i18n_default_locale') ? hobc_i18n_default_locale() : 'en';
    }

    public static function currentLocale(): string
    {
        if (function_exists('hobc_i18n_locale')) {
            return hobc_i18n_locale();
        }

        return self::defaultLocale();
    }

    public static function sourceHash(string $text): string
    {
        return hash('sha256', trim($text));
    }

    public static function normalizeLocale(?string $locale): string
    {
        $locale = trim((string)$locale);
        if ($locale === '') {
            return self::defaultLocale();
        }

        if (function_exists('hobc_i18n_normalize_locale')) {
            $normalized = hobc_i18n_normalize_locale($locale);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $locale;
    }

    public static function translateText(string $text, string $targetLocale, string $sourceLocale): string
    {
        $text = trim($text);
        $targetLocale = self::normalizeLocale($targetLocale);
        $sourceLocale = self::normalizeLocale($sourceLocale);
        if ($text === '' || $targetLocale === $sourceLocale) {
            return $text;
        }

        try {
            [$masked, $tokens] = hobc_translate_mask_string($text);
            $client = new HobcGoogleTranslateClient();
            $translated = $client->translateBatch([$masked], $targetLocale, $sourceLocale)[0] ?? '';
            $unmasked = hobc_translate_unmask_string($translated, $tokens);

            return $unmasked !== '' ? $unmasked : $text;
        } catch (Throwable $e) {
            wallet_log_error('support_i18n translate failed: ' . $e->getMessage());
            return $text;
        }
    }

    public static function cacheMessageTranslation(int $messageId, string $targetLocale, string $sourceText, string $translatedText): void
    {
        self::ensureSchema();
        if ($messageId <= 0 || trim($translatedText) === '') {
            return;
        }

        $stmt = wallet_db()->prepare(
            'INSERT INTO support_message_translations (message_id, target_locale, translated_message, source_hash)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE translated_message = VALUES(translated_message), source_hash = VALUES(source_hash)'
        );
        $stmt->execute([$messageId, self::normalizeLocale($targetLocale), $translatedText, self::sourceHash($sourceText)]);
    }

    public static function cachedMessageTranslation(int $messageId, string $targetLocale, string $sourceText): ?string
    {
        self::ensureSchema();
        if ($messageId <= 0) {
            return null;
        }

        $stmt = wallet_db()->prepare(
            'SELECT translated_message, source_hash FROM support_message_translations
             WHERE message_id = ? AND target_locale = ? LIMIT 1'
        );
        $stmt->execute([$messageId, self::normalizeLocale($targetLocale)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $hash = (string)($row['source_hash'] ?? '');
        if ($hash !== '' && $hash !== self::sourceHash($sourceText)) {
            return null;
        }

        $value = trim((string)($row['translated_message'] ?? ''));
        return $value !== '' ? $value : null;
    }

    public static function messageTranslation(int $messageId, string $sourceText, string $targetLocale, string $sourceLocale): string
    {
        $sourceText = trim($sourceText);
        $targetLocale = self::normalizeLocale($targetLocale);
        $sourceLocale = self::normalizeLocale($sourceLocale);
        if ($sourceText === '' || $targetLocale === $sourceLocale) {
            return $sourceText;
        }

        $cached = self::cachedMessageTranslation($messageId, $targetLocale, $sourceText);
        if ($cached !== null) {
            return $cached;
        }

        $translated = self::translateText($sourceText, $targetLocale, $sourceLocale);
        if ($translated !== '' && $translated !== $sourceText) {
            self::cacheMessageTranslation($messageId, $targetLocale, $sourceText, $translated);
        }

        return $translated !== '' ? $translated : $sourceText;
    }

    public static function cacheTicketField(int $ticketId, string $field, string $targetLocale, string $sourceText, string $translatedText): void
    {
        self::ensureSchema();
        if ($ticketId <= 0 || trim($translatedText) === '') {
            return;
        }

        $stmt = wallet_db()->prepare(
            'INSERT INTO support_ticket_translations (ticket_id, target_locale, field_name, translated_value, source_hash)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE translated_value = VALUES(translated_value), source_hash = VALUES(source_hash)'
        );
        $stmt->execute([$ticketId, self::normalizeLocale($targetLocale), $field, substr($translatedText, 0, 190), self::sourceHash($sourceText)]);
    }

    public static function ticketFieldTranslation(int $ticketId, string $field, string $sourceText, string $targetLocale, string $sourceLocale): string
    {
        $sourceText = trim($sourceText);
        $targetLocale = self::normalizeLocale($targetLocale);
        $sourceLocale = self::normalizeLocale($sourceLocale);
        if ($sourceText === '' || $targetLocale === $sourceLocale) {
            return $sourceText;
        }

        self::ensureSchema();
        $stmt = wallet_db()->prepare(
            'SELECT translated_value, source_hash FROM support_ticket_translations
             WHERE ticket_id = ? AND target_locale = ? AND field_name = ? LIMIT 1'
        );
        $stmt->execute([$ticketId, $targetLocale, $field]);
        $row = $stmt->fetch();
        if ($row) {
            $hash = (string)($row['source_hash'] ?? '');
            if ($hash === '' || $hash === self::sourceHash($sourceText)) {
                $value = trim((string)($row['translated_value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $translated = self::translateText($sourceText, $targetLocale, $sourceLocale);
        if ($translated !== '' && $translated !== $sourceText) {
            self::cacheTicketField($ticketId, $field, $targetLocale, $sourceText, $translated);
        }

        return $translated !== '' ? $translated : $sourceText;
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public static function ticketRequesterLocale(array $ticket): string
    {
        $locale = self::normalizeLocale((string)($ticket['requester_locale'] ?? ''));
        return $locale !== '' ? $locale : self::defaultLocale();
    }

    /**
     * After a user/guest message is stored.
     */
    public static function onUserMessage(int $ticketId, int $messageId, string $message, string $sourceLocale): void
    {
        $en = self::defaultLocale();
        $sourceLocale = self::normalizeLocale($sourceLocale);
        if ($messageId <= 0 || trim($message) === '' || $sourceLocale === $en) {
            return;
        }

        self::messageTranslation($messageId, $message, $en, $sourceLocale);
    }

    /**
     * After ticket subject is stored (user/guest authored).
     *
     * @param array<string, mixed> $ticket
     */
    public static function onTicketCreated(array $ticket, string $subject, string $sourceLocale): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $en = self::defaultLocale();
        $sourceLocale = self::normalizeLocale($sourceLocale);
        if ($ticketId <= 0 || trim($subject) === '' || $sourceLocale === $en) {
            return;
        }

        self::ticketFieldTranslation($ticketId, 'subject', $subject, $en, $sourceLocale);
    }

    /**
     * After an admin reply is stored.
     *
     * @param array<string, mixed> $ticket
     */
    public static function onAdminReply(array $ticket, int $messageId, string $message): string
    {
        $en = self::defaultLocale();
        $targetLocale = self::ticketRequesterLocale($ticket);
        if ($messageId <= 0 || trim($message) === '' || $targetLocale === $en) {
            return $message;
        }

        return self::messageTranslation($messageId, $message, $targetLocale, $en);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $ticket
     */
    public static function messageForAdmin(array $message, array $ticket): string
    {
        $raw = trim((string)($message['message'] ?? ''));
        $sender = (string)($message['sender_type'] ?? '');
        if ($raw === '' || in_array($sender, ['admin', 'system'], true)) {
            return $raw;
        }

        $messageId = (int)($message['id'] ?? 0);
        $sourceLocale = self::ticketRequesterLocale($ticket);
        $en = self::defaultLocale();
        if ($sourceLocale === $en) {
            return $raw;
        }

        return self::messageTranslation($messageId, $raw, $en, $sourceLocale);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $ticket
     */
    public static function messageForUser(array $message, array $ticket): string
    {
        $raw = trim((string)($message['message'] ?? ''));
        $sender = (string)($message['sender_type'] ?? '');
        if ($raw === '' || in_array($sender, ['user', 'guest'], true)) {
            return $raw;
        }

        $messageId = (int)($message['id'] ?? 0);
        $targetLocale = self::ticketRequesterLocale($ticket);
        $en = self::defaultLocale();
        if ($targetLocale === $en) {
            return $raw;
        }

        return self::messageTranslation($messageId, $raw, $targetLocale, $en);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public static function subjectForAdmin(array $ticket): string
    {
        $raw = trim((string)($ticket['subject'] ?? ''));
        $ticketId = (int)($ticket['id'] ?? 0);
        $sourceLocale = self::ticketRequesterLocale($ticket);
        $en = self::defaultLocale();
        if ($raw === '' || $sourceLocale === $en) {
            return $raw;
        }

        return self::ticketFieldTranslation($ticketId, 'subject', $raw, $en, $sourceLocale);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $ticket
     */
    public static function showOriginalHintForAdmin(array $message, array $ticket): bool
    {
        $sender = (string)($message['sender_type'] ?? '');
        if (!in_array($sender, ['user', 'guest'], true)) {
            return false;
        }

        $raw = trim((string)($message['message'] ?? ''));
        $shown = self::messageForAdmin($message, $ticket);
        return $raw !== '' && $shown !== $raw;
    }
}

function support_i18n_ensure_schema(): void
{
    HobcSupportI18n::ensureSchema();
}

function support_i18n_current_locale(): string
{
    return HobcSupportI18n::currentLocale();
}
