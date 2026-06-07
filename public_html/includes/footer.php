<?php
declare(strict_types=1);
$footerSiteName = function_exists('hobc_public_setting_text') ? hobc_public_setting_text('site.name', 'HobbyHash Coin') : 'HobbyHash Coin';
$footerCoinName = function_exists('hobc_public_setting_text') ? hobc_public_setting_text('coin.name', 'HobbyHash Coin') : 'HobbyHash Coin';
$footerTicker = function_exists('hobc_public_setting_text') ? hobc_public_setting_text('coin.ticker', 'HOBC') : 'HOBC';
$footerLogo = function_exists('hobc_public_asset_url') ? hobc_public_asset_url('branding.logo_url', '/assets/images/logo-medallion.png') : '/assets/images/logo-medallion.png';
$footerText = trim(function_exists('hobc_public_setting_text') ? hobc_public_setting_text('branding.footer_text', '') : '');
$riskNotice = trim(function_exists('hobc_public_setting_text') ? hobc_public_setting_text('legal.risk_notice', '') : '');
$footerBlurb = $footerText !== ''
    ? nl2br(htmlspecialchars($footerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
    : (function_exists('hobc_te') ? hobc_te('footer.default_blurb', [], 'SHA-256 home solo mining, nano miner friendly, solo pools only.') : 'SHA-256 home solo mining, nano miner friendly, solo pools only.');
?>
<footer class="site-footer">
  <div class="footer-shell">
    <div class="footer-brand-block">
      <div class="footer-brand">
        <img src="<?= htmlspecialchars($footerLogo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($footerTicker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> coin logo" class="footer-logo">
        <div class="footer-brand-copy">
          <strong class="footer-brand-name"><?= htmlspecialchars($footerCoinName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($footerTicker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</strong>
          <p class="footer-tagline"><?= $footerBlurb ?></p>
        </div>
      </div>
      <?php require __DIR__ . '/social-links.php'; ?>
    </div>
    <nav class="footer-nav" aria-label="<?= function_exists('hobc_te') ? hobc_e(hobc_te('footer.nav_aria', [], 'Footer navigation')) : 'Footer navigation' ?>">
      <p class="footer-section-label"><?= function_exists('hobc_te') ? hobc_te('footer.links_heading', [], 'Links') : 'Links' ?></p>
      <ul class="footer-links">
        <li><a href="<?= function_exists('hobc_pp') ? hobc_e(hobc_pp('/docs/')) : '/docs/' ?>"><?= function_exists('hobc_te') ? hobc_te('footer.docs', [], 'Docs') : 'Docs' ?></a></li>
        <li><a href="<?= function_exists('hobc_pp') ? hobc_e(hobc_pp('/exchange-listing/')) : '/exchange-listing/' ?>"><?= function_exists('hobc_te') ? hobc_te('footer.exchange_listing', [], 'Exchange Listing Packet') : 'Exchange Listing Packet' ?></a></li>
        <li><a href="<?= function_exists('hobc_pp') ? hobc_e(hobc_pp('/contact/')) : '/contact/' ?>"><?= function_exists('hobc_te') ? hobc_te('footer.support', [], 'Support') : 'Support' ?></a></li>
        <li><a href="<?= function_exists('hobc_pp') ? hobc_e(hobc_pp('/privacy/')) : '/privacy/' ?>"><?= function_exists('hobc_te') ? hobc_te('footer.privacy', [], 'Privacy Policy') : 'Privacy Policy' ?></a></li>
        <li><a href="<?= function_exists('hobc_pp') ? hobc_e(hobc_pp('/terms/')) : '/terms/' ?>"><?= function_exists('hobc_te') ? hobc_te('footer.terms', [], 'Terms') : 'Terms' ?></a></li>
      </ul>
    </nav>
  </div>
  <div class="footer-meta">
    <p class="fine-print"><?= htmlspecialchars($footerSiteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= function_exists('hobc_te') ? hobc_te('footer.data_notice', [], 'shows real project data only: unavailable live values are shown as Syncing, Offline, Pending launch, or Not available yet.') : 'shows real project data only: unavailable live values are shown as Syncing, Offline, Pending launch, or Not available yet.' ?></p>
    <?php if ($riskNotice !== ''): ?><p class="fine-print"><?= nl2br(htmlspecialchars($riskNotice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></p><?php endif; ?>
  </div>
</footer>
<?php if (function_exists('hobc_i18n_render_js_bootstrap')) { hobc_i18n_render_js_bootstrap(); } ?>
<?php $hobcLangJsVersion = (string)@filemtime(__DIR__ . '/../assets/js/hobc-lang.js'); ?>
<script src="/assets/js/hobc-lang.js?v=<?= htmlspecialchars($hobcLangJsVersion !== '' ? $hobcLangJsVersion : '1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" defer></script>
<?php $hobcJsVersion = (string)@filemtime(__DIR__ . '/../assets/js/hobc.js'); ?>
<script src="/assets/js/hobc.js?v=<?= htmlspecialchars($hobcJsVersion !== '' ? $hobcJsVersion : '1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" defer></script>
<?php if (!empty($statsModule)): ?>
<?php $hobcStatsJsVersion = (string)@filemtime(__DIR__ . '/../assets/js/hobc-stats-overload.js'); ?>
<script src="/assets/js/hobc-stats-overload.js?v=<?= htmlspecialchars($hobcStatsJsVersion !== '' ? $hobcStatsJsVersion : '1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" defer></script>
<?php endif; ?>
</body>
</html>
