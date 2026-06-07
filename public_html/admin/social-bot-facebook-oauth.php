<?php
declare(strict_types=1);

/**
 * Facebook OAuth callback — no admin session required.
 * SameSite=Strict blocks admin cookies on cross-site redirects from Meta,
 * so token exchange validates OAuth state from the bot database instead.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/social_bot_admin.php';

function social_bot_facebook_oauth_render(string $title, string $message, bool $ok): void
{
    admin_security_headers();
    $color = $ok ? '#7dffb2' : '#ff9a9a';
    $link = admin_url('/social-bot.php?tab=platforms');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= h($title) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Inter,system-ui,sans-serif;background:#050708;color:#f7f5ed;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .box{max-width:520px;background:#101417;border:1px solid rgba(246,185,40,.25);border-radius:16px;padding:24px;line-height:1.5}
    h1{margin:0 0 12px;font-size:22px;color:<?= h($color) ?>}
    p{color:#b9b5a6}
    a{color:#ffd764}
  </style>
</head>
<body>
  <div class="box">
    <h1><?= h($title) ?></h1>
    <p><?= h($message) ?></p>
    <p><a href="<?= h($link) ?>">Back to Social Bot → Platforms</a></p>
  </div>
</body>
</html>
    <?php
    exit;
}

if (!social_bot_available()) {
    social_bot_facebook_oauth_render('Social bot unavailable', 'The social bot database is not available.', false);
}

try {
    if (trim((string)($_GET['error'] ?? '')) !== '') {
        $desc = trim((string)($_GET['error_description'] ?? $_GET['error'] ?? 'Authorization denied'));
        throw new RuntimeException($desc);
    }

    $code = trim((string)($_GET['code'] ?? ''));
    $state = trim((string)($_GET['state'] ?? ''));
    if ($code === '' || $state === '') {
        throw new RuntimeException('Missing authorization code from Facebook. Start Connect again from admin.');
    }

    social_bot_facebook_oauth_handle_callback($_GET, 'facebook_oauth_callback');

    $fb = social_bot_platform_credentials()['facebook'];
    $label = trim((string)($fb['pageName'] ?? ''));
    if ($label === '') {
        $label = 'your Facebook Page';
    }

    social_bot_facebook_oauth_render(
        'Facebook connected',
        'Long-lived Page token saved for ' . $label . '. The bot can now post and reply on Facebook.',
        true
    );
} catch (Throwable $e) {
    social_bot_facebook_oauth_render('Facebook connection failed', $e->getMessage(), false);
}
