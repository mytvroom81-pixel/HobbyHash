<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();

function audit_date_value(string $key, string $default): string
{
    $value = (string)($_GET[$key] ?? $default);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $default;
}

function audit_filters(PDO $pdo): array
{
    $from = audit_date_value('from', gmdate('Y-m-d', time() - 2592000));
    $to = audit_date_value('to', gmdate('Y-m-d'));
    return [
        'from' => $from,
        'to' => $to,
        'from_sql' => $from . ' 00:00:00',
        'to_sql' => $to . ' 23:59:59',
        'admin_user_id' => max(0, (int)($_GET['admin_user_id'] ?? 0)),
        'action' => substr(trim((string)($_GET['action'] ?? '')), 0, 128),
        'target_type' => substr(trim((string)($_GET['target_type'] ?? '')), 0, 64),
        'q' => substr(trim((string)($_GET['q'] ?? '')), 0, 190),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => 50,
    ];
}

function audit_where(array $filters): array
{
    $where = ['a.created_at BETWEEN ? AND ?'];
    $params = [$filters['from_sql'], $filters['to_sql']];

    if ((int)$filters['admin_user_id'] > 0) {
        $where[] = 'a.admin_user_id = ?';
        $params[] = (int)$filters['admin_user_id'];
    }
    if ($filters['action'] !== '') {
        $where[] = 'a.action = ?';
        $params[] = $filters['action'];
    }
    if ($filters['target_type'] !== '') {
        $where[] = 'a.target_type = ?';
        $params[] = $filters['target_type'];
    }
    if ($filters['q'] !== '') {
        $where[] = '(a.action LIKE ? OR a.target_type LIKE ? OR a.target_id LIKE ? OR a.details_json LIKE ? OR au.username LIKE ? OR au.email LIKE ?)';
        $like = '%' . $filters['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function audit_query_string(array $filters, array $overrides = []): string
{
    $params = array_merge([
        'from' => $filters['from'],
        'to' => $filters['to'],
        'admin_user_id' => $filters['admin_user_id'],
        'action' => $filters['action'],
        'target_type' => $filters['target_type'],
        'q' => $filters['q'],
        'page' => $filters['page'],
    ], $overrides);
    return http_build_query(array_filter($params, static fn($value): bool => $value !== '' && $value !== 0 && $value !== '0'));
}

function audit_fetch_options(PDO $pdo, string $column): array
{
    $allowed = ['action', 'target_type'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }
    $rows = $pdo->query("SELECT DISTINCT {$column} AS value FROM admin_audit_log WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC LIMIT 500")->fetchAll();
    return array_map(static fn(array $row): string => (string)$row['value'], $rows);
}

function audit_export_csv(PDO $pdo, int $adminId, array $filters): void
{
    admin_audit($adminId, 'export_admin_audit_logs_csv', 'admin_audit_log', 'csv', [
        'from' => $filters['from'],
        'to' => $filters['to'],
        'admin_user_id' => (int)$filters['admin_user_id'],
        'action' => $filters['action'],
        'target_type' => $filters['target_type'],
        'search' => $filters['q'] !== '',
    ]);

    $where = audit_where($filters);
    $stmt = $pdo->prepare(
        "SELECT a.id, a.created_at, a.admin_user_id, au.username AS admin_name, au.email AS admin_email,
                a.action, a.target_type, a.target_id, a.ip_address, a.user_agent, a.details_json
         FROM admin_audit_log a
         LEFT JOIN admin_users au ON au.id = a.admin_user_id
         WHERE {$where['sql']}
         ORDER BY a.id DESC
         LIMIT 50000"
    );
    $stmt->execute($where['params']);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hobc-admin-audit-' . gmdate('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fputcsv($out, ['id', 'created_at', 'admin_user_id', 'admin', 'admin_email', 'action', 'entity_type', 'entity_id', 'ip_address', 'user_agent', 'details_json']);
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['admin_user_id'],
            $row['admin_name'] ?? 'system',
            $row['admin_email'] ?? '',
            $row['action'],
            $row['target_type'],
            $row['target_id'],
            $row['ip_address'],
            $row['user_agent'],
            $row['details_json'],
        ]);
    }
    fclose($out);
    exit;
}

$filters = audit_filters($pdo);
if ((string)($_GET['export'] ?? '') === 'csv') {
    audit_export_csv($pdo, (int)$admin['id'], $filters);
}

$where = audit_where($filters);
$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM admin_audit_log a
     LEFT JOIN admin_users au ON au.id = a.admin_user_id
     WHERE {$where['sql']}"
);
$countStmt->execute($where['params']);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $filters['per_page']));
$filters['page'] = min((int)$filters['page'], $totalPages);
$offset = ((int)$filters['page'] - 1) * (int)$filters['per_page'];

$stmt = $pdo->prepare(
    "SELECT a.id, a.admin_user_id, au.username AS admin_name, au.email AS admin_email,
            a.action, a.target_type, a.target_id, a.details_json, a.ip_address, a.user_agent, a.created_at
     FROM admin_audit_log a
     LEFT JOIN admin_users au ON au.id = a.admin_user_id
     WHERE {$where['sql']}
     ORDER BY a.id DESC
     LIMIT " . (int)$filters['per_page'] . " OFFSET " . (int)$offset
);
$stmt->execute($where['params']);
$rows = $stmt->fetchAll();

$detail = null;
$detailId = (int)($_GET['detail'] ?? 0);
if ($detailId > 0) {
    $detailStmt = $pdo->prepare(
        "SELECT a.*, au.username AS admin_name, au.email AS admin_email
         FROM admin_audit_log a
         LEFT JOIN admin_users au ON au.id = a.admin_user_id
         WHERE a.id = ? LIMIT 1"
    );
    $detailStmt->execute([$detailId]);
    $found = $detailStmt->fetch();
    $detail = is_array($found) ? $found : null;
}

$admins = $pdo->query("SELECT id, username, email FROM admin_users ORDER BY username ASC")->fetchAll();
$actions = audit_fetch_options($pdo, 'action');
$targetTypes = audit_fetch_options($pdo, 'target_type');

render_admin_header('Audit Logs', ['Audit Logs']);
?>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Matching Logs', number_format($totalRows), 'info') ?>
  <?= admin_stat_card('Current Page', (string)$filters['page'] . ' / ' . (string)$totalPages, 'ok') ?>
  <?= admin_stat_card('Range', h($filters['from'] . ' to ' . $filters['to']), 'info') ?>
</div>

<div class="admin-card">
  <h3>Filter Audit Logs</h3>
  <form method="get" class="analytics-filter-form">
    <label>From<input type="date" name="from" value="<?= h($filters['from']) ?>"></label>
    <label>To<input type="date" name="to" value="<?= h($filters['to']) ?>"></label>
    <label>Admin User<select name="admin_user_id">
      <option value="0">All admins</option>
      <?php foreach ($admins as $row): ?>
        <option value="<?= h((string)$row['id']) ?>" <?= (int)$filters['admin_user_id'] === (int)$row['id'] ? 'selected' : '' ?>><?= h((string)$row['username']) ?> (<?= h((string)$row['email']) ?>)</option>
      <?php endforeach; ?>
    </select></label>
    <label>Action<select name="action">
      <option value="">All actions</option>
      <?php foreach ($actions as $action): ?>
        <option value="<?= h($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= h($action) ?></option>
      <?php endforeach; ?>
    </select></label>
    <label>Entity Type<select name="target_type">
      <option value="">All entity types</option>
      <?php foreach ($targetTypes as $targetType): ?>
        <option value="<?= h($targetType) ?>" <?= $filters['target_type'] === $targetType ? 'selected' : '' ?>><?= h($targetType) ?></option>
      <?php endforeach; ?>
    </select></label>
    <label>Search<input name="q" value="<?= h($filters['q']) ?>" placeholder="Action, admin, entity, metadata"></label>
    <button type="submit">Apply Filters</button>
    <a class="admin-action admin-action-secondary" href="<?= h(admin_url('/audit.php?' . audit_query_string($filters, ['export' => 'csv', 'page' => 0]))) ?>">Export CSV</a>
  </form>
</div>

<?php if ($detail): ?>
  <div class="admin-card" id="detail">
    <h3>Audit Detail #<?= h((string)$detail['id']) ?></h3>
    <?php admin_render_table(['Field', 'Value'], [
        ['When', admin_h_datetime($detail['created_at'] ?? null)],
        ['Admin', h((string)($detail['admin_name'] ?? 'system')) . '<br><small>' . h((string)($detail['admin_email'] ?? '')) . '</small>'],
        ['Action', h((string)$detail['action'])],
        ['Entity', h((string)$detail['target_type']) . '#' . h((string)$detail['target_id'])],
        ['IP Address', '<code>' . h((string)$detail['ip_address']) . '</code>'],
        ['User Agent', h((string)$detail['user_agent'])],
        ['Metadata JSON', '<pre>' . h(json_encode(json_decode((string)$detail['details_json'], true) ?? (string)$detail['details_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>'],
    ]); ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h3>Audit Log Entries</h3>
  <?php admin_render_table(['ID', 'When', 'Admin', 'Action', 'Entity', 'IP Address', 'Details'], array_map(static function (array $row) use ($filters): array {
      $detailUrl = admin_url('/audit.php?' . audit_query_string($filters, ['detail' => (int)$row['id']]));
      $details = (string)($row['details_json'] ?? '');
      return [
          '<a href="' . h($detailUrl) . '#detail">' . h((string)$row['id']) . '</a>',
          admin_h_datetime($row['created_at'] ?? null),
          h((string)($row['admin_name'] ?? 'system')) . '<br><small>' . h((string)($row['admin_email'] ?? '')) . '</small>',
          '<code>' . h((string)$row['action']) . '</code>',
          h((string)$row['target_type']) . '<br><small>' . h((string)$row['target_id']) . '</small>',
          '<code>' . h((string)$row['ip_address']) . '</code>',
          $details !== '' ? '<pre>' . h(substr($details, 0, 500)) . (strlen($details) > 500 ? "\n..." : '') . '</pre>' : '',
      ];
  }, $rows), 'No audit logs found', 'No audit entries match the current filters.'); ?>

  <div class="admin-actions">
    <?php admin_pagination((int)$filters['page'], $totalPages, admin_url('/audit.php'), array_filter([
        'from' => $filters['from'],
        'to' => $filters['to'],
        'admin_user_id' => $filters['admin_user_id'] ?: null,
        'action' => $filters['action'] ?: null,
        'target_type' => $filters['target_type'] ?: null,
        'q' => $filters['q'] ?: null,
    ], static fn($value): bool => $value !== null && $value !== ''), $totalRows); ?>
  </div>
</div>
<?php render_admin_footer(); ?>
