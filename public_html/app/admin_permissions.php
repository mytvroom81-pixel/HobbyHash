<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

function admin_roles(): array
{
    return [
        'super_admin' => 'Super Admin',
        'site_admin' => 'Site Admin',
        'content_manager' => 'Content Manager',
        'support_manager' => 'Support Manager',
        'wallet_manager' => 'Wallet Manager',
        'pool_manager' => 'Pool Manager',
        'analytics_viewer' => 'Analytics Viewer',
        'read_only' => 'Read Only',
    ];
}

function admin_permissions(): array
{
    return [
        'dashboard' => 'Dashboard',
        'analytics' => 'Analytics',
        'visitors' => 'Visitors',
        'bots' => 'Bots',
        'downloads' => 'Downloads',
        'docs' => 'Docs',
        'exchange_listing' => 'Exchange listing',
        'announcements' => 'Announcements',
        'wallet_controls' => 'Wallet controls',
        'withdrawals' => 'Withdrawals',
        'users' => 'Users',
        'mining_pool' => 'Mining pool',
        'nodes' => 'Nodes',
        'explorer' => 'Explorer',
        'treasury_reserve' => 'Treasury/reserve',
        'burn_events' => 'Burn events',
        'support_messages' => 'Support messages',
        'security_center' => 'Security center',
        'admin_users' => 'Admin users',
        'settings' => 'Settings',
        'audit_logs' => 'Audit logs',
        'social_bot' => 'Social bot',
    ];
}

function admin_role_permissions(): array
{
    $all = array_keys(admin_permissions());
    return [
        'super_admin' => $all,
        'site_admin' => ['dashboard', 'analytics', 'visitors', 'bots', 'downloads', 'docs', 'exchange_listing', 'announcements', 'users', 'mining_pool', 'nodes', 'explorer', 'treasury_reserve', 'burn_events', 'support_messages', 'settings', 'audit_logs', 'social_bot'],
        'content_manager' => ['dashboard', 'downloads', 'docs', 'exchange_listing', 'announcements', 'burn_events', 'support_messages', 'social_bot'],
        'support_manager' => ['dashboard', 'support_messages', 'users'],
        'wallet_manager' => ['dashboard', 'wallet_controls', 'withdrawals', 'users', 'treasury_reserve', 'burn_events', 'audit_logs'],
        'pool_manager' => ['dashboard', 'mining_pool', 'nodes', 'explorer'],
        'analytics_viewer' => ['dashboard', 'analytics', 'visitors', 'bots', 'mining_pool', 'nodes', 'explorer'],
        'read_only' => ['dashboard', 'analytics', 'visitors', 'downloads', 'docs', 'exchange_listing', 'announcements', 'mining_pool', 'nodes', 'explorer', 'treasury_reserve', 'burn_events', 'support_messages', 'audit_logs'],
    ];
}

function admin_role_label(?string $role): string
{
    $roles = admin_roles();
    return $roles[$role ?: ''] ?? 'Read Only';
}

function admin_user_role(array $admin): string
{
    $role = (string)($admin['role'] ?? 'super_admin');
    return array_key_exists($role, admin_roles()) ? $role : 'read_only';
}

function admin_can(array $admin, string $permission): bool
{
    $role = admin_user_role($admin);
    $permissions = admin_role_permissions()[$role] ?? [];
    return in_array($permission, $permissions, true);
}

function admin_permission_for_request(): ?string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $tab = (string)($_GET['tab'] ?? 'overview');
    $section = (string)($_GET['section'] ?? '');

    if (in_array($script, ['/admin/login.php', '/admin/logout.php', '/admin/verify-sms.php', '/admin/verify-authenticator.php'], true)) {
        return null;
    }

    if ($script === '/admin/index.php') {
        return 'dashboard';
    }
    if ($script === '/admin/analytics.php') {
        return $tab === 'visitors' ? 'visitors' : 'analytics';
    }
    if ($script === '/admin/bots.php') {
        return 'bots';
    }
    if ($script === '/admin/content.php') {
        return match ($tab) {
            'downloads' => 'downloads',
            'docs', 'pages' => 'docs',
            'announcements' => 'announcements',
            'burn' => 'burn_events',
            'reserve' => 'treasury_reserve',
            'support' => 'support_messages',
            default => 'docs',
        };
    }
    if ($script === '/admin/wallet.php') {
        return 'wallet_controls';
    }
    if ($script === '/admin/withdrawals.php') {
        return 'withdrawals';
    }
    if ($script === '/admin/mining-pool.php') {
        return 'mining_pool';
    }
    if ($script === '/admin/node.php') {
        return 'nodes';
    }
    if ($script === '/admin/blockchain.php' || $script === '/admin/explorer.php') {
        return 'explorer';
    }
    if ($script === '/admin/reserve.php') {
        return 'treasury_reserve';
    }
    if ($script === '/admin/exchange-listing.php') {
        return 'exchange_listing';
    }
    if ($script === '/admin/tickets.php') {
        return 'support_messages';
    }
    if ($script === '/admin/security.php') {
        return 'security_center';
    }
    if ($script === '/admin/admin-users.php') {
        return 'admin_users';
    }
    if ($script === '/admin/audit.php') {
        return 'audit_logs';
    }
    if ($script === '/admin/social-bot.php') {
        return 'social_bot';
    }
    if ($script === '/admin/authenticator.php') {
        return 'dashboard';
    }
    if (in_array($script, ['/admin/settings.php', '/admin/site-config.php', '/admin/smtp.php'], true)) {
        return 'settings';
    }
    if ($script === '/admin/section.php') {
        return match ($section) {
            'users' => 'users',
            'wallets' => 'wallet_controls',
            'mining-pool' => 'mining_pool',
            'nodes' => 'nodes',
            'blockchain-stats', 'explorer-stats' => 'explorer',
            'burn-events' => 'burn_events',
            'announcements' => 'announcements',
            'security-center' => 'security_center',
            'admin-users-roles' => 'admin_users',
            'system-health' => 'settings',
            'downloads' => 'downloads',
            'docs', 'pages-content' => 'docs',
            'support-messages' => 'support_messages',
            default => 'dashboard',
        };
    }

    return 'dashboard';
}

function admin_access_denied(string $permission): void
{
    http_response_code(403);
    $label = admin_permissions()[$permission] ?? $permission;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access denied | HOBC Admin</title><link rel="stylesheet" href="/assets/css/hobc-admin.css"></head><body class="admin-body"><main class="admin-content" style="max-width:760px;margin:48px auto;"><div class="admin-card"><h1>Access denied</h1><p>Your admin role does not include access to <strong>' . h($label) . '</strong>.</p><div class="admin-actions"><a class="admin-action admin-action-secondary" href="' . h(admin_url('/index.php')) . '">Back to dashboard</a><a class="admin-action admin-action-danger" href="' . h(admin_url('/logout.php')) . '">Logout</a></div></div></main></body></html>';
    exit;
}

function admin_enforce_request_permission(array $admin): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $permission = admin_permission_for_request();
    if ($permission !== null && !admin_can($admin, $permission)) {
        admin_access_denied($permission);
    }
}

function admin_super_admin_count(PDO $pdo): int
{
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
