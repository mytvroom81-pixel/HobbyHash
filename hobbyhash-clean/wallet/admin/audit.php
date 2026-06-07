<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/admin_view.php';

admin_require_user();

$rows = wallet_db()->query(
    "SELECT a.id, au.username AS admin_name, a.action, a.target_type, a.target_id, a.details_json, a.ip_address, a.created_at
     FROM admin_audit_log a
     LEFT JOIN admin_users au ON au.id = a.admin_user_id
     ORDER BY a.id DESC
     LIMIT 300"
);

render_admin_header('Audit Log');
?>
<div class="card">
  <h3>Admin Audit Log</h3>
  <table>
    <tr><th>ID</th><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>IP</th><th>Details</th></tr>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= h((string)$row['id']) ?></td>
        <td><?= h($row['created_at']) ?></td>
        <td><?= h((string)($row['admin_name'] ?? 'system')) ?></td>
        <td><?= h($row['action']) ?></td>
        <td><?= h((string)$row['target_type']) ?>#<?= h((string)$row['target_id']) ?></td>
        <td><?= h((string)$row['ip_address']) ?></td>
        <td><pre><?= h((string)$row['details_json']) ?></pre></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php render_admin_footer(); ?>
