<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'roadmap';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'roadmap';
$robotsMeta = 'noindex, follow';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
require __DIR__ . '/../includes/status-bar.php';
?>
<main id="main-content">
  <div class="page">
    <section class="hero"><div class="hero-content"><span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span><h1><?= hobc_tpe($pageId, 'hero.title') ?></h1><p><?= hobc_tpe($pageId, 'hero.lead') ?></p></div></section>
    <section class="grid cards"><article class="card"><h3><?= hobc_tpe($pageId, 'card.shell.title') ?></h3><p><?= hobc_tpe($pageId, 'card.shell.body') ?></p></article><article class="card"><h3><?= hobc_tpe($pageId, 'card.api.title') ?></h3><p><?= hobc_tpe($pageId, 'card.api.body') ?></p></article><article class="card"><h3><?= hobc_tpe($pageId, 'card.explorer.title') ?></h3><p><?= hobc_tpe($pageId, 'card.explorer.body') ?></p></article><article class="card"><h3><?= hobc_tpe($pageId, 'card.reserve.title') ?></h3><p><?= hobc_tpe($pageId, 'card.reserve.body') ?></p></article></section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
