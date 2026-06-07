<?php
declare(strict_types=1);

/**
 * HobbyHash Social Bot — PHP admin integration.
 * Reads/writes the bot SQLite database; calls the Node service for publish/preview/scheduler actions.
 */

require_once __DIR__ . '/admin_datetime.php';

function social_bot_format_datetime(?string $value): string
{
    return admin_format_utc_datetime($value);
}

function social_bot_h_datetime(?string $value): string
{
    return admin_h_utc_datetime($value);
}

function social_bot_datetime_note(): string
{
    return admin_datetime_note();
}

function social_bot_format_x_action_type(string $type): string
{
    return match (true) {
        str_ends_with($type, '_dedup') => str_replace('_dedup', ' (dedup)', $type),
        str_ends_with($type, '_blocked') => str_replace('_blocked', ' (blocked)', $type),
        $type === 'read_poll_blocked' => 'read_poll (blocked)',
        default => $type,
    };
}

function social_bot_x_usage_row_notes(array $row): string
{
    $type = (string)($row['action_type'] ?? '');
    if (str_contains($type, '_dedup')) {
        return 'deduped — no charge';
    }
    if (str_ends_with($type, '_blocked')) {
        return 'budget blocked';
    }
    if ($type === 'read_poll') {
        return 'mention poll (every 5 min; $0 when timeline empty)';
    }
    if ($type === 'read_poll_blocked') {
        return 'poll skipped — budget blocked';
    }
    if (!empty($row['has_url'])) {
        return 'has URL';
    }

    return '—';
}

function social_bot_load_dotenv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $path = '/home/hobbyhashcoin/social-bot/.env';
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function social_bot_internal_token(): string
{
    social_bot_load_dotenv();
    $fromEnv = (string)(getenv('SOCIAL_BOT_INTERNAL_TOKEN') ?: '');
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    $tokenFile = '/home/hobbyhashcoin/social-bot/data/internal-token';
    if (is_readable($tokenFile)) {
        return trim((string)file_get_contents($tokenFile));
    }
    return '';
}

function social_bot_api_url(): string
{
    social_bot_load_dotenv();
    return rtrim(getenv('SOCIAL_BOT_API_URL') ?: 'http://127.0.0.1:3847', '/');
}

function social_bot_db_path(): string
{
    social_bot_load_dotenv();
    $candidates = array_filter([
        getenv('SOCIAL_BOT_DB_PATH') ?: null,
        getenv('DB_PATH') ?: null,
        '/home/hobbyhashcoin/social-bot/data/social-bot.db',
    ]);
    foreach ($candidates as $path) {
        if (str_starts_with($path, './')) {
            $path = '/home/hobbyhashcoin/social-bot/' . substr($path, 2);
        }
        if (is_readable($path) || is_writable(dirname($path))) {
            return $path;
        }
    }
    return '/home/hobbyhashcoin/social-bot/data/social-bot.db';
}

function social_bot_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = social_bot_db_path();
    if (!file_exists($path)) {
        throw new RuntimeException('Social bot database not found at ' . $path . '. Run npm run migrate in /social-bot/.');
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function social_bot_available(): bool
{
    try {
        social_bot_pdo();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function social_bot_setting(string $key, mixed $default = null): mixed
{
    $stmt = social_bot_pdo()->prepare('SELECT value FROM social_settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }
    $decoded = json_decode((string)$row['value'], true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $row['value'];
}

function social_bot_set_setting(string $key, mixed $value, string $updatedBy = 'admin'): void
{
    $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES);
    social_bot_pdo()->prepare('
        INSERT INTO social_settings (key, value, updated_at, updated_by)
        VALUES (?, ?, datetime(\'now\'), ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at, updated_by = excluded.updated_by
    ')->execute([$key, $json, $updatedBy]);
}

function social_bot_stats(): array
{
    $pdo = social_bot_pdo();
    $bounds = admin_local_day_bounds();
    $publishedToday = $pdo->prepare("
        SELECT COUNT(*) FROM social_posts
        WHERE status = 'published'
          AND published_at >= ?
          AND published_at < ?
    ");
    $publishedToday->execute([$bounds['start_utc'], $bounds['end_utc']]);

    return [
        'pending_posts' => (int)$pdo->query("SELECT COUNT(*) FROM social_posts WHERE status = 'pending'")->fetchColumn(),
        'published_today' => (int)$publishedToday->fetchColumn(),
        'pending_replies' => (int)$pdo->query("SELECT COUNT(*) FROM social_reply_log WHERE status = 'pending'")->fetchColumn(),
        'unprocessed_events' => (int)$pdo->query('SELECT COUNT(*) FROM social_events WHERE processed = 0')->fetchColumn(),
        'service_ok' => social_bot_service_health()['ok'] ?? false,
    ];
}

function social_bot_platforms(): array
{
    return social_bot_pdo()->query('SELECT * FROM social_platform_accounts ORDER BY platform')->fetchAll();
}

function social_bot_posts(int $limit = 100, ?string $status = null): array
{
    $sql = 'SELECT * FROM social_posts';
    $params = [];
    if ($status !== null) {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC LIMIT ' . max(1, min(500, $limit));
    $stmt = social_bot_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function social_bot_queue_posts(): array
{
    return social_bot_pdo()->query("
        SELECT * FROM social_posts WHERE status IN ('pending', 'approved')
        ORDER BY created_at DESC LIMIT 50
    ")->fetchAll();
}

function social_bot_replies(int $limit = 100): array
{
    $stmt = social_bot_pdo()->prepare('SELECT * FROM social_reply_log ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function social_bot_templates(): array
{
    return social_bot_pdo()->query('SELECT * FROM social_templates ORDER BY tone, name')->fetchAll();
}

function social_bot_audit(int $limit = 200): array
{
    $stmt = social_bot_pdo()->prepare('SELECT * FROM social_audit_log ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function social_bot_toggle_platform(string $platform, bool $enabled): void
{
    social_bot_pdo()->prepare('
        UPDATE social_platform_accounts SET enabled = ?, updated_at = datetime(\'now\') WHERE platform = ?
    ')->execute([$enabled ? 1 : 0, $platform]);
}

function social_bot_update_template(int $id, string $body, bool $enabled, int $weight): void
{
    social_bot_pdo()->prepare('
        UPDATE social_templates SET template_body = ?, enabled = ?, weight = ?, updated_at = datetime(\'now\') WHERE id = ?
    ')->execute([$body, $enabled ? 1 : 0, max(1, $weight), $id]);
}

function social_bot_reject_post(int $id): void
{
    social_bot_pdo()->prepare("UPDATE social_posts SET status = 'rejected' WHERE id = ?")->execute([$id]);
}

function social_bot_reject_reply(int $id): void
{
    social_bot_pdo()->prepare("UPDATE social_reply_log SET status = 'rejected' WHERE id = ?")->execute([$id]);
}

function social_bot_env_groups(): array
{
    return [
        ['label' => 'Discord', 'vars' => ['DISCORD_ENABLED', 'DISCORD_BOT_TOKEN', 'DISCORD_WEBHOOK_URL', 'DISCORD_CHANNEL_ID', 'DISCORD_REPLY_CHANNEL_IDS']],
        ['label' => 'X (Twitter)', 'vars' => ['X_ENABLED', 'X_API_KEY', 'X_API_SECRET', 'X_ACCESS_TOKEN', 'X_ACCESS_SECRET', 'X_BEARER_TOKEN', 'X_REPLY_ENABLED']],
        ['label' => 'Facebook', 'vars' => ['FACEBOOK_ENABLED', 'FACEBOOK_PAGE_ID', 'FACEBOOK_PAGE_ACCESS_TOKEN', 'FACEBOOK_REPLY_ENABLED']],
        ['label' => 'AI (optional)', 'vars' => ['AI_ENABLED', 'AI_API_KEY', 'AI_BASE_URL', 'AI_MODEL']],
        ['label' => 'Integration', 'vars' => ['SOCIAL_BOT_INTERNAL_TOKEN', 'SOCIAL_BOT_DB_PATH', 'SOCIAL_BOT_API_URL']],
    ];
}

function social_bot_service_health(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $url = social_bot_api_url() . '/health';
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $cached = ['ok' => false, 'message' => 'Node service unreachable at ' . social_bot_api_url()];
        return $cached;
    }
    $data = json_decode($body, true);
    $cached = is_array($data) ? $data : ['ok' => false, 'message' => 'Invalid health response'];
    return $cached;
}

function social_bot_api_call(string $method, string $path, ?array $body = null, int $timeoutSeconds = 45): array
{
    $token = social_bot_internal_token();
    if ($token === '') {
        throw new RuntimeException('SOCIAL_BOT_INTERNAL_TOKEN is not configured in /social-bot/.env or data/internal-token.');
    }

    $url = social_bot_api_url() . $path;
    $headers = [
        'Content-Type: application/json',
        'X-Social-Bot-Token: ' . $token,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => max(2, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => 3,
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : '{}';
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : '',
                'timeout' => max(2, $timeoutSeconds),
                'ignore_errors' => true,
            ],
        ];
        $response = @file_get_contents($url, false, stream_context_create($opts));
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
    }

    if ($response === false) {
        throw new RuntimeException('Social bot API request failed: ' . $method . ' ' . $path);
    }

    $data = json_decode((string)$response, true);
    if ($code >= 400) {
        $msg = is_array($data) ? (string)($data['error'] ?? $data['message'] ?? 'API error') : 'API error';
        throw new RuntimeException($msg . ' (HTTP ' . $code . ')');
    }

    return is_array($data) ? $data : ['ok' => true];
}

function social_bot_publish_post(int $id, string $actor = 'admin'): array
{
    return social_bot_api_call('POST', '/internal/posts/' . $id . '/publish', ['actor' => $actor]);
}

function social_bot_preview_via_cli(string $platform = 'discord', ?string $topic = null, bool $useAi = false): array
{
    $node = '/usr/bin/node';
    if (!is_executable($node)) {
        $node = trim((string)shell_exec('command -v node'));
    }
    if ($node === '') {
        throw new RuntimeException('Node.js not found for local preview.');
    }
    $script = '/home/hobbyhashcoin/social-bot/src/scripts/preview-cli.js';
    $nodeCmd = escapeshellarg($node) . ' ' . escapeshellarg($script) . ' '
        . escapeshellarg($platform) . ' '
        . escapeshellarg($topic ?? '') . ' '
        . escapeshellarg($useAi ? '1' : '0');
    $timeoutBin = trim((string)shell_exec('command -v timeout'));
    $cmd = ($timeoutBin !== '' ? escapeshellarg($timeoutBin) . ' 25 ' : '') . $nodeCmd;
    $output = shell_exec($cmd . ' 2>&1');
    if ($output === null || $output === '') {
        throw new RuntimeException('Preview script returned no output (timed out after 25s?).');
    }
    if (str_contains($output, 'Preview timed out')) {
        throw new RuntimeException('Preview timed out after 25 seconds. Try again or check the Node service.');
    }
    $data = json_decode(trim($output), true);
    if (!is_array($data) || empty($data['ok'])) {
        throw new RuntimeException('Preview failed: ' . substr($output, 0, 300));
    }
    return $data;
}

function social_bot_preview_post(string $platform = 'discord', ?string $topic = null, bool $useAi = false): array
{
    try {
        if (social_bot_service_health()['ok'] ?? false) {
            return social_bot_api_call('POST', '/internal/preview', [
                'platform' => $platform,
                'topic' => $topic,
                'useAi' => $useAi,
            ], 25);
        }
    } catch (Throwable $e) {
        // fall through to CLI fallback
    }
    return social_bot_preview_via_cli($platform, $topic, $useAi);
}

function social_bot_run_scheduler(string $actor = 'admin'): array
{
    return social_bot_api_call('POST', '/internal/scheduler/run', ['actor' => $actor]);
}

function social_bot_approve_reply(int $id, string $actor = 'admin'): array
{
    return social_bot_api_call('POST', '/internal/replies/' . $id . '/approve', ['actor' => $actor]);
}

function social_bot_collector_status(): ?array
{
    try {
        return social_bot_api_call('GET', '/internal/collectors', null, 8);
    } catch (Throwable $e) {
        return null;
    }
}

function social_bot_dry_run_enabled(): bool
{
    $v = social_bot_setting('dry_run_mode', true);
    return $v !== false && $v !== 'false' && $v !== 0;
}

function social_bot_platform_credentials(): array
{
    $defaults = [
        'discord' => ['enabled' => false, 'botToken' => '', 'webhookUrl' => '', 'channelId' => '', 'replyChannelIds' => ''],
        'x' => ['enabled' => false, 'apiKey' => '', 'apiSecret' => '', 'accessToken' => '', 'accessSecret' => '', 'bearerToken' => '', 'oauth2ClientId' => '', 'oauth2ClientSecret' => '', 'oauth2RedirectUri' => '', 'oauth2AccessToken' => '', 'oauth2RefreshToken' => '', 'oauth2ExpiresAt' => 0, 'replyEnabled' => false],
        'facebook' => ['enabled' => false, 'appId' => '', 'appSecret' => '', 'oauthRedirectUri' => '', 'pageId' => '', 'pageAccessToken' => '', 'pageName' => '', 'replyEnabled' => false],
    ];
    $stored = social_bot_setting('platform_credentials', []);
    if (!is_array($stored)) {
        $stored = [];
    }
    foreach ($defaults as $platform => $fields) {
        $stored[$platform] = array_merge($fields, is_array($stored[$platform] ?? null) ? $stored[$platform] : []);
    }
    return $stored;
}

function social_bot_ai_config(): array
{
    $defaults = [
        'enabled' => false,
        'provider' => 'none',
        'use_probability' => 1,
        'always_use_for_posts' => true,
        'replies_use_ai' => true,
        'openai' => ['apiKey' => '', 'model' => 'gpt-4o-mini'],
        'anthropic' => ['apiKey' => '', 'model' => 'claude-3-5-haiku-latest'],
    ];
    $stored = social_bot_setting('ai_config', []);
    if (!is_array($stored)) {
        return $defaults;
    }
    return array_replace_recursive($defaults, $stored);
}

function social_bot_site_urls(): array
{
    $defaults = [
        'siteUrl' => 'https://hobbyhashcoin.com',
        'docsUrl' => 'https://hobbyhashcoin.com/docs/',
        'poolUrl' => 'https://hobbyhashcoin.com/pool/',
        'explorerUrl' => 'https://hobbyhashcoin.com/explorer/',
        'walletUrl' => 'https://hobbyhashcoin.com/wallet/',
        'downloadsUrl' => 'https://hobbyhashcoin.com/downloads/',
        'supportUrl' => 'https://hobbyhashcoin.com/support/',
    ];
    $stored = social_bot_setting('site_urls', []);
    return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
}

function social_bot_utm_settings(): array
{
    $defaults = [
        'enabled' => true,
        'medium' => 'social',
        'campaign' => 'hobc_update_bot',
        'sources' => [
            'discord' => 'discord',
            'x' => 'twitter',
            'facebook' => 'facebook',
        ],
    ];
    $stored = social_bot_setting('utm_settings', []);
    if (!is_array($stored)) {
        return $defaults;
    }
    return array_replace_recursive($defaults, $stored);
}

function social_bot_merge_secret(string $submitted, string $existing): string
{
    $submitted = trim($submitted);
    if ($submitted === '' || $submitted === '__KEEP__') {
        return $existing;
    }
    return $submitted;
}

function social_bot_secret_hint(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= 8) {
        return '(saved — leave blank to keep)';
    }
    return '(saved ' . substr($value, 0, 4) . '…' . substr($value, -4) . ' — leave blank to keep)';
}

function social_bot_save_platform_credentials(array $post, string $actor): void
{
    $current = social_bot_platform_credentials();
    $discord = $current['discord'];
    $discord['enabled'] = isset($post['discord_enabled']);
    $discord['botToken'] = social_bot_merge_secret((string)($post['discord_bot_token'] ?? ''), $discord['botToken']);
    $discord['webhookUrl'] = social_bot_merge_secret((string)($post['discord_webhook_url'] ?? ''), $discord['webhookUrl']);
    $discord['channelId'] = trim((string)($post['discord_channel_id'] ?? $discord['channelId']));
    $discord['replyChannelIds'] = trim((string)($post['discord_reply_channel_ids'] ?? $discord['replyChannelIds']));

    $x = $current['x'];
    $x['enabled'] = isset($post['x_enabled']);
    $x['apiKey'] = social_bot_merge_secret((string)($post['x_api_key'] ?? ''), $x['apiKey']);
    $x['apiSecret'] = social_bot_merge_secret((string)($post['x_api_secret'] ?? ''), $x['apiSecret']);
    $x['accessToken'] = social_bot_merge_secret((string)($post['x_access_token'] ?? ''), $x['accessToken']);
    $x['accessSecret'] = social_bot_merge_secret((string)($post['x_access_secret'] ?? ''), $x['accessSecret']);
    $x['bearerToken'] = social_bot_merge_secret((string)($post['x_bearer_token'] ?? ''), $x['bearerToken']);
    $x['oauth2ClientId'] = trim((string)($post['x_oauth2_client_id'] ?? $x['oauth2ClientId'] ?? ''));
    $x['oauth2ClientSecret'] = social_bot_merge_secret((string)($post['x_oauth2_client_secret'] ?? ''), $x['oauth2ClientSecret'] ?? '');
    $x['oauth2RedirectUri'] = trim((string)($post['x_oauth2_redirect_uri'] ?? $x['oauth2RedirectUri'] ?? ''));
    if ($x['oauth2RedirectUri'] === '') {
        $x['oauth2RedirectUri'] = social_bot_x_oauth_redirect_uri();
    }
    $x['replyEnabled'] = isset($post['x_reply_enabled']);

    $facebook = $current['facebook'];
    $facebook['enabled'] = isset($post['facebook_enabled']);
    $facebook['appId'] = trim((string)($post['facebook_app_id'] ?? $facebook['appId'] ?? ''));
    $facebook['appSecret'] = social_bot_merge_secret((string)($post['facebook_app_secret'] ?? ''), $facebook['appSecret'] ?? '');
    $facebook['oauthRedirectUri'] = trim((string)($post['facebook_oauth_redirect_uri'] ?? $facebook['oauthRedirectUri'] ?? ''));
    if ($facebook['oauthRedirectUri'] === '') {
        $facebook['oauthRedirectUri'] = social_bot_facebook_oauth_redirect_uri();
    }
    $facebook['pageId'] = trim((string)($post['facebook_page_id'] ?? $facebook['pageId']));
    $facebook['pageAccessToken'] = social_bot_merge_secret((string)($post['facebook_page_access_token'] ?? ''), $facebook['pageAccessToken']);
    $facebook['replyEnabled'] = isset($post['facebook_reply_enabled']);

    social_bot_set_setting('platform_credentials', [
        'discord' => $discord,
        'x' => $x,
        'facebook' => $facebook,
    ], $actor);

    social_bot_pdo()->prepare('UPDATE social_platform_accounts SET enabled = ?, updated_at = datetime(\'now\') WHERE platform = ?')
        ->execute([$discord['enabled'] ? 1 : 0, 'discord']);
    social_bot_pdo()->prepare('UPDATE social_platform_accounts SET enabled = ?, updated_at = datetime(\'now\') WHERE platform = ?')
        ->execute([$x['enabled'] ? 1 : 0, 'x']);
    social_bot_pdo()->prepare('UPDATE social_platform_accounts SET enabled = ?, updated_at = datetime(\'now\') WHERE platform = ?')
        ->execute([$facebook['enabled'] ? 1 : 0, 'facebook']);
}

function social_bot_save_ai_config(array $post, string $actor): void
{
    $current = social_bot_ai_config();
    $provider = (string)($post['ai_provider'] ?? 'none');
    if (!in_array($provider, ['none', 'openai', 'anthropic'], true)) {
        $provider = 'none';
    }

    social_bot_set_setting('ai_config', [
        'enabled' => isset($post['ai_enabled']) && $provider !== 'none',
        'provider' => $provider,
        'use_probability' => (float)($post['ai_use_probability'] ?? 1),
        'always_use_for_posts' => isset($post['ai_always_posts']),
        'replies_use_ai' => isset($post['ai_replies']),
        'openai' => [
            'apiKey' => social_bot_merge_secret((string)($post['openai_api_key'] ?? ''), $current['openai']['apiKey']),
            'model' => trim((string)($post['openai_model'] ?? $current['openai']['model'])),
        ],
        'anthropic' => [
            'apiKey' => social_bot_merge_secret((string)($post['anthropic_api_key'] ?? ''), $current['anthropic']['apiKey']),
            'model' => trim((string)($post['anthropic_model'] ?? $current['anthropic']['model'])),
        ],
    ], $actor);
}

function social_bot_x_budget_settings(): array
{
    $defaults = [
        'enabled' => true,
        'daily_budget_usd' => 5,
        'max_posts_with_url_per_day' => 3,
        'max_replies_with_url_per_day' => 2,
        'reply_link_probability' => 0.15,
        'plain_create_usd' => 0.015,
        'url_create_usd' => 0.2,
        'owned_read_usd' => 0.001,
        'post_read_usd' => 0.005,
        'user_read_usd' => 0.01,
        'mention_search_fallback' => false,
        'dedupe_reads_utc' => true,
    ];
    $stored = social_bot_setting('x_budget_settings', []);
    if (!is_array($stored)) {
        $stored = [];
    }

    return array_merge($defaults, $stored);
}

function social_bot_x_daily_usage(): array
{
    $today = admin_local_date('Y-m-d');
    $fresh = [
        'date' => $today,
        'spent_usd' => 0,
        'plain_creates' => 0,
        'url_creates' => 0,
        'posts_with_url' => 0,
        'replies_with_url' => 0,
        'posts_plain' => 0,
        'replies_plain' => 0,
        'reads_owned' => 0,
        'reads_public' => 0,
        'reads_deduped' => 0,
        'read_cost_usd' => 0,
        'create_cost_usd' => 0,
        'blocked' => 0,
    ];
    $stored = social_bot_setting('x_daily_usage', []);
    if (!is_array($stored) || (string)($stored['date'] ?? '') !== $today) {
        return $fresh;
    }

    return array_merge($fresh, $stored);
}

function social_bot_x_budget_summary(): array
{
    $settings = social_bot_x_budget_settings();
    $usage = social_bot_x_daily_usage();
    $budget = (float)($settings['daily_budget_usd'] ?? 5);
    $spent = (float)($usage['spent_usd'] ?? 0);
    $remaining = max(0, $budget - $spent);
    $plainRate = (float)($settings['plain_create_usd'] ?? 0.015);
    $urlRate = (float)($settings['url_create_usd'] ?? 0.2);
    $ownedReadRate = (float)($settings['owned_read_usd'] ?? 0.001);

    return [
        'settings' => $settings,
        'usage' => $usage,
        'spent_usd' => $spent,
        'read_cost_usd' => (float)($usage['read_cost_usd'] ?? 0),
        'create_cost_usd' => (float)($usage['create_cost_usd'] ?? 0),
        'remaining_usd' => round($remaining, 2),
        'budget_usd' => $budget,
        'posts_with_url_left' => max(0, (int)($settings['max_posts_with_url_per_day'] ?? 0) - (int)($usage['posts_with_url'] ?? 0)),
        'replies_with_url_left' => max(0, (int)($settings['max_replies_with_url_per_day'] ?? 0) - (int)($usage['replies_with_url'] ?? 0)),
        'estimated_plain_left' => $plainRate > 0 ? (int)floor($remaining / $plainRate) : 0,
        'estimated_url_left' => $urlRate > 0 ? (int)floor($remaining / $urlRate) : 0,
        'estimated_owned_reads_left' => $ownedReadRate > 0 ? (int)floor($remaining / $ownedReadRate) : 0,
    ];
}

function social_bot_save_x_budget_settings(array $post, string $actor): void
{
    social_bot_set_setting('x_budget_settings', [
        'enabled' => isset($post['x_budget_enabled']),
        'daily_budget_usd' => max(0, (float)($post['x_daily_budget_usd'] ?? 5)),
        'max_posts_with_url_per_day' => max(0, (int)($post['x_max_posts_with_url'] ?? 3)),
        'max_replies_with_url_per_day' => max(0, (int)($post['x_max_replies_with_url'] ?? 2)),
        'reply_link_probability' => min(1, max(0, (float)($post['x_reply_link_probability'] ?? 0.15))),
        'plain_create_usd' => max(0, (float)($post['x_plain_create_usd'] ?? 0.015)),
        'url_create_usd' => max(0, (float)($post['x_url_create_usd'] ?? 0.2)),
        'owned_read_usd' => max(0, (float)($post['x_owned_read_usd'] ?? 0.001)),
        'post_read_usd' => max(0, (float)($post['x_post_read_usd'] ?? 0.005)),
        'user_read_usd' => max(0, (float)($post['x_user_read_usd'] ?? 0.01)),
        'mention_search_fallback' => isset($post['x_mention_search_fallback']),
        'dedupe_reads_utc' => isset($post['x_dedupe_reads_utc']),
    ], $actor);
}

function social_bot_x_usage_daily(int $days = 14): array
{
    $days = max(1, min(90, $days));
    $byDay = [];
    try {
        $stmt = social_bot_pdo()->query('
            SELECT created_at, cost_usd
            FROM social_x_usage_log
            ORDER BY created_at ASC
        ');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ts = admin_utc_datetime_timestamp((string)($row['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            $day = admin_local_date('Y-m-d', $ts);
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['spent_usd' => 0.0, 'creates' => 0];
            }
            $byDay[$day]['spent_usd'] += (float)($row['cost_usd'] ?? 0);
            $byDay[$day]['creates']++;
        }
    } catch (Throwable) {
        return [];
    }

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = admin_local_date('Y-m-d', time() - $i * 86400);
        $row = $byDay[$day] ?? null;
        $out[] = [
            'day' => $day,
            'label' => admin_local_day_label($day),
            'spent_usd' => round((float)($row['spent_usd'] ?? 0), 4),
            'creates' => (int)($row['creates'] ?? 0),
        ];
    }

    return $out;
}

function social_bot_x_usage_today_hourly(): array
{
    $bounds = admin_local_day_bounds();
    $byHour = array_fill(0, 24, 0.0);
    try {
        $stmt = social_bot_pdo()->prepare('
            SELECT created_at, cost_usd
            FROM social_x_usage_log
            WHERE created_at >= ? AND created_at < ?
            ORDER BY created_at ASC
        ');
        $stmt->execute([$bounds['start_utc'], $bounds['end_utc']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ts = admin_utc_datetime_timestamp((string)($row['created_at'] ?? ''));
            if ($ts === null) {
                continue;
            }
            $hour = (int)admin_local_date('G', $ts);
            if ($hour >= 0 && $hour <= 23) {
                $byHour[$hour] += (float)($row['cost_usd'] ?? 0);
            }
        }
    } catch (Throwable) {
        return [];
    }

    $out = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $out[] = [
            'label' => admin_local_hour_label($hour),
            'spent_usd' => round($byHour[$hour], 4),
        ];
    }

    return $out;
}

function social_bot_x_usage_today_breakdown(): array
{
    $usage = social_bot_x_daily_usage();

    return [
        ['label' => 'Owned reads', 'value' => (int)($usage['reads_owned'] ?? 0)],
        ['label' => 'Public reads', 'value' => (int)($usage['reads_public'] ?? 0)],
        ['label' => 'Plain posts', 'value' => (int)($usage['posts_plain'] ?? 0)],
        ['label' => 'Posts with URL', 'value' => (int)($usage['posts_with_url'] ?? 0)],
        ['label' => 'Plain replies', 'value' => (int)($usage['replies_plain'] ?? 0)],
        ['label' => 'Replies with URL', 'value' => (int)($usage['replies_with_url'] ?? 0)],
    ];
}

function social_bot_x_usage_cost_breakdown(array $usage): array
{
    return [
        ['label' => 'Reads', 'value' => round((float)($usage['read_cost_usd'] ?? 0), 4)],
        ['label' => 'Creates', 'value' => round((float)($usage['create_cost_usd'] ?? 0), 4)],
    ];
}

function social_bot_x_usage_log_rows(int $limit = 40): array
{
    $limit = max(1, min(200, $limit));
    try {
        $stmt = social_bot_pdo()->prepare('
            SELECT usage_date, action_type, has_url, cost_usd, reference_id, created_at
            FROM social_x_usage_log
            ORDER BY created_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function social_bot_x_budget_dashboard(int $historyDays = 14): array
{
    $summary = social_bot_x_budget_summary();
    $settings = $summary['settings'] ?? social_bot_x_budget_settings();
    $usage = $summary['usage'] ?? social_bot_x_daily_usage();

    return [
        'summary' => $summary,
        'settings' => $settings,
        'usage' => $usage,
        'daily' => social_bot_x_usage_daily($historyDays),
        'hourly' => social_bot_x_usage_today_hourly(),
        'breakdown' => social_bot_x_usage_today_breakdown(),
        'cost_breakdown' => social_bot_x_usage_cost_breakdown($usage),
        'url_caps' => [
            ['label' => 'Posts with URL', 'used' => (int)($usage['posts_with_url'] ?? 0), 'max' => (int)($settings['max_posts_with_url_per_day'] ?? 0)],
            ['label' => 'Replies with URL', 'used' => (int)($usage['replies_with_url'] ?? 0), 'max' => (int)($settings['max_replies_with_url_per_day'] ?? 0)],
        ],
        'recent_log' => social_bot_x_usage_log_rows(150),
    ];
}

function social_bot_render_x_budget_meter(float $spent, float $budget, string $title = 'Budget used today'): void
{
    $budget = max(0.0001, $budget);
    $spentPct = min(100, round(($spent / $budget) * 100, 1));
    $remain = max(0, $budget - $spent);
    $remainPct = min(100, round(($remain / $budget) * 100, 1));

    echo '<div class="admin-card"><h3>' . h($title) . '</h3>';
    echo '<div class="analytics-chart">';
    echo '<div class="analytics-bar-row"><span>Spent</span><div><b style="width:' . h((string)$spentPct) . '%;background:linear-gradient(90deg,#e8a317,#f06)"></b></div><strong>$' . h(number_format($spent, 2)) . '</strong></div>';
    echo '<div class="analytics-bar-row"><span>Remaining</span><div><b style="width:' . h((string)$remainPct) . '%;background:linear-gradient(90deg,#3d8f5f,#6fd39a)"></b></div><strong>$' . h(number_format($remain, 2)) . '</strong></div>';
    echo '</div>';
    echo '<p class="admin-muted" style="margin-top:0.75rem">Daily cap $' . h(number_format($budget, 2)) . ' · Resets at local midnight (' . h(admin_timezone_label()) . ') · ' . h((string)$spentPct) . '% used</p>';
    echo '</div>';
}

function social_bot_render_x_budget_pie(array $slices, string $title): void
{
    $filtered = [];
    $total = 0.0;
    foreach ($slices as $slice) {
        $value = (float)($slice['value'] ?? 0);
        if ($value <= 0) {
            continue;
        }
        $filtered[] = ['label' => (string)($slice['label'] ?? ''), 'value' => $value];
        $total += $value;
    }

    if ($filtered === []) {
        echo '<div class="admin-card"><h3>' . h($title) . '</h3>';
        admin_empty_state('No usage yet', 'Charts fill in after the bot publishes posts or replies on X.');
        echo '</div>';
        return;
    }

    $colors = ['#f6b928', '#8dc7ff', '#3d8f5f', '#f06', '#b48cff', '#ff9f43'];
    $segments = [];
    $offset = 0.0;
    foreach ($filtered as $index => $slice) {
        $pct = ($slice['value'] / $total) * 100;
        $color = $colors[$index % count($colors)];
        $segments[] = h($color) . ' ' . $offset . '% ' . ($offset + $pct) . '%';
        $offset += $pct;
    }

    echo '<div class="admin-card"><h3>' . h($title) . '</h3>';
    echo '<div class="analytics-pie-wrap">';
    echo '<div class="analytics-pie" style="background:conic-gradient(' . implode(', ', $segments) . ');"></div>';
    echo '<ul class="analytics-pie-legend">';
    foreach ($filtered as $index => $slice) {
        $pct = round(($slice['value'] / $total) * 100, 1);
        $color = $colors[$index % count($colors)];
        echo '<li><span class="analytics-pie-dot" style="background:' . h($color) . ';"></span>';
        echo '<span class="analytics-pie-label">' . h($slice['label']) . '</span>';
        if (str_contains($title, '$')) {
            echo '<strong>$' . h(number_format($slice['value'], 2)) . '</strong>';
        } else {
            echo '<strong>' . h(number_format($slice['value'], 0)) . '</strong>';
        }
        echo '<em>' . h((string)$pct) . '%</em></li>';
    }
    echo '</ul></div></div>';
}

function social_bot_render_x_budget_bar_chart(array $rows, string $labelKey, string $valueKey, string $title, bool $asCurrency = false): void
{
    if ($rows === []) {
        echo '<div class="admin-card"><h3>' . h($title) . '</h3>';
        admin_empty_state('No chart data yet', 'Usage charts appear after X API creates are logged.');
        echo '</div>';
        return;
    }

    $max = 0.0001;
    foreach ($rows as $row) {
        $max = max($max, (float)($row[$valueKey] ?? 0));
    }

    echo '<div class="admin-card"><h3>' . h($title) . '</h3><div class="analytics-chart">';
    foreach ($rows as $row) {
        $value = (float)($row[$valueKey] ?? 0);
        $width = max(2, (int)round(($value / $max) * 100));
        $display = $asCurrency ? ('$' . number_format($value, 2)) : number_format($value, $value >= 10 ? 0 : 2);
        echo '<div class="analytics-bar-row"><span>' . h((string)($row[$labelKey] ?? '')) . '</span>';
        echo '<div><b style="width:' . h((string)$width) . '%"></b></div>';
        echo '<strong>' . h($display) . '</strong></div>';
    }
    echo '</div></div>';
}

function social_bot_render_x_budget_cap_chart(array $caps, string $title): void
{
    if ($caps === []) {
        echo '<div class="admin-card"><h3>' . h($title) . '</h3>';
        admin_empty_state('No caps configured', 'Set URL post and reply limits in the form below.');
        echo '</div>';
        return;
    }

    echo '<div class="admin-card"><h3>' . h($title) . '</h3><div class="analytics-chart">';
    foreach ($caps as $cap) {
        $used = (int)($cap['used'] ?? 0);
        $max = max(1, (int)($cap['max'] ?? 1));
        $width = min(100, max(2, (int)round(($used / $max) * 100)));
        echo '<div class="analytics-bar-row"><span>' . h((string)($cap['label'] ?? '')) . '</span>';
        echo '<div><b style="width:' . h((string)$width) . '%"></b></div>';
        echo '<strong>' . h((string)$used) . ' / ' . h((string)$max) . '</strong></div>';
    }
    echo '</div></div>';
}

function social_bot_status_badge(string $status): string
{
    return '<span class="module-status">' . h($status) . '</span>';
}

function social_bot_x_oauth_redirect_uri(): string
{
    return social_bot_oauth_absolute_url('/social-bot-x-oauth.php');
}

function social_bot_oauth_absolute_url(string $adminPath): string
{
    $path = admin_url($adminPath);
    if (isset($_SERVER['HTTP_HOST']) && trim((string)$_SERVER['HTTP_HOST']) !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $path;
    }
    $site = social_bot_site_urls();

    return rtrim((string)$site['siteUrl'], '/') . $path;
}

function social_bot_facebook_oauth_redirect_uri(): string
{
    return social_bot_oauth_absolute_url('/social-bot-facebook-oauth.php');
}

function social_bot_x_oauth_pkce_start(string $actor): string
{
    $x = social_bot_platform_credentials()['x'];
    if (trim((string)($x['oauth2ClientId'] ?? '')) === '' || trim((string)($x['oauth2ClientSecret'] ?? '')) === '') {
        throw new RuntimeException('X OAuth 2.0 Client ID and Client Secret are required.');
    }

    $redirect = trim((string)($x['oauth2RedirectUri'] ?? ''));
    if ($redirect === '') {
        $redirect = social_bot_x_oauth_redirect_uri();
    }

    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $state = bin2hex(random_bytes(16));

    social_bot_set_setting('x_oauth_pkce', [
        'state' => $state,
        'codeVerifier' => $verifier,
        'expiresAt' => time() + 600,
    ], $actor);

    $params = [
        'response_type' => 'code',
        'client_id' => trim((string)$x['oauth2ClientId']),
        'redirect_uri' => $redirect,
        'scope' => 'tweet.read tweet.write users.read offline.access',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];

    return 'https://x.com/i/oauth2/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function social_bot_x_oauth_handle_manual_code(string $rawInput, string $actor): void
{
    $raw = trim($rawInput);
    if ($raw === '') {
        throw new RuntimeException('Paste the full redirect URL or authorization code from X.');
    }

    $code = $raw;
    if (str_contains($raw, 'http://') || str_contains($raw, 'https://')) {
        $parts = parse_url($raw);
        parse_str((string)($parts['query'] ?? ''), $query);
        $code = trim((string)($query['code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('No code= parameter found in the pasted URL.');
        }
    }

    $pkce = social_bot_setting('x_oauth_pkce', null);
    if (!is_array($pkce) || empty($pkce['codeVerifier'])) {
        throw new RuntimeException('OAuth session expired. Click Connect X (OAuth 2.0) again, authorize, then paste the redirect URL here quickly.');
    }

    social_bot_x_oauth_handle_callback([
        'code' => $code,
        'state' => (string)($pkce['state'] ?? ''),
    ], $actor);
}

function social_bot_x_oauth_handle_callback(array $query, string $actor): void
{
    $error = trim((string)($query['error'] ?? ''));
    if ($error !== '') {
        throw new RuntimeException('X authorization denied: ' . $error);
    }

    $code = trim((string)($query['code'] ?? ''));
    $state = trim((string)($query['state'] ?? ''));
    if ($code === '' || $state === '') {
        throw new RuntimeException('Missing authorization code or state from X.');
    }

    $pkce = social_bot_setting('x_oauth_pkce', null);
    if (!is_array($pkce) || ($pkce['state'] ?? '') !== $state) {
        throw new RuntimeException('OAuth state mismatch. Start connect again from admin.');
    }
    if (!empty($pkce['expiresAt']) && time() > (int)$pkce['expiresAt']) {
        throw new RuntimeException('OAuth session expired. Start connect again from admin.');
    }

    $creds = social_bot_platform_credentials();
    $x = $creds['x'];
    $clientId = trim((string)($x['oauth2ClientId'] ?? ''));
    $clientSecret = trim((string)($x['oauth2ClientSecret'] ?? ''));
    $redirect = trim((string)($x['oauth2RedirectUri'] ?? ''));
    if ($redirect === '') {
        $redirect = social_bot_x_oauth_redirect_uri();
    }

    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect,
        'code_verifier' => (string)($pkce['codeVerifier'] ?? ''),
    ], '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init('https://api.x.com/2/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
        $detail = is_array($data) ? (string)($data['error_description'] ?? $data['detail'] ?? $data['error'] ?? 'Token exchange failed') : 'Token exchange failed';
        throw new RuntimeException($detail);
    }

    $expiresIn = (int)($data['expires_in'] ?? 7200);
    $x['oauth2AccessToken'] = (string)$data['access_token'];
    if (!empty($data['refresh_token'])) {
        $x['oauth2RefreshToken'] = (string)$data['refresh_token'];
    }
    $x['oauth2ExpiresAt'] = (time() + $expiresIn - 60) * 1000;
    $x['oauth2Scope'] = (string)($data['scope'] ?? '');
    $creds['x'] = $x;
    social_bot_set_setting('platform_credentials', $creds, $actor);
    social_bot_set_setting('x_oauth_pkce', null, $actor);
}

function social_bot_facebook_app_credentials(): array
{
    $fb = social_bot_platform_credentials()['facebook'];
    $redirect = trim((string)($fb['oauthRedirectUri'] ?? ''));
    if ($redirect === '') {
        $redirect = social_bot_facebook_oauth_redirect_uri();
    }

    return [
        'appId' => trim((string)($fb['appId'] ?? '')),
        'appSecret' => trim((string)($fb['appSecret'] ?? '')),
        'redirectUri' => $redirect,
    ];
}

function social_bot_facebook_graph_get(string $path, array $query): array
{
    $url = 'https://graph.facebook.com/v21.0/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $detail = is_array($data) ? (string)($data['error']['message'] ?? $data['error'] ?? 'Facebook API error') : 'Facebook API error';
        throw new RuntimeException($detail . ' (HTTP ' . $status . ')');
    }

    return $data;
}

function social_bot_facebook_debug_token(string $token): array
{
    $app = social_bot_facebook_app_credentials();
    if ($app['appId'] === '' || $app['appSecret'] === '') {
        return [];
    }

    try {
        return social_bot_facebook_graph_get('debug_token', [
            'input_token' => $token,
            'access_token' => $app['appId'] . '|' . $app['appSecret'],
        ]);
    } catch (Throwable $e) {
        return [];
    }
}

function social_bot_facebook_oauth_start(string $actor): string
{
    $app = social_bot_facebook_app_credentials();
    if ($app['appId'] === '' || $app['appSecret'] === '') {
        throw new RuntimeException('Facebook App ID and App Secret are required.');
    }

    $state = bin2hex(random_bytes(16));
    social_bot_set_setting('facebook_oauth_state', [
        'state' => $state,
        'expiresAt' => time() + 600,
    ], $actor);

    $params = [
        'client_id' => $app['appId'],
        'redirect_uri' => $app['redirectUri'],
        'state' => $state,
        'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list',
        'response_type' => 'code',
    ];

    return 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function social_bot_facebook_oauth_handle_callback(array $query, string $actor): void
{
    $error = trim((string)($query['error'] ?? ''));
    if ($error !== '') {
        $desc = trim((string)($query['error_description'] ?? $error));
        throw new RuntimeException('Facebook authorization denied: ' . $desc);
    }

    $code = trim((string)($query['code'] ?? ''));
    $state = trim((string)($query['state'] ?? ''));
    if ($code === '' || $state === '') {
        throw new RuntimeException('Missing authorization code from Facebook. Start Connect again from admin.');
    }

    $stored = social_bot_setting('facebook_oauth_state', null);
    if (!is_array($stored) || ($stored['state'] ?? '') !== $state) {
        throw new RuntimeException('OAuth state mismatch. Start Connect again from admin.');
    }
    if (!empty($stored['expiresAt']) && time() > (int)$stored['expiresAt']) {
        throw new RuntimeException('OAuth session expired. Start Connect again from admin.');
    }

    $app = social_bot_facebook_app_credentials();
    $short = social_bot_facebook_graph_get('oauth/access_token', [
        'client_id' => $app['appId'],
        'client_secret' => $app['appSecret'],
        'redirect_uri' => $app['redirectUri'],
        'code' => $code,
    ]);
    $shortToken = trim((string)($short['access_token'] ?? ''));
    if ($shortToken === '') {
        throw new RuntimeException('Facebook did not return a user access token.');
    }

    $long = social_bot_facebook_graph_get('oauth/access_token', [
        'grant_type' => 'fb_exchange_token',
        'client_id' => $app['appId'],
        'client_secret' => $app['appSecret'],
        'fb_exchange_token' => $shortToken,
    ]);
    $longUserToken = trim((string)($long['access_token'] ?? ''));
    if ($longUserToken === '') {
        throw new RuntimeException('Could not exchange for a long-lived Facebook user token.');
    }

    $accounts = social_bot_facebook_graph_get('me/accounts', [
        'fields' => 'id,name,access_token',
        'access_token' => $longUserToken,
    ]);
    $pages = is_array($accounts['data'] ?? null) ? $accounts['data'] : [];
    if ($pages === []) {
        throw new RuntimeException('No Facebook Pages found for this account. Use a Facebook login that manages the HobbyHash page.');
    }

    $creds = social_bot_platform_credentials();
    $facebook = $creds['facebook'];
    $preferredId = trim((string)($facebook['pageId'] ?? ''));
    $selected = null;
    if ($preferredId !== '') {
        foreach ($pages as $page) {
            if ((string)($page['id'] ?? '') === $preferredId) {
                $selected = $page;
                break;
            }
        }
    }
    if ($selected === null && count($pages) === 1) {
        $selected = $pages[0];
    }
    if ($selected === null) {
        $names = array_map(static fn(array $p): string => (string)($p['name'] ?? $p['id'] ?? '?'), $pages);
        throw new RuntimeException(
            'Multiple Facebook Pages found. Set Page ID to one of: ' . implode(', ', $names) . ', then connect again.'
        );
    }

    $pageToken = trim((string)($selected['access_token'] ?? ''));
    if ($pageToken === '') {
        throw new RuntimeException('Facebook did not return a Page access token.');
    }

    $debug = social_bot_facebook_debug_token($pageToken);
    $expiresAt = (int)($debug['data']['expires_at'] ?? 0);
    if ($expiresAt > 0 && $expiresAt < time() + 3600) {
        throw new RuntimeException('Facebook returned a short-lived Page token. Try Connect again or check app permissions.');
    }

    $facebook['pageId'] = (string)($selected['id'] ?? '');
    $facebook['pageName'] = (string)($selected['name'] ?? '');
    $facebook['pageAccessToken'] = $pageToken;
    $facebook['enabled'] = true;
    $creds['facebook'] = $facebook;
    social_bot_set_setting('platform_credentials', $creds, $actor);
    social_bot_set_setting('facebook_oauth_state', null, $actor);
    social_bot_set_setting('facebook_posting_paused', null, $actor);

    social_bot_pdo()->prepare('UPDATE social_platform_accounts SET enabled = 1, updated_at = datetime(\'now\') WHERE platform = ?')
        ->execute(['facebook']);
}
