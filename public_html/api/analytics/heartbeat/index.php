<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/analytics.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

$url = analytics_normalize_tracking_url(trim((string)($data['url'] ?? '')));
if ($url === '/') {
    $refererFallback = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($refererFallback !== '') {
        $url = analytics_normalize_tracking_url($refererFallback);
    }
}

analytics_record_heartbeat([
    'url' => $url,
    'referrer' => trim((string)($data['referrer'] ?? '')),
]);

echo json_encode(['ok' => true]);
