<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

if (auth_current_user()) {
    wallet_redirect(wallet_url('/dashboard.php'));
}
wallet_redirect(wallet_url('/login.php'));
