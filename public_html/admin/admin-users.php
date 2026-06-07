<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/admin_permissions.php';

$admin = admin_require_user();
$pdo = wallet_db();
$msg = '';
$err = '';

function admin_users_target(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT id, username, email, role, is_active FROM admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function admin_users_is_last_super_admin(PDO $pdo, array $target): bool
{
    return (string)($target['role'] ?? '') === 'super_admin'
        && (int)($target['is_active'] ?? 0) === 1
        && admin_super_admin_count($pdo) <= 1;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create_admin') {
            $username = trim((string)($_POST['username'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone_number'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $role = (string)($_POST['role'] ?? 'read_only');
            if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
                throw new RuntimeException('Username must be 3-40 characters using letters, numbers, or underscore.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid email is required.');
            }
            if (!array_key_exists($role, admin_roles())) {
                throw new RuntimeException('Invalid admin role.');
            }
            if (strlen($password) < 12 || !hash_equals($password, $confirm)) {
                throw new RuntimeException('Password must be at least 12 characters and match confirmation.');
            }
            $exists = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ? LIMIT 1");
            $exists->execute([$username, $email]);
            if ($exists->fetch()) {
                throw new RuntimeException('Admin username or email already exists.');
            }
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, role, phone_number, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $email, $role, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);
            $newId = (int)$pdo->lastInsertId();
            admin_audit((int)$admin['id'], 'admin_user_create', 'admin_user', (string)$newId, [
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'phone_set' => $phone !== '',
            ]);
            $msg = 'Admin user created.';
        } elseif ($action === 'update_role') {
            $targetId = (int)($_POST['admin_id'] ?? 0);
            $role = (string)($_POST['role'] ?? 'read_only');
            if (!array_key_exists($role, admin_roles())) {
                throw new RuntimeException('Invalid admin role.');
            }
            $target = admin_users_target($pdo, $targetId);
            if (!$target) {
                throw new RuntimeException('Admin user not found.');
            }
            if ($role !== 'super_admin' && admin_users_is_last_super_admin($pdo, $target)) {
                throw new RuntimeException('Cannot demote the last active Super Admin.');
            }
            $stmt = $pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $targetId]);
            admin_audit((int)$admin['id'], 'admin_role_update', 'admin_user', (string)$targetId, [
                'from_role' => (string)$target['role'],
                'to_role' => $role,
            ]);
            $msg = 'Admin role updated.';
        } elseif ($action === 'update_status') {
            $targetId = (int)($_POST['admin_id'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $target = admin_users_target($pdo, $targetId);
            if (!$target) {
                throw new RuntimeException('Admin user not found.');
            }
            if ($isActive === 0 && admin_users_is_last_super_admin($pdo, $target)) {
                throw new RuntimeException('Cannot deactivate the last active Super Admin.');
            }
            $stmt = $pdo->prepare("UPDATE admin_users SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $targetId]);
            if ($isActive === 0 && admin_sessions_table_exists()) {
                $revoke = $pdo->prepare("UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_by_admin_id = ?, revoke_reason = 'admin_deactivated' WHERE admin_user_id = ? AND revoked_at IS NULL");
                $revoke->execute([(int)$admin['id'], $targetId]);
            }
            admin_audit((int)$admin['id'], 'admin_status_update', 'admin_user', (string)$targetId, [
                'from_active' => (int)$target['is_active'],
                'to_active' => $isActive,
            ]);
            $msg = 'Admin status updated.';
        } else {
            throw new RuntimeException('Unknown admin users action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('admin users action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$admins = $pdo->query("SELECT id, username, email, role, sms_2fa_enabled, totp_enabled, is_active, created_at, updated_at FROM admin_users ORDER BY id ASC")->fetchAll();
$rolePermissions = admin_role_permissions();
$permissions = admin_permissions();

render_admin_header('Admin Users & Roles', ['Admin Users & Roles']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>

<div class="admin-grid admin-grid-tight">
  <?= admin_stat_card('Admin Users', (string)count($admins), 'info') ?>
  <?= admin_stat_card('Active Super Admins', (string)admin_super_admin_count($pdo), admin_super_admin_count($pdo) > 0 ? 'ok' : 'error') ?>
  <?= admin_stat_card('Current Role', admin_role_label((string)$admin['role']), 'ok') ?>
</div>

<div class="admin-card">
  <h3>Add Admin User</h3>
  <p>Create admin users with a specific role. Passwords are hashed; no secrets are shown after save.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create_admin">
    <label>Username<br><input name="username" required maxlength="40" pattern="[A-Za-z0-9_]{3,40}" autocomplete="off"></label><br><br>
    <label>Email<br><input type="email" name="email" required maxlength="320" autocomplete="off"></label><br><br>
    <label>Phone Number Optional<br><input name="phone_number" maxlength="32" placeholder="+15551234567" autocomplete="off"></label><br><br>
    <label>Role<br><select name="role">
      <?php foreach (admin_roles() as $role => $label): ?>
        <option value="<?= h($role) ?>" <?= $role === 'read_only' ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select></label><br><br>
    <label>Temporary Password<br><input type="password" name="password" required minlength="12" autocomplete="new-password"></label><br><br>
    <label>Confirm Password<br><input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label><br><br>
    <button type="submit" data-confirm="Create this admin user?">Create Admin User</button>
  </form>
</div>

<div class="admin-card">
  <h3>Admin Users</h3>
  <p>Role and status changes are enforced server-side and written to the audit log. The last active Super Admin cannot be demoted or deactivated.</p>
  <?php admin_filter_box('Filter admins'); ?>
  <?php admin_render_table(['ID', 'Admin', 'Role', '2FA', 'Status', 'Role Control', 'Status Control'], array_map(static function (array $row): array {
      $roleSelect = '<form method="post" class="inline-form">'
          . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
          . '<input type="hidden" name="action" value="update_role">'
          . '<input type="hidden" name="admin_id" value="' . h((string)$row['id']) . '">'
          . '<select name="role">';
      foreach (admin_roles() as $role => $label) {
          $roleSelect .= '<option value="' . h($role) . '"' . ((string)$row['role'] === $role ? ' selected' : '') . '>' . h($label) . '</option>';
      }
      $roleSelect .= '</select><button type="submit" data-confirm="Change role for ' . h((string)$row['username']) . '?">Save role</button></form>';

      $statusForm = '<form method="post" class="inline-form">'
          . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
          . '<input type="hidden" name="action" value="update_status">'
          . '<input type="hidden" name="admin_id" value="' . h((string)$row['id']) . '">'
          . '<label><input type="checkbox" name="is_active" ' . ((int)$row['is_active'] === 1 ? 'checked' : '') . '> Active</label>'
          . '<button type="submit" data-confirm="Update active status for ' . h((string)$row['username']) . '?">Save status</button></form>';

      return [
          h((string)$row['id']),
          h((string)$row['username']) . '<br><small>' . h((string)$row['email']) . '</small>',
          h(admin_role_label((string)$row['role'])),
          (((int)$row['sms_2fa_enabled'] === 1) ? 'SMS ' : '') . (((int)$row['totp_enabled'] === 1) ? 'TOTP' : ''),
          ((int)$row['is_active'] === 1) ? '<span class="ok">Active</span>' : '<span class="warn">Inactive</span>',
          $roleSelect,
          $statusForm,
      ];
  }, $admins), 'No admin users found', 'No admin users are configured.'); ?>
</div>

<div class="admin-card">
  <h3>Role Permission Matrix</h3>
  <?php
  $headers = array_merge(['Permission'], array_values(admin_roles()));
  $rows = [];
  foreach ($permissions as $permission => $label) {
      $row = [h($label)];
      foreach (array_keys(admin_roles()) as $role) {
          $row[] = in_array($permission, $rolePermissions[$role] ?? [], true) ? '<span class="ok">Allowed</span>' : '<span class="warn">Denied</span>';
      }
      $rows[] = $row;
  }
  admin_render_table($headers, $rows);
  ?>
</div>

<?php render_admin_footer(); ?>
