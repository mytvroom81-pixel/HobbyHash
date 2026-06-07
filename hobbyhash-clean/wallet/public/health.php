<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rpc.php';

header('Content-Type: application/json');

$status = ['db' => 'ok', 'rpc' => 'ok', 'scanner' => 'unknown'];
try {
    wallet_db()->query('SELECT 1');
} catch (Throwable $e) {
    $status['db'] = 'error';
}

if (!rpc_is_online()) {
    $status['rpc'] = 'offline';
}

try {
    $scan = wallet_db()->query("SELECT scanner_status, last_scanned_height, updated_at FROM chain_scan_state WHERE id = 1")->fetch();
    $status['scanner'] = $scan['scanner_status'] ?? 'unknown';
    $status['last_scanned_height'] = (int)($scan['last_scanned_height'] ?? 0);
    $status['scanner_updated_at'] = $scan['updated_at'] ?? null;
} catch (Throwable $e) {
    $status['scanner'] = 'error';
}

$http = ($status['db'] === 'ok' && $status['rpc'] === 'ok') ? 200 : 503;
http_response_code($http);
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
