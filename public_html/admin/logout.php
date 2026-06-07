<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

admin_logout();
wallet_redirect(admin_url('/login.php'));
