<?php
return [
    'app' => [
        'name' => 'HOBC Web Wallet',
        'base_url' => '/wallet',
        'timezone' => 'UTC',
        'maintenance_page' => '/wallet/maintenance.php',
        'session_name' => 'hobc_wallet_sess',
        'session_secure_cookie' => true,
        'session_samesite' => 'Strict',
        'csrf_token_ttl_seconds' => 7200,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'hobbyhash_wallet',
        'username' => 'hobbyhash_wallet_app',
        'password' => 'CHANGE_ME_STRONG',
        'charset' => 'utf8mb4',
    ],
    'rpc' => [
        'url' => 'http://127.0.0.1:18762/',
        'username' => '__cookie__',
        'password' => 'CHANGE_THIS_LONG_RANDOM_PASSWORD',
        'wallet' => 'startupfees',
        'timeout_seconds' => 8,
    ],
    'security' => [
        'password_algo' => PASSWORD_ARGON2ID,
        'password_options' => [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ],
        'max_login_attempts_per_15m' => 8,
        'max_withdrawals_per_hour' => 5,
        'max_withdrawal_amount_per_day' => '50000.00000000',
        'analytics_salt' => 'replace-with-private-random-analytics-salt',
    ],
    'analytics' => [
        'ip_hash_salt' => 'replace-with-private-random-analytics-salt',
    ],
    'sms' => [
        'enabled' => false,
        'provider' => 'twilio',
        'account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'api_key_sid' => 'SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'api_key_secret' => 'replace-with-private-secret',
        'messaging_service_sid' => 'MGxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'from_number' => '',
        'admin_phone_number' => '+15555555555',
        'code_ttl_seconds' => 600,
        'max_attempts' => 5,
    ],
    'logs' => [
        'php_error_log' => '/home/hobbyhashcoin/hobbyhash-clean/wallet/logs/php_errors.log',
        'app_error_log' => '/home/hobbyhashcoin/hobbyhash-clean/wallet/logs/app_errors.log',
    ],
];
