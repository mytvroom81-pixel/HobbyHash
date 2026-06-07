<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/i18n_catalog.php';

function mailer_i18n_locale(?string $locale = null): string
{
    if ($locale !== null && trim($locale) !== '') {
        return class_exists('HobcSupportI18n')
            ? HobcSupportI18n::normalizeLocale($locale)
            : trim($locale);
    }
    if (function_exists('hobc_i18n_locale')) {
        return hobc_i18n_locale();
    }

    return function_exists('hobc_i18n_default_locale') ? hobc_i18n_default_locale() : 'en';
}

function mailer_support_ticket_url(string $token, ?string $locale = null): string
{
    if (!function_exists('hobc_i18n_public_url')) {
        require_once __DIR__ . '/i18n.php';
    }
    $locale = mailer_i18n_locale($locale);
    $base = function_exists('hobc_i18n_public_url')
        ? hobc_i18n_public_url('/ticket.php', $locale)
        : 'https://hobbyhashcoin.com/ticket.php';

    return $base . '?token=' . rawurlencode($token);
}

function mailer_i18n_t(string $key, array $vars = [], string $fallback = '', ?string $locale = null): string
{
    $locale = mailer_i18n_locale($locale);
    $value = function_exists('hobc_i18n_lookup') ? hobc_i18n_lookup($key, $locale) : null;
    if ($value === null || $value === '') {
        $value = $fallback !== '' ? $fallback : $key;
    }

    return function_exists('hobc_i18n_replace_vars')
        ? hobc_i18n_replace_vars($value, $vars)
        : $value;
}

function mailer_i18n_tp(string $page, string $key, array $vars = [], string $fallback = '', ?string $locale = null): string
{
    $locale = mailer_i18n_locale($locale);
    $value = hobc_i18n_page_lookup($page, $key, $locale);
    if ($value === null || $value === '') {
        $value = $fallback !== '' ? $fallback : ($page . '.' . $key);
    }

    return hobc_i18n_replace_vars($value, $vars);
}

function mailer_support_html_localized(string $title, array $rows, string $messageHtml, string $trackUrl = '', ?string $locale = null): string
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
    $buttonLabel = mailer_i18n_t('email.support.open_ticket', [], 'Open Ticket', $locale);
    $button = $trackUrl !== ''
        ? '<p style="margin:24px 0;"><a href="' . htmlspecialchars($trackUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;background:#f6b928;color:#110d05;text-decoration:none;font-weight:800;padding:12px 18px;border-radius:999px;">'
            . htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></p>'
        : '';

    $brand = mailer_i18n_t('email.support.brand_eyebrow', [], 'HobbyHash Coin Support', $locale);
    $brandSub = mailer_i18n_t('email.support.brand_sub', [], 'HOBC Web Wallet and portal support', $locale);
    $doNotReply = mailer_i18n_t('email.support.do_not_reply', [], 'Do not reply to this email address. Please use the ticket system so your support history stays attached to your request.', $locale);
    $thanks = mailer_i18n_t('email.support.thanks', [], 'Thank you,', $locale);
    $team = mailer_i18n_t('email.support.team', [], 'HOBC Support', $locale);
    $tagline = mailer_i18n_t('email.support.tagline', [], 'Home solo mining · Nano miner friendly · Transparent support', $locale);

    return '<!doctype html><html><body style="margin:0;background:#050708;color:#f7f5ed;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:720px;margin:0 auto;padding:28px 16px;">'
        . '<div style="border:1px solid rgba(246,185,40,.35);border-radius:18px;overflow:hidden;background:#0b0f12;">'
        . '<div style="padding:28px;background:linear-gradient(135deg,#090d10,#15191c);border-bottom:1px solid rgba(246,185,40,.35);">'
        . '<div style="color:#ffd764;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;">' . htmlspecialchars($brand, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
        . '<h1 style="margin:8px 0 0;color:#f7f5ed;font-size:30px;line-height:1.1;">' . $safeTitle . '</h1>'
        . '<p style="margin:10px 0 0;color:#b9b5a6;">' . htmlspecialchars($brandSub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '</div>'
        . '<div style="padding:26px;">'
        . '<div style="line-height:1.65;color:#f7f5ed;">' . $messageHtml . '</div>'
        . ($rowHtml !== '' ? '<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:18px;border-top:1px solid rgba(255,255,255,.08);">' . $rowHtml . '</table>' : '')
        . $button
        . '<div style="margin-top:24px;padding:14px;border:1px solid rgba(255,215,100,.35);border-radius:12px;background:rgba(255,215,100,.08);color:#ffd764;font-weight:700;">' . htmlspecialchars($doNotReply, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'
        . '<p style="margin-top:24px;color:#b9b5a6;line-height:1.6;">' . htmlspecialchars($thanks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><strong style="color:#f7f5ed;">' . htmlspecialchars($team, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong><br>HobbyHash Coin command center<br>' . htmlspecialchars($tagline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '</div></div></div></body></html>';
}

function mailer_support_text_localized(string $title, array $rows, string $message, string $trackUrl = '', ?string $locale = null): string
{
    $lines = [$title, '', $message, ''];
    foreach ($rows as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }
    if ($trackUrl !== '') {
        $lines[] = '';
        $lines[] = mailer_i18n_t('email.support.open_ticket', [], 'Open Ticket', $locale) . ': ' . $trackUrl;
    }
    $lines[] = '';
    $lines[] = mailer_i18n_t('email.support.do_not_reply', [], 'Do not reply to this email address. Please use the ticket system so your support history stays attached to your request.', $locale);
    $lines[] = '';
    $lines[] = mailer_i18n_t('email.support.thanks', [], 'Thank you,', $locale);
    $lines[] = mailer_i18n_t('email.support.team', [], 'HOBC Support', $locale);
    $lines[] = 'HobbyHash Coin command center';
    return implode("\n", $lines);
}

function mailer_support_rows_localized(array $values, ?string $locale = null): array
{
    $map = [
        'Section' => 'email.support.row.section',
        'Subject' => 'email.support.row.subject',
        'Tracking link' => 'email.support.row.tracking_link',
        'Status' => 'email.support.row.status',
        'New status' => 'email.support.row.new_status',
    ];
    $rows = [];
    foreach ($values as $label => $value) {
        $key = $map[$label] ?? null;
        $rows[$key ? mailer_i18n_t($key, [], $label, $locale) : $label] = $value;
    }

    return $rows;
}

function account_email_template_localized(string $title, string $bodyHtml, string $code, ?string $locale = null): array
{
    $brand = mailer_i18n_t('email.security.brand', [], 'HobbyHash Coin Security', $locale);
    $codeLabel = mailer_i18n_t('email.security.verify_code_label', [], 'Verification Code', $locale);
    $expires = mailer_i18n_t('email.security.expires_soon', [], 'This code expires soon. If you did not request this, you can ignore this email.', $locale);
    $team = mailer_i18n_t('email.security.team', [], 'HOBC Security', $locale);

    $html = '<!doctype html><html><body style="margin:0;background:#050708;color:#f7f5ed;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:680px;margin:0 auto;padding:28px 16px;">'
        . '<div style="border:1px solid rgba(246,185,40,.35);border-radius:18px;overflow:hidden;background:#0b0f12;">'
        . '<div style="padding:26px;background:linear-gradient(135deg,#090d10,#171d22);border-bottom:1px solid rgba(246,185,40,.3);">'
        . '<div style="color:#ffd764;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;">' . h($brand) . '</div>'
        . '<h1 style="margin:8px 0 0;color:#f7f5ed;font-size:28px;">' . h($title) . '</h1></div>'
        . '<div style="padding:26px;color:#f7f5ed;line-height:1.65;">'
        . $bodyHtml
        . '<div style="margin:22px 0;padding:16px;border:1px solid rgba(246,185,40,.34);border-radius:14px;background:#080b0d;text-align:center;">'
        . '<div style="color:#b9b5a6;font-size:12px;text-transform:uppercase;letter-spacing:.12em;">' . h($codeLabel) . '</div>'
        . '<div style="color:#ffd764;font-size:34px;font-weight:900;letter-spacing:.18em;margin-top:6px;">' . h($code) . '</div></div>'
        . '<p style="color:#b9b5a6;">' . h($expires) . '</p>'
        . '<p style="color:#b9b5a6;">' . mailer_i18n_t('email.support.thanks', [], 'Thank you,', $locale) . '<br><strong style="color:#f7f5ed;">' . h($team) . '</strong></p>'
        . '</div></div></div></body></html>';
    $text = $title . "\n\n" . trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)))
        . "\n\n" . $codeLabel . ': ' . $code
        . "\n\n" . $expires . "\n\n" . $team;
    return [$text, $html];
}

function account_send_email_code_localized(string $email, string $titleKey, string $bodyKey, string $code, ?string $locale = null): bool
{
    $locale = mailer_i18n_locale($locale);
    $title = mailer_i18n_t($titleKey, [], $titleKey, $locale);
    $bodyText = mailer_i18n_t($bodyKey, [], $bodyKey, $locale);
    $bodyHtml = '<p>' . htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    [$text, $html] = account_email_template_localized($title, $bodyHtml, $code, $locale);
    $sent = mailer_send($email, $title, $text, $html);
    if (!$sent) {
        throw new RuntimeException('Verification email could not be sent.');
    }

    return true;
}
