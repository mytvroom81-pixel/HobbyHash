<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

$user = auth_require_user();
$addressId = (int)($_GET['address_id'] ?? 0);
if ($addressId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing address.';
    exit;
}

$stmt = wallet_db()->prepare(
    "SELECT address
     FROM deposit_addresses
     WHERE id = ? AND user_id = ? AND is_active = 1
     LIMIT 1"
);
$stmt->execute([$addressId, (int)$user['id']]);
$row = $stmt->fetch();
$address = trim((string)($row['address'] ?? ''));
if ($address === '' || !preg_match('/^[a-zA-Z0-9]{20,128}$/', $address)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Address not found.';
    exit;
}

$qrencode = '/usr/bin/qrencode';
if (!is_file($qrencode) || !is_executable($qrencode)) {
    wallet_log_error('QR generation failed: qrencode binary missing');
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR generator unavailable.';
    exit;
}

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open([$qrencode, '-t', 'SVG', '-o', '-', '-m', '1', '-s', '8', $address], $descriptors, $pipes);
if (!is_resource($process)) {
    wallet_log_error('QR generation failed: proc_open failed');
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR generator unavailable.';
    exit;
}

fclose($pipes[0]);
$svg = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0 || !is_string($svg) || trim($svg) === '') {
    wallet_log_error('QR generation failed: ' . trim((string)$error));
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR generator unavailable.';
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: private, no-store');
echo $svg;
