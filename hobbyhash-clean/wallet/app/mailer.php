<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function mailer_settings(): array
{
    $row = wallet_db()->query("SELECT * FROM smtp_settings WHERE id = 1")->fetch();
    return $row ?: ['is_enabled' => 0];
}

function mailer_decode_password(?string $encoded): string
{
    if (!$encoded) {
        return '';
    }
    $decoded = base64_decode($encoded, true);
    return is_string($decoded) ? $decoded : '';
}

function mailer_domain(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'hobbyhashcoin.com');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'hobbyhashcoin.com';
    return $host;
}

function mailer_headers(string $fromEmail, string $fromName, ?string $boundary = null): array
{
    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
    ];
    if ($boundary !== null) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }
    return $headers;
}

function mailer_normalize_crlf(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return str_replace("\n", "\r\n", $value);
}

function mailer_html_to_text(string $html): string
{
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
    $html = preg_replace('/<\/p>/i', "\n\n", $html) ?? $html;
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function mailer_build_message(string $textBody, ?string $htmlBody, ?string &$boundary): string
{
    if ($htmlBody === null || trim($htmlBody) === '') {
        $boundary = null;
        return mailer_normalize_crlf($textBody);
    }

    $boundary = 'hobc_' . bin2hex(random_bytes(12));
    $message = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . mailer_normalize_crlf($textBody) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . mailer_normalize_crlf($htmlBody) . "\r\n\r\n"
        . '--' . $boundary . "--";
    return $message;
}

function mailer_support_html(string $title, array $rows, string $messageHtml, string $trackUrl = ''): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rowHtml = '';
    foreach ($rows as $label => $value) {
        $rowHtml .= '<tr><td style="padding:8px 0;color:#b9b5a6;font-size:12px;text-transform:uppercase;letter-spacing:.08em;width:130px;">'
            . htmlspecialchars((string)$label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</td><td style="padding:8px 0;color:#f7f5ed;word-break:break-word;">'
            . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</td></tr>';
    }
    $button = $trackUrl !== ''
        ? '<p style="margin:24px 0;"><a href="' . htmlspecialchars($trackUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;background:#f6b928;color:#110d05;text-decoration:none;font-weight:800;padding:12px 18px;border-radius:999px;">Open Ticket</a></p>'
        : '';

    return '<!doctype html><html><body style="margin:0;background:#050708;color:#f7f5ed;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:720px;margin:0 auto;padding:28px 16px;">'
        . '<div style="border:1px solid rgba(246,185,40,.35);border-radius:18px;overflow:hidden;background:#0b0f12;">'
        . '<div style="padding:28px;background:linear-gradient(135deg,#090d10,#15191c);border-bottom:1px solid rgba(246,185,40,.35);">'
        . '<div style="color:#ffd764;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;">HobbyHash Coin Support</div>'
        . '<h1 style="margin:8px 0 0;color:#f7f5ed;font-size:30px;line-height:1.1;">' . $safeTitle . '</h1>'
        . '<p style="margin:10px 0 0;color:#b9b5a6;">HOBC Web Wallet and portal support</p>'
        . '</div>'
        . '<div style="padding:26px;">'
        . '<div style="line-height:1.65;color:#f7f5ed;">' . $messageHtml . '</div>'
        . ($rowHtml !== '' ? '<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:18px;border-top:1px solid rgba(255,255,255,.08);">' . $rowHtml . '</table>' : '')
        . $button
        . '<div style="margin-top:24px;padding:14px;border:1px solid rgba(255,215,100,.35);border-radius:12px;background:rgba(255,215,100,.08);color:#ffd764;font-weight:700;">Do not reply to this email address. Please use the ticket system so your support history stays attached to your request.</div>'
        . '<p style="margin-top:24px;color:#b9b5a6;line-height:1.6;">Thank you,<br><strong style="color:#f7f5ed;">HOBC Support</strong><br>HobbyHash Coin command center<br>Home solo mining &bull; Nano miner friendly &bull; Transparent support</p>'
        . '</div></div></div></body></html>';
}

function mailer_support_text(string $title, array $rows, string $message, string $trackUrl = ''): string
{
    $lines = [$title, '', $message, ''];
    foreach ($rows as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }
    if ($trackUrl !== '') {
        $lines[] = '';
        $lines[] = 'Open ticket: ' . $trackUrl;
    }
    $lines[] = '';
    $lines[] = 'Do not reply to this email address. Please use the ticket system so your support history stays attached to your request.';
    $lines[] = '';
    $lines[] = 'Thank you,';
    $lines[] = 'HOBC Support';
    $lines[] = 'HobbyHash Coin command center';
    return implode("\n", $lines);
}

function mailer_send_php_mail(string $to, string $subject, string $body, string $fromEmail, string $fromName, ?string $htmlBody = null): bool
{
    $boundary = null;
    $message = mailer_build_message($body, $htmlBody, $boundary);
    return mail($to, $subject, $message, implode("\r\n", mailer_headers($fromEmail, $fromName, $boundary)));
}

function mailer_smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function mailer_smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $response = mailer_smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP command failed: ' . trim($response));
    }
    return $response;
}

function mailer_smtp_send(array $settings, string $to, string $subject, string $body, string $fromEmail, string $fromName, ?string $htmlBody = null): bool
{
    $host = trim((string)($settings['host'] ?? ''));
    $port = (int)($settings['port'] ?? 587);
    $encryption = (string)($settings['encryption'] ?? 'tls');
    $username = trim((string)($settings['username'] ?? ''));
    $password = mailer_decode_password((string)($settings['password_enc'] ?? ''));

    if ($host === '' || $port <= 0) {
        throw new RuntimeException('SMTP host or port missing');
    }

    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('SMTP connect failed: ' . $errstr);
    }

    stream_set_timeout($socket, 10);
    try {
        $greeting = mailer_smtp_read($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
        }

        mailer_smtp_command($socket, 'EHLO ' . mailer_domain(), [250]);
        if ($encryption === 'tls') {
            mailer_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed');
            }
            mailer_smtp_command($socket, 'EHLO ' . mailer_domain(), [250]);
        }

        if ($username !== '') {
            mailer_smtp_command($socket, 'AUTH LOGIN', [334]);
            mailer_smtp_command($socket, base64_encode($username), [334]);
            mailer_smtp_command($socket, base64_encode($password), [235]);
        }

        mailer_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        mailer_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        mailer_smtp_command($socket, 'DATA', [354]);

        $boundary = null;
        $messageBody = mailer_build_message($body, $htmlBody, $boundary);
        $headers = array_merge(mailer_headers($fromEmail, $fromName, $boundary), [
            'To: ' . $to,
            'Subject: ' . $subject,
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . mailer_domain() . '>',
        ]);
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $messageBody;
        $message = str_replace("\r\n.", "\r\n..", $message);
        fwrite($socket, $message . "\r\n.\r\n");
        $response = mailer_smtp_read($socket);
        if ((int)substr($response, 0, 3) !== 250) {
            throw new RuntimeException('SMTP DATA failed: ' . trim($response));
        }

        @mailer_smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        fclose($socket);
        throw $e;
    }
}

function mailer_send(string $to, string $subject, string $body, ?string $htmlBody = null): bool
{
    $settings = mailer_settings();
    $fromEmail = (string)($settings['from_email'] ?? '');
    $fromName = (string)($settings['from_name'] ?? 'HobbyHashCoin Support');
    if ($fromEmail === '') {
        wallet_log_error('mailer skipped missing from_email');
        return false;
    }

    if ((int)($settings['is_enabled'] ?? 0) === 1) {
        try {
            if (mailer_smtp_send($settings, $to, $subject, $body, $fromEmail, $fromName, $htmlBody)) {
                return true;
            }
        } catch (Throwable $e) {
            wallet_log_error('smtp send failed, falling back to php mail: ' . $e->getMessage());
        }
    }

    $sent = mailer_send_php_mail($to, $subject, $body, $fromEmail, $fromName, $htmlBody);
    if (!$sent) {
        wallet_log_error('php mail fallback failed to=' . $to . ' subject=' . $subject);
    }
    return $sent;
}
