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
if ($data === []) {
    $data = $_POST;
}

$fileUrl = trim((string)($data['file_url'] ?? ''));
$referrer = trim((string)($data['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')));

if ($fileUrl === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_file_url']);
    exit;
}

$path = parse_url($fileUrl, PHP_URL_PATH);
if (!is_string($path) || !str_starts_with($path, '/downloads/')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_file_url']);
    exit;
}

recordDownloadEvent(null, $fileUrl, $referrer);

echo json_encode(['ok' => true]);
