<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

const HOBC_ANALYTICS_VISITOR_COOKIE = 'hobc_vid';
const HOBC_ANALYTICS_SESSION_COOKIE = 'hobc_sid';
const HOBC_ANALYTICS_UTM_COOKIE = 'hobc_utm';
const HOBC_ADMIN_DEFAULT_TIMEZONE = 'America/Los_Angeles';

function analytics_setting_bool(string $key, bool $default = true): bool
{
    try {
        return (bool)getSetting($key, $default);
    } catch (Throwable $e) {
        return $default;
    }
}

function analytics_local_timezone(): DateTimeZone
{
    static $timezone = null;
    if ($timezone instanceof DateTimeZone) {
        return $timezone;
    }

    $configured = trim((string)(getenv('HOBC_ADMIN_TIMEZONE') ?: getenv('HOBC_ANALYTICS_TIMEZONE') ?: ''));
    if ($configured === '') {
        try {
            $configured = trim((string)getSetting('analytics.timezone', ''));
        } catch (Throwable $e) {
            $configured = '';
        }
    }
    if ($configured !== '') {
        return $timezone = new DateTimeZone($configured);
    }

    return $timezone = new DateTimeZone(HOBC_ADMIN_DEFAULT_TIMEZONE);
}

function analytics_local_date(string $format = 'Y-m-d', ?int $timestamp = null): string
{
    $timezone = analytics_local_timezone();
    $instant = $timestamp === null
        ? new DateTimeImmutable('now', $timezone)
        : (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);

    return $instant->format($format);
}

function analytics_format_datetime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '' || str_starts_with($value, '0000')) {
        return 'not_available';
    }

    try {
        if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
            $dt = (new DateTimeImmutable($value))->setTimezone(analytics_local_timezone());
        } else {
            $dt = new DateTimeImmutable($value, analytics_local_timezone());
        }

        return $dt->format('M j, Y g:ia');
    } catch (Throwable) {
        return $value;
    }
}

function analytics_h_datetime(?string $value): string
{
    return h(analytics_format_datetime($value));
}

function analytics_timezone_label(): string
{
    $tz = analytics_local_timezone();
    $name = $tz->getName();
    $abbr = (new DateTimeImmutable('now', $tz))->format('T');

    if ($name === HOBC_ADMIN_DEFAULT_TIMEZONE) {
        return 'Los Angeles (' . $abbr . ')';
    }

    if (str_contains($name, '/')) {
        $city = str_replace('_', ' ', (string)substr($name, (int)strrpos($name, '/') + 1));

        return $city . ' (' . $abbr . ')';
    }

    return $abbr !== '' ? $abbr : $name;
}

function analytics_datetime_note(): string
{
    return 'Times shown in ' . analytics_timezone_label() . ' (12-hour local).';
}

function analytics_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = wallet_db()->query("SHOW FULL TABLES LIKE " . wallet_db()->quote($table));
        $cache[$table] = (bool)$stmt->fetch();
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function analytics_column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = wallet_db()->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function analytics_salt(): string
{
    $env = getenv('HOBC_ANALYTICS_SALT');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    try {
        $cfg = wallet_config();
        $configured = $cfg['analytics']['ip_hash_salt'] ?? $cfg['security']['analytics_salt'] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        return hash('sha256', (string)($cfg['db']['password'] ?? '') . '|' . (string)($cfg['app']['session_name'] ?? 'hobc'));
    } catch (Throwable $e) {
        return 'hobc-analytics-local-salt';
    }
}

function analytics_cookie_options(int $ttl): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    return [
        'expires' => time() + $ttl,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function analytics_random_id(): string
{
    return bin2hex(random_bytes(16));
}

function getVisitorId(): string
{
    if (isset($GLOBALS['hobc_analytics_visitor_id']) && is_string($GLOBALS['hobc_analytics_visitor_id']) && $GLOBALS['hobc_analytics_visitor_id'] !== '') {
        return $GLOBALS['hobc_analytics_visitor_id'];
    }

    $value = (string)($_COOKIE[HOBC_ANALYTICS_VISITOR_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
        $value = analytics_random_id();
    }

    if (!headers_sent()) {
        setcookie(HOBC_ANALYTICS_VISITOR_COOKIE, $value, analytics_cookie_options(60 * 60 * 24 * 365));
    }

    $GLOBALS['hobc_analytics_visitor_id'] = $value;
    return $value;
}

function getSessionId(): string
{
    if (isset($GLOBALS['hobc_analytics_session_id']) && is_string($GLOBALS['hobc_analytics_session_id']) && $GLOBALS['hobc_analytics_session_id'] !== '') {
        return $GLOBALS['hobc_analytics_session_id'];
    }

    $value = (string)($_COOKIE[HOBC_ANALYTICS_SESSION_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
        $value = analytics_random_id();
    }

    if (!headers_sent()) {
        setcookie(HOBC_ANALYTICS_SESSION_COOKIE, $value, analytics_cookie_options(60 * 30));
    }

    $GLOBALS['hobc_analytics_session_id'] = $value;
    return $value;
}

function analytics_client_ip(): string
{
    $candidates = [
        (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string)($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim(explode(',', $candidate)[0]);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function hashIpAddress(?string $ip = null): string
{
    $ip = $ip ?: analytics_client_ip();
    return hash_hmac('sha256', $ip, analytics_salt());
}

function analytics_ip_address(): string
{
    return analytics_client_ip();
}

function detectBot(?string $userAgent = null, ?string $url = null): array
{
    $ua = strtolower($userAgent ?? (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $url = strtolower($url ?? (string)($_SERVER['REQUEST_URI'] ?? ''));
    $known = [
        'Googlebot' => ['googlebot', 'google-inspectiontool', 'adsbot-google'],
        'Bingbot' => ['bingbot', 'msnbot'],
        'DuckDuckBot' => ['duckduckbot'],
        'YandexBot' => ['yandexbot', 'yandeximages'],
        'Baiduspider' => ['baiduspider'],
        'Facebook crawler' => ['facebookexternalhit', 'facebot'],
        'Twitter/X crawler' => ['twitterbot'],
        'Discordbot' => ['discordbot'],
        'TelegramBot' => ['telegrambot'],
        'AhrefsBot' => ['ahrefsbot'],
        'SemrushBot' => ['semrushbot'],
        'MJ12bot' => ['mj12bot'],
        'DotBot' => ['dotbot'],
    ];

    foreach ($known as $name => $needles) {
        foreach ($needles as $needle) {
            if ($ua !== '' && str_contains($ua, $needle)) {
                $search = in_array($name, ['Googlebot', 'Bingbot', 'DuckDuckBot', 'YandexBot', 'Baiduspider'], true);
                return ['is_bot' => true, 'bot_name' => $name, 'bot_type' => $search ? 'search_engine' : 'crawler', 'threat_level' => 'info'];
            }
        }
    }

    if ($ua === '' || preg_match('/\b(curl|wget|python|requests|httpclient|libwww|scrapy|go-http-client|java\/|perl|ruby|nikto|masscan|zgrab)\b/i', $ua)) {
        return ['is_bot' => true, 'bot_name' => 'Generic curl/wget/python requests', 'bot_type' => 'generic_script', 'threat_level' => 'medium'];
    }

    $probePattern = '#/(wp-login|xmlrpc\.php|wp-admin|phpmyadmin|adminer|\.env|vendor/phpunit|cgi-bin|boaform|setup\.cgi)#i';
    if (preg_match($probePattern, $url)) {
        return ['is_bot' => true, 'bot_name' => 'Unknown suspicious bot', 'bot_type' => 'probe', 'threat_level' => 'high'];
    }

    if (preg_match('/bot|crawler|spider|scanner|probe/i', $ua)) {
        return ['is_bot' => true, 'bot_name' => 'Unknown suspicious bot', 'bot_type' => 'unknown_bot', 'threat_level' => 'medium'];
    }

    return ['is_bot' => false, 'bot_name' => null, 'bot_type' => 'human', 'threat_level' => 'info'];
}

function analytics_is_known_good_bot(array $bot): bool
{
    return (bool)($bot['is_bot'] ?? false)
        && in_array((string)($bot['bot_type'] ?? ''), ['search_engine', 'crawler'], true)
        && in_array((string)($bot['bot_name'] ?? ''), [
            'Googlebot',
            'Bingbot',
            'DuckDuckBot',
            'YandexBot',
            'Baiduspider',
            'Facebook crawler',
            'Twitter/X crawler',
            'Discordbot',
            'TelegramBot',
        ], true);
}

function analytics_rule_matches(array $rule, string $userAgent, string $ipHash, ?string $botName): bool
{
    $target = match ((string)$rule['target_type']) {
        'ip_hash' => $ipHash,
        'bot_name' => (string)$botName,
        default => $userAgent,
    };
    $pattern = (string)$rule['pattern'];
    if ($pattern === '' || $target === '') {
        return false;
    }
    return match ((string)$rule['match_type']) {
        'exact' => hash_equals($pattern, $target),
        'regex' => @preg_match('#' . $pattern . '#i', $target) === 1,
        default => stripos($target, $pattern) !== false,
    };
}

function analytics_log_rule_hit(array $rule, string $ipHash, string $userAgent, string $url): void
{
    if (analytics_table_exists('bot_rule_hits')) {
        analytics_safe_insert(
            "INSERT INTO bot_rule_hits (rule_id, rule_type, target_type, pattern, ip_hash, user_agent, url)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                (int)$rule['id'],
                (string)$rule['rule_type'],
                (string)$rule['target_type'],
                (string)$rule['pattern'],
                $ipHash,
                substr($userAgent, 0, 2000),
                substr($url, 0, 2000),
            ]
        );
    }
}

function analytics_bot_access_decision(): array
{
    if (!analytics_table_exists('bot_rules')) {
        return ['action' => 'allow', 'rule' => null, 'reason' => 'rules_table_missing'];
    }

    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $url = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $ipHash = hashIpAddress();
    $bot = detectBot($userAgent, $url);

    try {
        $rules = wallet_db()->query("SELECT * FROM bot_rules WHERE status = 'active' ORDER BY rule_type = 'allow' DESC, id ASC")->fetchAll();
    } catch (Throwable $e) {
        wallet_log_error('bot rules read skipped: ' . $e->getMessage());
        return ['action' => 'allow', 'rule' => null, 'reason' => 'rules_read_failed'];
    }

    foreach ($rules as $rule) {
        if ((string)$rule['rule_type'] === 'allow' && analytics_rule_matches($rule, $userAgent, $ipHash, $bot['bot_name'] ?? null)) {
            analytics_log_rule_hit($rule, $ipHash, $userAgent, $url);
            return ['action' => 'allow', 'rule' => $rule, 'reason' => 'allowlist'];
        }
    }

    foreach ($rules as $rule) {
        if ((string)$rule['rule_type'] !== 'block') {
            continue;
        }
        if (!analytics_rule_matches($rule, $userAgent, $ipHash, $bot['bot_name'] ?? null)) {
            continue;
        }
        if (analytics_is_known_good_bot($bot)) {
            return ['action' => 'allow', 'rule' => $rule, 'reason' => 'known_good_protected'];
        }
        analytics_log_rule_hit($rule, $ipHash, $userAgent, $url);
        return ['action' => 'block', 'rule' => $rule, 'reason' => 'blocklist'];
    }

    if (analytics_setting_bool('bot.block_suspicious_user_agents', false)
        && (bool)($bot['is_bot'] ?? false)
        && !analytics_is_known_good_bot($bot)
        && in_array((string)($bot['threat_level'] ?? ''), ['medium', 'high', 'critical'], true)
    ) {
        return ['action' => 'block', 'rule' => ['threat_level' => $bot['threat_level'] ?? 'high'], 'reason' => 'suspicious_user_agent'];
    }

    return ['action' => 'allow', 'rule' => null, 'reason' => 'no_match'];
}

function analytics_enforce_bot_rules(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $decision = analytics_bot_access_decision();
    if (($decision['action'] ?? 'allow') !== 'block') {
        return;
    }
    $rule = is_array($decision['rule'] ?? null) ? $decision['rule'] : [];
    recordBotEvent('Blocked by app rule', 'app_block', 'blocked_request', (string)($rule['threat_level'] ?? 'high'));
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Request blocked by site policy.\n";
    exit;
}

function detectDevice(?string $userAgent = null): string
{
    $ua = strtolower($userAgent ?? (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (preg_match('/ipad|tablet|kindle|silk/', $ua)) {
        return 'tablet';
    }
    if (preg_match('/mobile|iphone|android|blackberry|phone/', $ua)) {
        return 'mobile';
    }
    if ($ua === '') {
        return 'unknown';
    }
    return 'desktop';
}

function detectBrowser(?string $userAgent = null): string
{
    $ua = $userAgent ?? (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return match (true) {
        stripos($ua, 'Edg/') !== false => 'Edge',
        stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false => 'Opera',
        stripos($ua, 'Firefox/') !== false => 'Firefox',
        stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false => 'Chrome',
        stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false => 'Safari',
        stripos($ua, 'bot') !== false || stripos($ua, 'crawler') !== false => 'Bot',
        $ua === '' => 'Unknown',
        default => 'Other',
    };
}

function detectOS(?string $userAgent = null): string
{
    $ua = $userAgent ?? (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return match (true) {
        stripos($ua, 'Windows') !== false => 'Windows',
        stripos($ua, 'Android') !== false => 'Android',
        stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false => 'iOS',
        stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false => 'macOS',
        stripos($ua, 'Linux') !== false => 'Linux',
        $ua === '' => 'Unknown',
        default => 'Other',
    };
}

function analytics_country_code(): ?string
{
    if (is_file(__DIR__ . '/geoip.php')) {
        require_once __DIR__ . '/geoip.php';
        $geo = geoip_lookup();
        if ($geo['country_code'] !== null) {
            return $geo['country_code'];
        }
    }

    $country = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
}

function analytics_geoip_enabled(string $table): bool
{
    return analytics_column_exists($table, 'city_name')
        && analytics_column_exists($table, 'region_name')
        && analytics_column_exists($table, 'asn_number')
        && analytics_column_exists($table, 'asn_org');
}

function analytics_geoip_payload(?string $ip = null): array
{
    if (!is_file(__DIR__ . '/geoip.php')) {
        return [
            'country_code' => analytics_country_code(),
            'city_name' => null,
            'region_name' => null,
            'asn_number' => null,
            'asn_org' => null,
        ];
    }

    require_once __DIR__ . '/geoip.php';
    return geoip_analytics_payload($ip);
}

function analytics_geoip_params(array $geo): array
{
    return [
        $geo['city_name'] ?? null,
        $geo['region_name'] ?? null,
        $geo['asn_number'] ?? null,
        $geo['asn_org'] ?? null,
    ];
}

function analytics_route_name(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    if ($path === '/') {
        return 'home';
    }
    return trim(str_replace('/', '_', trim($path, '/')), '_') ?: 'home';
}

function analytics_pageview_locale(): string
{
    if (function_exists('hobc_i18n_locale')) {
        $locale = trim((string)hobc_i18n_locale());
        if ($locale !== '') {
            return substr($locale, 0, 16);
        }
    }

    return 'en';
}

function analytics_locale_from_url(string $url): string
{
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '/');
    if ($path === '' || $path === '/') {
        return 'en';
    }

    if (!function_exists('hobc_i18n_supported_locales')) {
        return 'en';
    }

    $segment = trim((string)(explode('/', trim($path, '/'))[0] ?? ''), '/');
    if ($segment === '') {
        return 'en';
    }

    foreach (hobc_i18n_supported_locales() as $locale) {
        if ($segment === $locale || $segment === str_replace('_', '-', strtolower($locale))) {
            return substr((string)$locale, 0, 16);
        }
    }

    return 'en';
}

function analytics_sql_locale_label(string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
    if (analytics_column_exists('site_pageviews', 'locale')) {
        return "COALESCE(NULLIF({$prefix}locale, ''), 'en')";
    }

    $cases = [];
    if (function_exists('hobc_i18n_supported_locales')) {
        foreach (hobc_i18n_supported_locales() as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $safe = str_replace("'", "''", (string)$locale);
            $cases[] = "WHEN {$prefix}url LIKE '/{$safe}/%' OR {$prefix}url = '/{$safe}' THEN '{$safe}'";
        }
    }

    if ($cases === []) {
        return "'en'";
    }

    return 'CASE ' . implode(' ', $cases) . " ELSE 'en' END";
}

function analytics_own_site_hosts(): array
{
    static $hosts = null;
    if ($hosts !== null) {
        return $hosts;
    }

    $hosts = ['hobbyhashcoin.com', 'www.hobbyhashcoin.com', 'localhost', '127.0.0.1'];
    $serverHost = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $serverHost = preg_replace('/:\d+$/', '', $serverHost) ?: '';
    if ($serverHost !== '' && !in_array($serverHost, $hosts, true)) {
        $hosts[] = $serverHost;
    }

    return $hosts;
}

function analytics_is_internal_referrer(string $referrer): bool
{
    $referrer = trim($referrer);
    if ($referrer === '') {
        return false;
    }
    if ($referrer[0] === '/') {
        return true;
    }

    $host = parse_url($referrer, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    $host = strtolower($host);
    foreach (analytics_own_site_hosts() as $ownHost) {
        if ($host === $ownHost) {
            return true;
        }
    }

    return $host === 'hobbyhashcoin.com' || str_ends_with($host, '.hobbyhashcoin.com');
}

function analytics_normalize_tracking_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '/';
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = str_starts_with($url, '/') ? $url : '/';
    }
    $query = parse_url($url, PHP_URL_QUERY);
    $safeUrl = $path . (is_string($query) && $query !== '' ? '?' . $query : '');

    return substr($safeUrl, 0, 2000);
}

function analytics_heartbeat_referrer(string $referrer): string
{
    $referrer = trim($referrer);
    if ($referrer === '' || analytics_is_internal_referrer($referrer)) {
        return '';
    }

    return substr($referrer, 0, 2000);
}

function analytics_utm_stored(): array
{
    $raw = (string)($_COOKIE[HOBC_ANALYTICS_UTM_COOKIE] ?? '');
    if ($raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    $stored = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
        $value = analytics_sanitize_utm_value((string)($data[$key] ?? ''));
        if ($value !== '') {
            $stored[$key] = substr($value, 0, 190);
        }
    }

    return $stored;
}

function analytics_sanitize_utm_value(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return rtrim($value, ".,;:!?\"'`)]");
}

function analytics_utm_capture(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $incoming = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
        $value = analytics_sanitize_utm_value((string)($_GET[$key] ?? ''));
        if ($value !== '') {
            $incoming[$key] = substr($value, 0, 190);
        }
    }

    if ($incoming === []) {
        return;
    }

    $merged = array_merge(analytics_utm_stored(), $incoming);
    $encoded = json_encode($merged, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
        return;
    }

    if (!headers_sent()) {
        setcookie(HOBC_ANALYTICS_UTM_COOKIE, $encoded, analytics_cookie_options(60 * 60 * 24 * 30));
    }
    $_COOKIE[HOBC_ANALYTICS_UTM_COOKIE] = $encoded;
}

function analytics_utm(string $key): ?string
{
    $value = analytics_sanitize_utm_value((string)($_GET[$key] ?? ''));
    if ($value !== '') {
        return substr($value, 0, 190);
    }

    $stored = analytics_utm_stored();
    $value = analytics_sanitize_utm_value((string)($stored[$key] ?? ''));
    return $value !== '' ? substr($value, 0, 190) : null;
}

function analytics_safe_insert(string $sql, array $params): void
{
    try {
        $stmt = wallet_db()->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable $e) {
        wallet_log_error('analytics insert skipped: ' . $e->getMessage());
    }
}

function analytics_record_visitor(array $payload): void
{
    if (!analytics_table_exists('site_visitors')) {
        return;
    }

    $currentUrl = analytics_normalize_tracking_url((string)($payload['url'] ?? '/'));
    $currentRoute = substr((string)($payload['route_name'] ?? analytics_route_name($currentUrl)), 0, 190);
    $hasCurrentUrl = analytics_column_exists('site_visitors', 'current_url');
    $hasIpAddress = analytics_table_exists('site_visitors') && analytics_column_exists('site_visitors', 'ip_address');
    $hasGeoIp = analytics_geoip_enabled('site_visitors');
    $geoColumns = $hasGeoIp ? ', city_name, region_name, asn_number, asn_org' : '';
    $geoValues = $hasGeoIp ? ', ?, ?, ?, ?' : '';
    $geoUpdate = $hasGeoIp ? 'city_name = COALESCE(VALUES(city_name), city_name), region_name = COALESCE(VALUES(region_name), region_name), asn_number = COALESCE(VALUES(asn_number), asn_number), asn_org = COALESCE(VALUES(asn_org), asn_org),' : '';
    $currentColumns = $hasCurrentUrl ? ', current_url, current_route_name' : '';
    $currentValues = $hasCurrentUrl ? ', ?, ?' : '';
    $currentUpdate = $hasCurrentUrl ? 'current_url = VALUES(current_url), current_route_name = VALUES(current_route_name),' : '';
    $columns = $hasIpAddress
        ? '(visitor_id, ip_address, first_seen_at, last_seen_at, first_referrer, last_referrer' . $currentColumns . ', pageview_count, is_bot, bot_name, country_code' . $geoColumns . ', device_type, browser_name, os_name)'
        : '(visitor_id, first_seen_at, last_seen_at, first_referrer, last_referrer' . $currentColumns . ', pageview_count, is_bot, bot_name, country_code' . $geoColumns . ', device_type, browser_name, os_name)';
    $values = $hasIpAddress
        ? '(?, ?, NOW(), NOW(), ?, ?' . $currentValues . ', 1, ?, ?, ?' . $geoValues . ', ?, ?, ?)'
        : '(?, NOW(), NOW(), ?, ?' . $currentValues . ', 1, ?, ?, ?' . $geoValues . ', ?, ?, ?)';
    $updateIp = $hasIpAddress ? 'ip_address = VALUES(ip_address),' : '';
    $params = $hasIpAddress ? [
        $payload['visitor_id'],
        $payload['ip_address'],
        $payload['referrer'],
        $payload['referrer'],
    ] : [
        $payload['visitor_id'],
        $payload['referrer'],
        $payload['referrer'],
    ];
    if ($hasCurrentUrl) {
        array_push($params, $currentUrl, $currentRoute);
    }
    array_push(
        $params,
        $payload['is_bot'] ? 1 : 0,
        $payload['bot_name'],
        $payload['country_code'],
    );
    if ($hasGeoIp) {
        array_push($params, ...analytics_geoip_params($payload));
    }
    array_push(
        $params,
        $payload['device_type'],
        $payload['browser_name'],
        $payload['os_name']
    );

    analytics_safe_insert(
        "INSERT INTO site_visitors
            {$columns}
         VALUES {$values}
         ON DUPLICATE KEY UPDATE
            {$updateIp}
            {$geoUpdate}
            {$currentUpdate}
            last_seen_at = NOW(),
            last_referrer = VALUES(last_referrer),
            pageview_count = pageview_count + 1,
            is_bot = VALUES(is_bot),
            bot_name = VALUES(bot_name),
            country_code = COALESCE(VALUES(country_code), country_code),
            device_type = VALUES(device_type),
            browser_name = VALUES(browser_name),
            os_name = VALUES(os_name)",
        $params
    );
}

function analytics_record_heartbeat(array $overrides = []): void
{
    if (PHP_SAPI === 'cli' || !analytics_setting_bool('analytics.enabled', true) || !analytics_table_exists('site_visitors')) {
        return;
    }

    $url = analytics_normalize_tracking_url((string)($overrides['url'] ?? ($_SERVER['REQUEST_URI'] ?? '/')));
    $currentRoute = substr(analytics_route_name($url), 0, 190);
    $userAgent = substr((string)($overrides['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 2000);
    $bot = detectBot($userAgent, $url);
    $geo = analytics_geoip_payload(analytics_ip_address());
    $referrerUpdate = analytics_heartbeat_referrer((string)($overrides['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')));
    $payload = [
        'visitor_id' => getVisitorId(),
        'ip_address' => analytics_ip_address(),
        'referrer' => $referrerUpdate,
        'is_bot' => (bool)$bot['is_bot'],
        'bot_name' => $bot['bot_name'],
        'country_code' => $geo['country_code'],
        'city_name' => $geo['city_name'],
        'region_name' => $geo['region_name'],
        'asn_number' => $geo['asn_number'],
        'asn_org' => $geo['asn_org'],
        'device_type' => detectDevice($userAgent),
        'browser_name' => detectBrowser($userAgent),
        'os_name' => detectOS($userAgent),
    ];

    $hasCurrentUrl = analytics_column_exists('site_visitors', 'current_url');
    $hasIpAddress = analytics_column_exists('site_visitors', 'ip_address');
    $hasGeoIp = analytics_geoip_enabled('site_visitors');
    $geoColumns = $hasGeoIp ? ', city_name, region_name, asn_number, asn_org' : '';
    $geoValues = $hasGeoIp ? ', ?, ?, ?, ?' : '';
    $geoUpdate = $hasGeoIp ? 'city_name = COALESCE(VALUES(city_name), city_name), region_name = COALESCE(VALUES(region_name), region_name), asn_number = COALESCE(VALUES(asn_number), asn_number), asn_org = COALESCE(VALUES(asn_org), asn_org),' : '';
    $currentColumns = $hasCurrentUrl ? ', current_url, current_route_name' : '';
    $currentValues = $hasCurrentUrl ? ', ?, ?' : '';
    $currentUpdate = $hasCurrentUrl ? 'current_url = VALUES(current_url), current_route_name = VALUES(current_route_name),' : '';
    $columns = $hasIpAddress
        ? '(visitor_id, ip_address, first_seen_at, last_seen_at, first_referrer, last_referrer' . $currentColumns . ', pageview_count, is_bot, bot_name, country_code' . $geoColumns . ', device_type, browser_name, os_name)'
        : '(visitor_id, first_seen_at, last_seen_at, first_referrer, last_referrer' . $currentColumns . ', pageview_count, is_bot, bot_name, country_code' . $geoColumns . ', device_type, browser_name, os_name)';
    $values = $hasIpAddress
        ? '(?, ?, NOW(), NOW(), ?, ?' . $currentValues . ', 0, ?, ?, ?' . $geoValues . ', ?, ?, ?)'
        : '(?, NOW(), NOW(), ?, ?' . $currentValues . ', 0, ?, ?, ?' . $geoValues . ', ?, ?, ?)';
    $updateIp = $hasIpAddress ? 'ip_address = VALUES(ip_address),' : '';
    $params = $hasIpAddress ? [
        $payload['visitor_id'],
        $payload['ip_address'],
        $payload['referrer'],
        $payload['referrer'],
    ] : [
        $payload['visitor_id'],
        $payload['referrer'],
        $payload['referrer'],
    ];
    if ($hasCurrentUrl) {
        array_push($params, $url, $currentRoute);
    }
    array_push(
        $params,
        $payload['is_bot'] ? 1 : 0,
        $payload['bot_name'],
        $payload['country_code'],
    );
    if ($hasGeoIp) {
        array_push($params, ...analytics_geoip_params($payload));
    }
    array_push(
        $params,
        $payload['device_type'],
        $payload['browser_name'],
        $payload['os_name']
    );

    analytics_safe_insert(
        "INSERT INTO site_visitors
            {$columns}
         VALUES {$values}
         ON DUPLICATE KEY UPDATE
            {$updateIp}
            {$geoUpdate}
            {$currentUpdate}
            last_seen_at = NOW(),
            last_referrer = CASE WHEN VALUES(last_referrer) <> '' THEN VALUES(last_referrer) ELSE last_referrer END,
            is_bot = VALUES(is_bot),
            bot_name = VALUES(bot_name),
            country_code = COALESCE(VALUES(country_code), country_code),
            device_type = VALUES(device_type),
            browser_name = VALUES(browser_name),
            os_name = VALUES(os_name)",
        $params
    );
}

function recordPageView(array $overrides = []): void
{
    if (PHP_SAPI === 'cli' || !analytics_table_exists('site_pageviews')) {
        return;
    }

    $url = (string)($overrides['url'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
    $normalizedUrl = analytics_normalize_tracking_url($url);
    $userAgent = substr((string)($overrides['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 2000);
    $bot = detectBot($userAgent, $url);
    $status = (int)($overrides['response_status'] ?? http_response_code() ?: 200);
    $ipAddress = analytics_ip_address();
    $geo = analytics_geoip_payload($ipAddress);
    $payload = [
        'session_id' => getSessionId(),
        'visitor_id' => getVisitorId(),
        'url' => $normalizedUrl,
        'route_name' => (string)($overrides['route_name'] ?? analytics_route_name($normalizedUrl)),
        'page_title' => substr((string)($overrides['page_title'] ?? ($GLOBALS['pageTitle'] ?? '')), 0, 255),
        'referrer' => substr((string)($overrides['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 2000),
        'utm_source' => analytics_utm('utm_source'),
        'utm_medium' => analytics_utm('utm_medium'),
        'utm_campaign' => analytics_utm('utm_campaign'),
        'locale' => analytics_pageview_locale(),
        'device_type' => detectDevice($userAgent),
        'browser_name' => detectBrowser($userAgent),
        'os_name' => detectOS($userAgent),
        'country_code' => $geo['country_code'],
        'city_name' => $geo['city_name'],
        'region_name' => $geo['region_name'],
        'asn_number' => $geo['asn_number'],
        'asn_org' => $geo['asn_org'],
        'is_bot' => (bool)$bot['is_bot'],
        'bot_name' => $bot['bot_name'],
        'ip_hash' => hashIpAddress(),
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'response_status' => $status,
        'load_time_ms' => isset($overrides['load_time_ms']) ? (int)$overrides['load_time_ms'] : null,
    ];

    $hasIpAddress = analytics_column_exists('site_pageviews', 'ip_address');
    $hasLocale = analytics_column_exists('site_pageviews', 'locale');
    $hasGeoIp = analytics_geoip_enabled('site_pageviews');
    $geoColumn = $hasGeoIp ? ', city_name, region_name, asn_number, asn_org' : '';
    $geoPlaceholder = $hasGeoIp ? ', ?, ?, ?, ?' : '';
    $localeColumn = $hasLocale ? ', locale' : '';
    $localePlaceholder = $hasLocale ? ', ?' : '';
    $ipColumn = $hasIpAddress ? ', ip_address' : '';
    $ipPlaceholder = $hasIpAddress ? ', ?' : '';
    $params = [
        $payload['session_id'],
        $payload['visitor_id'],
        $payload['url'],
        $payload['route_name'],
        $payload['page_title'],
        $payload['referrer'],
        $payload['utm_source'],
        $payload['utm_medium'],
        $payload['utm_campaign'],
    ];
    if ($hasLocale) {
        $params[] = $payload['locale'];
    }
    array_push(
        $params,
        $payload['device_type'],
        $payload['browser_name'],
        $payload['os_name'],
        $payload['country_code'],
    );
    if ($hasGeoIp) {
        array_push($params, ...analytics_geoip_params($payload));
    }
    array_push(
        $params,
        $payload['is_bot'] ? 1 : 0,
        $payload['bot_name'],
        $payload['ip_hash']
    );
    if ($hasIpAddress) {
        $params[] = $payload['ip_address'];
    }
    $params[] = $payload['user_agent'];
    $params[] = $payload['response_status'];
    $params[] = $payload['load_time_ms'];

    analytics_safe_insert(
        "INSERT INTO site_pageviews
            (session_id, visitor_id, url, route_name, page_title, referrer, utm_source, utm_medium, utm_campaign{$localeColumn},
             device_type, browser_name, os_name, country_code{$geoColumn}, is_bot, bot_name, ip_hash{$ipColumn}, user_agent, response_status, load_time_ms)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?{$localePlaceholder}, ?, ?, ?, ?{$geoPlaceholder}, ?, ?, ?{$ipPlaceholder}, ?, ?, ?)",
        $params
    );
    analytics_record_visitor($payload);

    if ($payload['is_bot'] && analytics_setting_bool('analytics.bot_logging_enabled', true)) {
        recordBotEvent((string)$payload['bot_name'], (string)$bot['bot_type'], 'pageview', (string)$bot['threat_level'], $url, $payload['referrer'], $userAgent);
    }
    if ($status === 404 || preg_match('#/(wp-login|xmlrpc\.php|wp-admin|phpmyadmin|adminer|\.env)#i', $url)) {
        recordBotEvent($payload['is_bot'] ? (string)$payload['bot_name'] : 'Unknown suspicious bot', 'probe', $status === 404 ? '404_probe' : 'login_probe', 'high', $url, $payload['referrer'], $userAgent);
    }
}

function recordBotEvent(string $botName, string $botType, string $eventType, string $threatLevel = 'info', ?string $url = null, ?string $referrer = null, ?string $userAgent = null): void
{
    if (!analytics_setting_bool('analytics.bot_logging_enabled', true)) {
        return;
    }
    if (!analytics_table_exists('bot_events')) {
        return;
    }
    $hasIpAddress = analytics_column_exists('bot_events', 'ip_address');
    $hasGeoIp = analytics_geoip_enabled('bot_events');
    $geoColumn = $hasGeoIp ? ', city_name, region_name, country_code, asn_number, asn_org' : '';
    $geoPlaceholder = $hasGeoIp ? ', ?, ?, ?, ?, ?' : '';
    $ipColumn = $hasIpAddress ? ', ip_address' : '';
    $ipPlaceholder = $hasIpAddress ? ', ?' : '';
    $ipAddress = analytics_ip_address();
    $geo = analytics_geoip_payload($ipAddress);
    $params = [
        substr($botName, 0, 120),
        substr($botType, 0, 80),
        substr((string)($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 2000),
        hashIpAddress(),
    ];
    if ($hasIpAddress) {
        $params[] = $ipAddress;
    }
    if ($hasGeoIp) {
        array_push($params, $geo['city_name'], $geo['region_name'], $geo['country_code'], $geo['asn_number'], $geo['asn_org']);
    }
    array_push(
        $params,
        substr((string)($url ?? ($_SERVER['REQUEST_URI'] ?? '')), 0, 2000),
        substr((string)($referrer ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 2000),
        substr($eventType, 0, 80),
        in_array($threatLevel, ['info', 'low', 'medium', 'high', 'critical'], true) ? $threatLevel : 'info'
    );

    analytics_safe_insert(
        "INSERT INTO bot_events (bot_name, bot_type, user_agent, ip_hash{$ipColumn}{$geoColumn}, url, referrer, event_type, threat_level)
         VALUES (?, ?, ?, ?{$ipPlaceholder}{$geoPlaceholder}, ?, ?, ?, ?)",
        $params
    );
}

function recordDownloadEvent(?int $downloadId = null, ?string $fileUrl = null, ?string $referrer = null, ?string $userAgent = null): void
{
    if (!analytics_setting_bool('analytics.download_tracking_enabled', true)) {
        return;
    }
    if (!analytics_table_exists('download_events')) {
        return;
    }

    if ($downloadId === null && $fileUrl !== null && analytics_table_exists('downloads')) {
        try {
            $stmt = wallet_db()->prepare("SELECT id FROM downloads WHERE file_url = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$fileUrl]);
            $found = $stmt->fetchColumn();
            if ($found) {
                $downloadId = (int)$found;
            } else {
                $path = parse_url($fileUrl, PHP_URL_PATH);
                $name = basename(is_string($path) ? $path : $fileUrl);
                $platform = str_contains(strtolower($fileUrl), '/windows/') ? 'windows' : (str_contains(strtolower($fileUrl), '/linux/') ? 'linux' : 'other');
                $insert = wallet_db()->prepare(
                    "INSERT INTO downloads (title, platform, file_url, status)
                     VALUES (?, ?, ?, 'published')"
                );
                $insert->execute([$name !== '' ? $name : 'Download', $platform, $fileUrl]);
                $downloadId = (int)wallet_db()->lastInsertId();
            }
        } catch (Throwable $e) {
            wallet_log_error('download lookup skipped: ' . $e->getMessage());
        }
    }

    $hasIpAddress = analytics_column_exists('download_events', 'ip_address');
    $ipColumn = $hasIpAddress ? ', ip_address' : '';
    $ipPlaceholder = $hasIpAddress ? ', ?' : '';
    $params = [$downloadId, hashIpAddress()];
    if ($hasIpAddress) {
        $params[] = analytics_ip_address();
    }
    array_push(
        $params,
        substr((string)($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 2000),
        substr((string)($referrer ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 2000)
    );
    analytics_safe_insert(
        "INSERT INTO download_events (download_id, ip_hash{$ipColumn}, user_agent, referrer) VALUES (?, ?{$ipPlaceholder}, ?, ?)",
        $params
    );

    if ($downloadId !== null && analytics_table_exists('downloads')) {
        analytics_safe_insert("UPDATE downloads SET download_count = download_count + 1 WHERE id = ?", [$downloadId]);
    }
}

function recordSecurityEvent(string $eventType, ?int $adminUserId = null, ?string $usernameAttempted = null, array $details = []): void
{
    if (!analytics_table_exists('admin_security_events')) {
        return;
    }

    analytics_safe_insert(
        "INSERT INTO admin_security_events (event_type, admin_user_id, username_attempted, ip_hash, user_agent, details_json)
         VALUES (?, ?, ?, ?, ?, ?)",
        [
            substr($eventType, 0, 80),
            $adminUserId,
            $usernameAttempted !== null ? substr($usernameAttempted, 0, 190) : null,
            hashIpAddress(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 2000),
            json_encode($details, JSON_UNESCAPED_SLASHES),
        ]
    );
}

function recordRateLimitEvent(string $route, string $eventType, int $countValue): void
{
    if (!analytics_table_exists('rate_limit_events')) {
        return;
    }

    analytics_safe_insert(
        "INSERT INTO rate_limit_events (ip_hash, route, event_type, count_value) VALUES (?, ?, ?, ?)",
        [hashIpAddress(), substr($route, 0, 190), substr($eventType, 0, 80), $countValue]
    );
}

function recordSiteError(string $level, string $message, ?string $filePath = null, ?int $lineNumber = null, array $context = []): void
{
    if (!analytics_table_exists('site_errors')) {
        return;
    }

    analytics_safe_insert(
        "INSERT INTO site_errors (level, message, file_path, line_number, url, ip_hash, user_agent, context_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            in_array($level, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $level : 'error',
            $message,
            $filePath !== null ? substr($filePath, 0, 512) : null,
            $lineNumber,
            substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 2000),
            hashIpAddress(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 2000),
            json_encode($context, JSON_UNESCAPED_SLASHES),
        ]
    );
}

function analytics_start_public_request(?string $pageTitle = null, ?string $routeName = null): void
{
    static $started = false;
    if ($started || PHP_SAPI === 'cli') {
        return;
    }
    $started = true;
    analytics_enforce_bot_rules();
    if (!analytics_setting_bool('analytics.enabled', true)) {
        return;
    }
    analytics_utm_capture();
    getVisitorId();
    getSessionId();
    $start = microtime(true);

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        $level = in_array($severity, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR], true) ? 'warning' : 'error';
        recordSiteError($level, $message, $file, $line, ['severity' => $severity]);
        return false;
    });

    register_shutdown_function(static function () use ($start, $pageTitle, $routeName): void {
        $error = error_get_last();
        if (is_array($error) && in_array((int)($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            recordSiteError('critical', (string)$error['message'], (string)$error['file'], (int)$error['line'], ['type' => $error['type']]);
        }
        recordPageView([
            'page_title' => $pageTitle ?? ($GLOBALS['pageTitle'] ?? ''),
            'route_name' => $routeName ?? analytics_route_name((string)($_SERVER['REQUEST_URI'] ?? '/')),
            'response_status' => http_response_code() ?: 200,
            'load_time_ms' => (int)round((microtime(true) - $start) * 1000),
        ]);
    });
}
