<?php
declare(strict_types=1);

function mining_quick_step_icon(string $type, string $label): string
{
    $gradientId = 'mqs-gold-' . preg_replace('/[^a-z0-9-]+/i', '-', $type);
    $icons = [
        'wallet' => '<path d="M22 30h42a5 5 0 0 1 5 5v14a5 5 0 0 1-5 5H22a5 5 0 0 1-5-5V35a5 5 0 0 1 5-5z" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linejoin="round"/><path d="M26 30v-5a8 8 0 0 1 8-8h18a8 8 0 0 1 8 8v5" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linecap="round"/><circle cx="52" cy="42" r="3.5" fill="url(#' . $gradientId . ')"/>',
        'miners' => '<path d="M18 48 32 18l8 14 10-8 18 24" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 48h38" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linecap="round"/><path d="M32 18v8M40 18v5" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linecap="round"/>',
        'blocks' => '<path d="M43 14 62 26v24L43 62 24 50V26z" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linejoin="round"/><path d="M43 14v24M24 26l19 12 19-12M43 38 24 50M43 38l19 12" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="2.25" opacity="0.75"/><path d="M43 8v4M43 62v4M16 38h4M66 38h4" fill="none" stroke="url(#' . $gradientId . ')" stroke-width="3" stroke-linecap="round"/>',
    ];
    $paths = $icons[$type] ?? '';

    return '<svg class="hobc-feature-icon hobc-feature-icon-svg" viewBox="0 0 86 76" role="img" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . h($gradientId) . '" x1="43" y1="10" x2="43" y2="58" gradientUnits="userSpaceOnUse">'
        . '<stop stop-color="#ffd764"/><stop offset="0.55" stop-color="#f6b928"/><stop offset="1" stop-color="#e5a817"/></linearGradient></defs>'
        . $paths
        . '<text class="hobc-feature-icon-label" x="43" y="72" text-anchor="middle">' . h($label) . '</text>'
        . '</svg>';
}
?>
<section class="mining-quick-steps" aria-label="<?= hobc_te('mining_steps.aria_label') ?>">
  <div class="mining-quick-steps-intro">
    <h2><?= hobc_te('mining_steps.title') ?></h2>
    <p><?= hobc_te('mining_steps.tagline') ?></p>
  </div>
  <ol class="grid cards mining-quick-steps-list">
    <li class="card mining-quick-step">
      <div class="mining-quick-step-body">
        <div class="mining-quick-step-icon"><?= mining_quick_step_icon('wallet', hobc_t('mining_steps.step1.icon_label')) ?></div>
        <h3><?= hobc_te('mining_steps.step1.title') ?></h3>
        <p><?= hobc_te('mining_steps.step1.body') ?></p>
        <p class="mining-quick-step-links">
          <a href="<?= hobc_e(hobc_pp('/wallet/')) ?>"><?= hobc_te('mining_steps.step1.link_wallet') ?></a>
          <span class="mining-quick-step-sep" aria-hidden="true">·</span>
          <a href="<?= hobc_e(hobc_pp('/downloads/')) ?>"><?= hobc_te('mining_steps.step1.link_download') ?></a>
        </p>
      </div>
    </li>
    <li class="card mining-quick-step">
      <div class="mining-quick-step-body">
        <div class="mining-quick-step-icon"><?= mining_quick_step_icon('miners', hobc_t('mining_steps.step2.icon_label')) ?></div>
        <h3><?= hobc_te('mining_steps.step2.title') ?></h3>
        <p><?= hobc_te('mining_steps.step2.body') ?></p>
        <code class="mining-quick-step-code">YOUR_HOBC_ADDRESS.worker1</code>
        <p class="mining-quick-step-links">
          <a href="<?= hobc_e(hobc_pp('/mining/')) ?>"><?= hobc_te('mining_steps.step2.link') ?></a>
        </p>
      </div>
    </li>
    <li class="card mining-quick-step">
      <div class="mining-quick-step-body">
        <div class="mining-quick-step-icon"><?= mining_quick_step_icon('blocks', hobc_t('mining_steps.step3.icon_label')) ?></div>
        <h3><?= hobc_te('mining_steps.step3.title') ?></h3>
        <p><?= hobc_te('mining_steps.step3.body') ?></p>
        <p class="mining-quick-step-links">
          <a href="<?= hobc_e(hobc_pp('/mining/')) ?>"><?= hobc_te('mining_steps.step3.link') ?></a>
        </p>
      </div>
    </li>
  </ol>
</section>
