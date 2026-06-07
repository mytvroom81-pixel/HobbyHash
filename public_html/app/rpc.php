<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function rpc_call(string $method, array $params = [], ?string $wallet = null): mixed
{
    $cfg = wallet_config()['rpc'];
    $url = rtrim($cfg['url'], '/');
    $walletName = $wallet ?? ($cfg['wallet'] ?? null);
    if ($walletName) {
        $url .= '/wallet/' . rawurlencode($walletName);
    }

    $payload = json_encode([
        'jsonrpc' => '1.0',
        'id' => 'hobc-wallet',
        'method' => $method,
        'params' => $params,
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $cfg['username'] . ':' . $cfg['password'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => (int)($cfg['timeout_seconds'] ?? 8),
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        throw new RuntimeException('RPC transport error: ' . $error);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('RPC http error: ' . $status);
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException('RPC decode error');
    }
    if (!empty($json['error'])) {
        $message = is_array($json['error']) ? ($json['error']['message'] ?? 'Unknown RPC error') : 'Unknown RPC error';
        throw new RuntimeException('RPC error: ' . $message);
    }
    return $json['result'] ?? [];
}

function rpc_is_online(): bool
{
    try {
        $res = rpc_call('getblockchaininfo', [], null);
        return is_array($res) && isset($res['chain']);
    } catch (Throwable $e) {
        return false;
    }
}
