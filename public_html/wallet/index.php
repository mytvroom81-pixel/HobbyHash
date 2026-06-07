<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/public_settings.php';

if (auth_current_user()) {
    wallet_redirect(wallet_url('/dashboard.php'));
}

$pageId = 'wallet';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'wallet';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <?php if (hobc_public_feature_disabled('wallet.public_enabled')): ?>
      <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= h(hobc_public_setting_text('wallet.maintenance_message', 'The HOBC custodial web wallet is temporarily unavailable.')) ?></p></div></section>
    <?php else: ?>
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow_active') ?></span><h1><?= hobc_tpe($pageId, 'hero.title_active') ?></h1><p><?= hobc_tpe($pageId, 'body.p1') ?></p></div></section>
    <section class="notice"><strong><?= hobc_te('wallet.custodial_notice.label') ?></strong> <?= h(hobc_public_setting_text('legal.wallet_custody_notice', hobc_t('wallet.custodial_notice.body'))) ?></section>
    <section class="grid cards"><a class="metric-card metric-link" href="/wallet/login.php"><span class="metric-label"><?= hobc_tpe($pageId, 'label.existing_user') ?></span><strong><?= hobc_tpe($pageId, 'label.login') ?></strong></a><a class="metric-card metric-link" href="/wallet/register.php"><span class="metric-label"><?= hobc_tpe($pageId, 'label.new_user') ?></span><strong><?= hobc_tpe($pageId, 'label.register') ?></strong></a><a class="metric-card metric-link" href="/wallet/dashboard.php"><span class="metric-label"><?= hobc_tpe($pageId, 'label.wallet_app') ?></span><strong><?= hobc_tpe($pageId, 'label.dashboard') ?></strong></a></section>
    <section class="card"><h2><?= hobc_te('wallet.windows_standalone.heading') ?></h2><p><?= hobc_te('wallet.windows_standalone.body') ?></p><a class="button" href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_te('button.windows_wallet') ?></a></section>
    <section class="grid two"><article class="card"><h2><?= hobc_tpe($pageId, 'heading.deposits') ?></h2><p><?= hobc_tpe($pageId, 'body.p2') ?></p><a class="button" href="/wallet/deposit.php"><?= hobc_tpe($pageId, 'button.deposit_page') ?></a></article><article class="card"><h2><?= hobc_tpe($pageId, 'heading.withdrawals') ?></h2><p><?= hobc_tpe($pageId, 'body.p3') ?></p><a class="button" href="/wallet/withdraw.php"><?= hobc_tpe($pageId, 'button.withdraw_page') ?></a></article></section>
    <section class="card"><h2><?= hobc_tpe($pageId, 'heading.public_wallet_status') ?></h2><div class="table-like"><div class="table-row"><span><?= hobc_tpe($pageId, 'label.status') ?></span><strong data-api-value="/api/wallet/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></div><div class="table-row"><span><?= hobc_tpe($pageId, 'label.deposits') ?></span><strong data-api-value="/api/wallet/status" data-field="deposits" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div><div class="table-row"><span><?= hobc_tpe($pageId, 'label.withdrawals') ?></span><strong data-api-value="/api/wallet/status" data-field="withdrawals" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div><div class="table-row"><span><?= hobc_tpe($pageId, 'label.scanner') ?></span><strong data-api-value="/api/wallet/status" data-field="scanner_status" data-fallback="<?= hobc_te('status.not_available') ?>"><?= hobc_te('status.not_available') ?></strong></div></div></section>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
