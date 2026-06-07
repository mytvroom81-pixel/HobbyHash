<?php declare(strict_types=1); ?>
<section class="status-bar" aria-label="<?= hobc_te('wallet.live_status') ?>">
  <a href="<?= hobc_e(hobc_pp('/stats/')) ?>" class="status-pill"><span><?= hobc_te('status.chain') ?></span><strong data-api-value="/api/chain/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></a>
  <a href="<?= hobc_e(hobc_pp('/stats/')) ?>" class="status-pill"><span><?= hobc_te('status.height') ?></span><strong data-api-value="/api/chain/status" data-field="blocks" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></a>
  <a href="<?= hobc_e(hobc_pp('/pool/main/')) ?>" class="status-pill"><span><?= hobc_te('status.main_pool') ?></span><strong data-api-value="/api/pool/main/status?lite=1" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></a>
  <a href="<?= hobc_e(hobc_pp('/pool/nano/')) ?>" class="status-pill"><span><?= hobc_te('status.nano_pool') ?></span><strong data-api-value="/api/pool/nano/status?lite=1" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></a>
  <a href="<?= hobc_e(hobc_pp('/wallet/')) ?>" class="status-pill"><span><?= hobc_te('status.wallet') ?></span><strong data-api-value="/api/wallet/status" data-field="status" data-fallback="<?= hobc_te('status.offline') ?>"><?= hobc_te('status.offline') ?></strong></a>
  <a href="<?= hobc_e(hobc_pp('/explorer/')) ?>" class="status-pill"><span><?= hobc_te('status.explorer') ?></span><strong data-api-value="/api/explorer/status" data-field="status" data-fallback="<?= hobc_te('status.syncing') ?>"><?= hobc_te('status.syncing') ?></strong></a>
</section>
