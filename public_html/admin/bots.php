<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/analytics.php';
require_once __DIR__ . '/../app/security_log.php';

$admin = admin_require_user();
$pdo = wallet_db();

function bots_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW FULL TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetch();
}

function bots_tabs(): array
{
    return [
        'overview' => 'Overview',
        'known-good' => 'Known good bots',
        'search' => 'Search crawlers',
        'social' => 'Social preview bots',
        'seo' => 'SEO crawlers',
        'suspicious' => 'Suspicious bots',
        'login-probes' => 'Login probes',
        '404-scanners' => '404 scanners',
        'high-rate' => 'High-rate visitors',
        'user-agents' => 'User-agent search',
        'timeline' => 'Event timeline',
        'rules' => 'Allowlist / Blocklist',
    ];
}

function bots_filters(): array
{
    $range = (string)($_GET['range'] ?? '7d');
    if (!in_array($range, ['today', '24h', '7d', '30d', 'custom'], true)) {
        $range = '7d';
    }
    $from = (string)($_GET['from'] ?? '');
    $to = (string)($_GET['to'] ?? '');
    if ($range !== 'custom') {
        $to = gmdate('Y-m-d');
        $from = match ($range) {
            'today' => gmdate('Y-m-d'),
            '24h' => gmdate('Y-m-d', time() - 86400),
            '30d' => gmdate('Y-m-d', time() - 2592000),
            default => gmdate('Y-m-d', time() - 604800),
        };
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = gmdate('Y-m-d', time() - 604800);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = gmdate('Y-m-d');
    }

    return [
        'tab' => (string)($_GET['tab'] ?? 'overview'),
        'range' => $range,
        'from' => $from,
        'to' => $to,
        'from_sql' => $from . ' 00:00:00',
        'to_sql' => $to . ' 23:59:59',
        'q' => trim((string)($_GET['q'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => 50,
    ];
}

function bots_base_params(array $filters, string $tab): array
{
    return [
        'tab' => $tab,
        'range' => $filters['range'],
        'from' => $filters['from'],
        'to' => $filters['to'],
        'q' => $filters['q'],
    ];
}

function bots_fetch(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function bots_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function bots_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_.-]+/i', '-', $filename) . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_map(static fn($value): string => strip_tags((string)$value), $row));
    }
    fclose($out);
    exit;
}

function bots_filter_form(array $filters, string $tab): void
{
    echo '<div class="admin-card"><form method="get" class="analytics-filter-form">';
    echo '<input type="hidden" name="tab" value="' . h($tab) . '">';
    echo '<label>Date range<select name="range">';
    foreach (['today' => 'Today', '24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom'] as $value => $label) {
        echo '<option value="' . h($value) . '"' . ($filters['range'] === $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label>From<input type="date" name="from" value="' . h($filters['from']) . '"></label>';
    echo '<label>To<input type="date" name="to" value="' . h($filters['to']) . '"></label>';
    echo '<label>User-agent / URL / bot<input name="q" value="' . h($filters['q']) . '" placeholder="Search bot name, URL, user-agent"></label>';
    echo '<button type="submit">Apply Filters</button>';
    echo '<a class="admin-action admin-action-secondary" href="' . h(admin_url('/bots.php?' . http_build_query(array_merge(bots_base_params($filters, $tab), ['export' => 'csv'])))) . '">Export Bot Logs CSV</a>';
    echo '</form></div>';
}

function bots_detail_link(string $type, string $value, string $label): string
{
    return '<a href="' . h(admin_url('/bots.php?detail=' . rawurlencode($type) . '&id=' . rawurlencode($value))) . '">' . h($label) . '</a>';
}

function bots_chart(array $rows, string $labelKey, string $valueKey, string $title): void
{
    if ($rows === []) {
        echo admin_empty_state('No chart data yet', 'Bot chart data appears after events are collected.');
        return;
    }
    $max = 1;
    foreach ($rows as $row) {
        $max = max($max, (int)$row[$valueKey]);
    }
    echo '<div class="admin-card"><h3>' . h($title) . '</h3><div class="analytics-chart">';
    foreach (array_slice($rows, 0, 14) as $row) {
        $value = (int)$row[$valueKey];
        $width = max(2, (int)round(($value / $max) * 100));
        echo '<div class="analytics-bar-row"><span>' . h((string)($row[$labelKey] ?: 'not_available')) . '</span><div><b style="width:' . h((string)$width) . '%"></b></div><strong>' . h((string)$value) . '</strong></div>';
    }
    echo '</div></div>';
}

function bots_known_good_pattern(string $pattern): bool
{
    return preg_match('/googlebot|bingbot|duckduckbot|yandexbot|baiduspider|facebookexternalhit|facebot|twitterbot|discordbot|telegrambot/i', $pattern) === 1;
}

function bots_audit(int $adminId, string $action, string $targetType, string $targetId, array $details = []): void
{
    admin_audit($adminId, $action, $targetType, $targetId, $details);
}

function bots_audit_export(string $exportType, array $filters): void
{
    static $logged = false;
    if ($logged) {
        return;
    }
    $logged = true;
    $adminId = (int)($GLOBALS['admin']['id'] ?? 0);
    if ($adminId > 0) {
        admin_audit($adminId, 'export_bot_logs_csv', 'bot_events', $exportType, [
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
            'tab' => $filters['tab'] ?? '',
            'search' => !empty($filters['q']),
        ]);
    }
}

function bots_row_ip(array $row): string
{
    $ip = trim((string)($row['ip_address'] ?? ''));
    if ($ip !== '') {
        return $ip;
    }
    $hash = trim((string)($row['ip_hash'] ?? ''));
    return $hash !== '' ? 'hash:' . substr($hash, 0, 16) . '...' : 'not_available';
}

function bots_handle_post(PDO $pdo, array $admin): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    $adminId = (int)$admin['id'];

    if ($action === 'add_rule') {
        $ruleType = (string)($_POST['rule_type'] ?? '');
        $targetType = (string)($_POST['target_type'] ?? '');
        $pattern = trim((string)($_POST['pattern'] ?? ''));
        $matchType = (string)($_POST['match_type'] ?? 'contains');
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (!in_array($ruleType, ['allow', 'block'], true) || !in_array($targetType, ['user_agent', 'ip_hash', 'bot_name'], true) || $pattern === '') {
            admin_flash_set('error', 'Rule type, target type, and pattern are required.');
            wallet_redirect(admin_url('/bots.php?tab=rules'));
        }
        if ($ruleType === 'block' && $targetType !== 'ip_hash' && bots_known_good_pattern($pattern)) {
            admin_flash_set('error', 'Known good search/social crawlers cannot be added to the blocklist by pattern. Add an allowlist rule instead.');
            wallet_redirect(admin_url('/bots.php?tab=rules'));
        }
        if (!in_array($matchType, ['contains', 'exact', 'regex'], true)) {
            $matchType = 'contains';
        }
        $stmt = $pdo->prepare(
            "INSERT INTO bot_rules (rule_type, target_type, pattern, match_type, threat_level, notes, created_by_admin_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$ruleType, $targetType, substr($pattern, 0, 512), $matchType, $ruleType === 'block' ? 'high' : 'info', $notes, $adminId]);
        bots_audit($adminId, 'bot_rule_added', 'bot_rule', (string)$pdo->lastInsertId(), ['rule_type' => $ruleType, 'target_type' => $targetType, 'pattern' => $pattern]);
        admin_flash_set('success', ucfirst($ruleType) . ' rule added.');
        wallet_redirect(admin_url('/bots.php?tab=rules'));
    }

    if ($action === 'toggle_rule') {
        $id = (int)($_POST['rule_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'inactive');
        $status = $status === 'active' ? 'active' : 'inactive';
        $stmt = $pdo->prepare("UPDATE bot_rules SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        bots_audit($adminId, 'bot_rule_status_updated', 'bot_rule', (string)$id, ['status' => $status]);
        admin_flash_set('success', 'Rule status updated.');
        wallet_redirect(admin_url('/bots.php?tab=rules'));
    }

    if ($action === 'save_note') {
        $subjectType = (string)($_POST['subject_type'] ?? 'user_agent');
        $subjectValue = trim((string)($_POST['subject_value'] ?? ''));
        $classification = (string)($_POST['classification'] ?? 'unknown');
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (!in_array($subjectType, ['user_agent', 'ip_hash', 'bot_name'], true) || $subjectValue === '') {
            admin_flash_set('error', 'Note subject is required.');
            wallet_redirect(admin_url('/bots.php'));
        }
        if (!in_array($classification, ['unknown', 'harmless', 'suspicious'], true)) {
            $classification = 'unknown';
        }
        $stmt = $pdo->prepare(
            "INSERT INTO bot_notes (subject_type, subject_value, classification, notes, updated_by_admin_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE classification = VALUES(classification), notes = VALUES(notes), updated_by_admin_id = VALUES(updated_by_admin_id)"
        );
        $stmt->execute([$subjectType, substr($subjectValue, 0, 512), $classification, $notes, $adminId]);
        bots_audit($adminId, 'bot_note_saved', 'bot_note', $subjectType . ':' . $subjectValue, ['classification' => $classification]);
        admin_flash_set('success', 'Bot note saved.');
        wallet_redirect(admin_url('/bots.php?detail=' . rawurlencode($subjectType === 'bot_name' ? 'bot' : $subjectType) . '&id=' . rawurlencode($subjectValue)));
    }

    if ($action === 'clear_cache') {
        bots_audit($adminId, 'bot_classification_cache_cleared', 'bot_cache', 'runtime', []);
        admin_flash_set('success', 'Bot classification cache cleared. Current classifier is request-time only, so no persistent cache rows needed removal.');
        wallet_redirect(admin_url('/bots.php?tab=rules'));
    }
}

function bots_where(array $filters): array
{
    $where = ['created_at BETWEEN ? AND ?'];
    $params = [$filters['from_sql'], $filters['to_sql']];
    if ($filters['q'] !== '') {
        $where[] = '(bot_name LIKE ? OR user_agent LIKE ? OR url LIKE ? OR referrer LIKE ?)';
        array_push($params, '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%', '%' . $filters['q'] . '%');
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function bots_render_table(PDO $pdo, array $filters, string $tab, string $extraWhere, array $extraParams = []): void
{
    $base = bots_where($filters);
    $where = $base['sql'] . ($extraWhere !== '' ? ' AND ' . $extraWhere : '');
    $params = array_merge($base['params'], $extraParams);
    $total = bots_count($pdo, "SELECT COUNT(*) FROM bot_events WHERE {$where}", $params);
    $offset = ((int)$filters['page'] - 1) * (int)$filters['per_page'];
    $rows = bots_fetch($pdo, "SELECT * FROM bot_events WHERE {$where} ORDER BY created_at DESC LIMIT " . (int)$filters['per_page'] . " OFFSET {$offset}", $params);
    $mapped = array_map(static fn(array $row): array => [
        admin_h_datetime($row['created_at'] ?? null),
        bots_detail_link('bot', (string)$row['bot_name'], (string)$row['bot_name']),
        h((string)$row['bot_type']),
        h((string)$row['event_type']),
        h((string)$row['threat_level']),
        bots_detail_link('url', (string)$row['url'], (string)$row['url']),
        '<code>' . h(bots_row_ip($row)) . '</code>',
        h((string)$row['user_agent']),
    ], $rows);
    if (($_GET['export'] ?? '') === 'csv') {
        bots_audit_export('events', array_merge($filters, ['tab' => $tab]));
        bots_csv('hobc-bot-logs-' . gmdate('Ymd-His') . '.csv', ['When', 'Bot', 'Type', 'Event', 'Threat', 'URL', 'IP', 'User Agent'], $mapped);
    }
    admin_render_table(['When', 'Bot', 'Type', 'Event', 'Threat', 'URL', 'IP', 'User Agent'], $mapped, 'No bot events', 'No bot events matched this filter.');
    admin_pagination((int)$filters['page'], max(1, (int)ceil($total / (int)$filters['per_page'])), admin_url('/bots.php'), bots_base_params($filters, $tab));
}

function bots_render_overview(PDO $pdo, array $filters): void
{
    $base = bots_where($filters);
    echo '<div class="admin-grid admin-grid-tight">';
    echo admin_stat_card('Bot Events', (string)bots_count($pdo, 'SELECT COUNT(*) FROM bot_events WHERE ' . $base['sql'], $base['params']), 'warn');
    echo admin_stat_card('Known Good', (string)bots_count($pdo, "SELECT COUNT(*) FROM bot_events WHERE {$base['sql']} AND bot_type IN ('search_engine','crawler') AND threat_level = 'info'", $base['params']), 'ok');
    echo admin_stat_card('Suspicious', (string)bots_count($pdo, "SELECT COUNT(*) FROM bot_events WHERE {$base['sql']} AND threat_level IN ('medium','high','critical')", $base['params']), 'warn');
    echo admin_stat_card('Blocked Hits', bots_table_exists($pdo, 'bot_rule_hits') ? (string)bots_count($pdo, "SELECT COUNT(*) FROM bot_rule_hits WHERE created_at BETWEEN ? AND ? AND rule_type = 'block'", [$filters['from_sql'], $filters['to_sql']]) : '0', 'error');
    echo admin_stat_card('Active Rules', bots_table_exists($pdo, 'bot_rules') ? (string)bots_count($pdo, "SELECT COUNT(*) FROM bot_rules WHERE status = 'active'", []) : '0', 'info');
    echo '</div>';
    $daily = bots_fetch($pdo, "SELECT DATE(created_at) AS day, COUNT(*) AS events FROM bot_events WHERE {$base['sql']} GROUP BY day ORDER BY day DESC LIMIT 30", $base['params']);
    if (($_GET['export'] ?? '') === 'csv') {
        bots_audit_export('overview', array_merge($filters, ['tab' => 'overview']));
        bots_csv('hobc-bot-overview-' . gmdate('Ymd-His') . '.csv', ['Day', 'Events'], array_map(static fn(array $row): array => [$row['day'], $row['events']], $daily));
    }
    bots_chart(array_reverse($daily), 'day', 'events', 'Bot Traffic By Day');
    if (bots_table_exists($pdo, 'site_pageviews')) {
        $mix = bots_fetch($pdo, "SELECT IF(is_bot = 1, 'Bots', 'Humans') AS label, COUNT(*) AS total FROM site_pageviews WHERE created_at BETWEEN ? AND ? GROUP BY label", [$filters['from_sql'], $filters['to_sql']]);
        bots_chart($mix, 'label', 'total', 'Bot vs Human Traffic');
    }
}

function bots_render_top(PDO $pdo, array $filters): void
{
    $base = bots_where($filters);
    $agents = bots_fetch($pdo, "SELECT user_agent, COUNT(*) AS events, MAX(created_at) AS last_seen FROM bot_events WHERE {$base['sql']} GROUP BY user_agent ORDER BY events DESC LIMIT 25", $base['params']);
    if (($_GET['export'] ?? '') === 'csv') {
        bots_audit_export('user_agents', array_merge($filters, ['tab' => 'user-agents']));
        bots_csv('hobc-bot-user-agents-' . gmdate('Ymd-His') . '.csv', ['User Agent', 'Events', 'Last Seen'], array_map(static fn(array $row): array => [$row['user_agent'], $row['events'], $row['last_seen']], $agents));
    }
    admin_render_table(['User Agent', 'Events', 'Last Seen'], array_map(static fn(array $row): array => [bots_detail_link('user_agent', (string)$row['user_agent'], (string)$row['user_agent']), h((string)$row['events']), admin_h_datetime($row['last_seen'] ?? null)], $agents), 'No user agents', 'No bot user agents matched this filter.');
    $urls = bots_fetch($pdo, "SELECT url, COUNT(*) AS events, MAX(created_at) AS last_seen FROM bot_events WHERE {$base['sql']} GROUP BY url ORDER BY events DESC LIMIT 25", $base['params']);
    admin_render_table(['URL', 'Events', 'Last Seen'], array_map(static fn(array $row): array => [bots_detail_link('url', (string)$row['url'], (string)$row['url']), h((string)$row['events']), admin_h_datetime($row['last_seen'] ?? null)], $urls), 'No URLs', 'No bot URLs matched this filter.');
}

function bots_render_rules(PDO $pdo): void
{
    echo '<div class="admin-card"><h3>Safe App-Level Controls</h3><p>Rules are enforced inside PHP before page rendering. This does not edit server firewall rules. Allowlist rules win before blocklist rules, and known good crawlers are protected from accidental user-agent pattern blocks.</p></div>';
    echo '<div class="admin-card"><h3>Add Allowlist / Blocklist Rule</h3><form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="add_rule">';
    echo '<label>Rule type<select name="rule_type"><option value="allow">Allowlist</option><option value="block">Blocklist</option></select></label>';
    echo '<label>Target<select name="target_type"><option value="user_agent">User-agent pattern</option><option value="ip_hash">Hashed IP</option><option value="bot_name">Bot name</option></select></label>';
    echo '<label>Match type<select name="match_type"><option value="contains">Contains</option><option value="exact">Exact</option><option value="regex">Regex</option></select></label>';
    echo '<label>Pattern<input name="pattern" required maxlength="512" placeholder="Example: SemrushBot or a hashed IP"></label>';
    echo '<label>Admin notes<textarea name="notes" placeholder="Why this rule is safe"></textarea></label>';
    echo '<button type="submit" data-confirm="Add this app-level bot rule? Known good crawlers are protected from accidental pattern blocks.">Add Rule</button>';
    echo '</form></div>';
    echo '<div class="admin-card"><form method="post"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="clear_cache"><button type="submit" data-confirm="Clear bot classification cache? Current classifier is request-time only.">Clear Bot Classification Cache</button></form></div>';
    $rules = bots_table_exists($pdo, 'bot_rules') ? bots_fetch($pdo, 'SELECT * FROM bot_rules ORDER BY id DESC LIMIT 200') : [];
    admin_render_table(['ID', 'Type', 'Target', 'Match', 'Pattern', 'Status', 'Notes', 'Action'], array_map(static function (array $row): array {
        $next = $row['status'] === 'active' ? 'inactive' : 'active';
        $form = '<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="toggle_rule"><input type="hidden" name="rule_id" value="' . h((string)$row['id']) . '"><input type="hidden" name="status" value="' . h($next) . '"><button type="submit" data-confirm="Set this rule to ' . h($next) . '?">' . h(ucfirst($next)) . '</button></form>';
        return [h((string)$row['id']), h((string)$row['rule_type']), h((string)$row['target_type']), h((string)$row['match_type']), '<code>' . h((string)$row['pattern']) . '</code>', h((string)$row['status']), h((string)$row['notes']), $form];
    }, $rules), 'No bot rules yet', 'Add an allowlist or blocklist rule above.');
}

function bots_render_note_form(string $subjectType, string $subjectValue, ?array $note): void
{
    echo '<div class="admin-card"><h3>Admin Notes</h3><form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="save_note">';
    echo '<input type="hidden" name="subject_type" value="' . h($subjectType) . '"><input type="hidden" name="subject_value" value="' . h($subjectValue) . '">';
    echo '<label>Classification<select name="classification">';
    foreach (['unknown' => 'Unknown', 'harmless' => 'Mark harmless', 'suspicious' => 'Mark suspicious'] as $value => $label) {
        echo '<option value="' . h($value) . '"' . (($note['classification'] ?? 'unknown') === $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Notes<textarea name="notes">' . h((string)($note['notes'] ?? '')) . '</textarea></label>';
    echo '<button type="submit">Save Bot Note</button></form></div>';
}

function bots_note(PDO $pdo, string $subjectType, string $subjectValue): ?array
{
    if (!bots_table_exists($pdo, 'bot_notes')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM bot_notes WHERE subject_type = ? AND subject_value = ? LIMIT 1');
    $stmt->execute([$subjectType, $subjectValue]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function bots_render_detail(PDO $pdo, string $type, string $id): void
{
    echo '<div class="admin-actions">' . admin_action_button('Back to Bots', admin_url('/bots.php'), 'secondary') . '</div>';
    $subjectType = match ($type) {
        'ip_hash' => 'ip_hash',
        'bot' => 'bot_name',
        default => 'user_agent',
    };
    $note = bots_note($pdo, $subjectType, $id);
    bots_render_note_form($subjectType, $id, $note);
    $where = match ($type) {
        'bot' => 'bot_name = ?',
        'url' => 'url = ?',
        'ip_hash' => 'ip_hash = ?',
        default => 'user_agent = ?',
    };
    $rows = bots_fetch($pdo, "SELECT * FROM bot_events WHERE {$where} ORDER BY created_at DESC LIMIT 500", [$id]);
    admin_render_table(['When', 'Bot', 'Type', 'Threat', 'Event', 'URL', 'IP', 'User Agent'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)$row['bot_name']), h((string)$row['bot_type']), h((string)$row['threat_level']), h((string)$row['event_type']), h((string)$row['url']), '<code>' . h(bots_row_ip($row)) . '</code>', h((string)$row['user_agent'])], $rows), 'No bot detail', 'No events found for this bot detail.');
}

bots_handle_post($pdo, $admin);

$filters = bots_filters();
$tabs = bots_tabs();
if (!isset($tabs[$filters['tab']])) {
    $filters['tab'] = 'overview';
}

render_admin_header('Bots & Crawlers', ['Bots & Crawlers']);

$missing = [];
foreach (['bot_events', 'bot_rules', 'bot_notes', 'bot_rule_hits'] as $table) {
    if (!bots_table_exists($pdo, $table)) {
        $missing[] = $table;
    }
}
if ($missing !== []) {
    admin_render_alert('warning', 'Bot management tables missing: ' . implode(', ', $missing) . '. Run php /home/hobbyhashcoin/hobbyhash-clean/wallet/run_migrations.php.');
}

$detailType = (string)($_GET['detail'] ?? '');
$detailId = (string)($_GET['id'] ?? '');
if ($detailType !== '' && $detailId !== '') {
    bots_render_detail($pdo, $detailType, $detailId);
    render_admin_footer();
    exit;
}

echo '<div class="admin-card"><h3>Bot Category Notes</h3><p><b>Known good bots</b> are recognized search/social crawlers. <b>SEO crawlers</b> are commercial crawlers such as Ahrefs, Semrush, MJ12, and DotBot. <b>Suspicious bots</b> include generic scripts, probes, high threat events, login probes, and 404 scanners. Blocking is optional, app-level only, and never edits firewall rules.</p></div>';
echo '<div class="admin-tabs">';
foreach ($tabs as $key => $label) {
    echo '<a class="admin-tab ' . ($filters['tab'] === $key ? 'is-active' : '') . '" href="' . h(admin_url('/bots.php?' . http_build_query(array_merge(bots_base_params($filters, $key), ['page' => 1])))) . '">' . h($label) . '</a>';
}
echo '</div>';

if ($filters['tab'] !== 'rules') {
    bots_filter_form($filters, $filters['tab']);
}

if ($missing !== [] && $filters['tab'] !== 'rules') {
    echo admin_empty_state('Migration required', 'Apply the bot management migration before using this section.');
    render_admin_footer();
    exit;
}

match ($filters['tab']) {
    'overview' => bots_render_overview($pdo, $filters),
    'known-good' => bots_render_table($pdo, $filters, 'known-good', "bot_type IN ('search_engine','crawler') AND threat_level = 'info'"),
    'search' => bots_render_table($pdo, $filters, 'search', "bot_type = 'search_engine'"),
    'social' => bots_render_table($pdo, $filters, 'social', "bot_name IN ('Facebook crawler','Twitter/X crawler','Discordbot','TelegramBot')"),
    'seo' => bots_render_table($pdo, $filters, 'seo', "bot_name IN ('AhrefsBot','SemrushBot','MJ12bot','DotBot')"),
    'suspicious' => bots_render_table($pdo, $filters, 'suspicious', "threat_level IN ('medium','high','critical') OR bot_type IN ('generic_script','probe','unknown_bot','app_block')"),
    'login-probes' => bots_render_table($pdo, $filters, 'login-probes', "event_type = 'login_probe'"),
    '404-scanners' => bots_render_table($pdo, $filters, '404-scanners', "event_type = '404_probe'"),
    'high-rate' => bots_render_table($pdo, $filters, 'high-rate', "ip_hash IN (SELECT ip_hash FROM bot_events WHERE created_at BETWEEN " . $pdo->quote($filters['from_sql']) . " AND " . $pdo->quote($filters['to_sql']) . " GROUP BY ip_hash HAVING COUNT(*) >= 50)"),
    'user-agents' => bots_render_top($pdo, $filters),
    'timeline' => bots_render_table($pdo, $filters, 'timeline', ''),
    'rules' => bots_render_rules($pdo),
    default => bots_render_overview($pdo, $filters),
};

render_admin_footer();
