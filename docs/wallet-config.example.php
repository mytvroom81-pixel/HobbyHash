<?php
/**
 * Wallet / site backend configuration template.
 *
 * Copy to a private path on the server (never under the public web root), e.g.:
 *   /home/hobbyhashcoin/hobbyhash-wallet-private/config.php
 *
 * Then point the app at it via public_html/.env:
 *   HOBC_WALLET_CONFIG=/home/hobbyhashcoin/hobbyhash-wallet-private/config.php
 *
 * Never commit the real config.php.
 */
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
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    'rpc' => [
        'url' => 'http://127.0.0.1:18762/',
        'username' => '__cookie__',
        'password' => 'your_rpc_cookie_password',
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
        'analytics_salt' => 'replace-with-long-random-string',
    ],
    'analytics' => [
        'ip_hash_salt' => 'replace-with-long-random-string',
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
        'php_error_log' => '/path/to/logs/php_errors.log',
        'app_error_log' => '/path/to/logs/app_errors.log',
    ],
];
