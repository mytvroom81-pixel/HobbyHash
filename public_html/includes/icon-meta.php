<?php
declare(strict_types=1);
$hobcFaviconUrl = function_exists('hobc_public_asset_url') ? hobc_public_asset_url('branding.favicon_url', '/favicon.ico') : '/favicon.ico';
$hobcThemeColor = function_exists('hobc_public_setting_text') ? hobc_public_setting_text('branding.primary_theme_color', '#f6b928') : '#f6b928';
$hobcTileColor = function_exists('hobc_public_setting_text') ? hobc_public_setting_text('branding.accent_color', '#050708') : '#050708';
$hobcAppName = function_exists('hobc_public_setting_text') ? hobc_public_setting_text('coin.ticker', 'HOBC') : 'HOBC';
?>
<link rel="icon" href="<?= htmlspecialchars($hobcFaviconUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" sizes="any">
<link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="application-name" content="<?= htmlspecialchars($hobcAppName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($hobcAppName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<meta name="theme-color" content="<?= htmlspecialchars($hobcThemeColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<meta name="msapplication-TileColor" content="<?= htmlspecialchars($hobcTileColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<meta name="msapplication-config" content="/browserconfig.xml">
