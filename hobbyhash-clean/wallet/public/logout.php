<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

auth_logout();
wallet_redirect(wallet_url('/login.php'));
