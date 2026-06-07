<?php
declare(strict_types=1);
$activePage = $activePage ?? '';
$sectionLabel = $pageTitle ?? '';
?>
<nav class="site-nav" aria-label="<?= hobc_te('nav.aria_main') ?>">
  <?php foreach (hobc_pages() as $page): ?>
    <?php
      $url = $page['url'];
      if ($page['key'] === 'contact' && $activePage !== '' && $activePage !== 'contact') {
          $url = '/contact/?section=' . rawurlencode((string)$sectionLabel);
      }
    ?>
    <a href="<?= hobc_e($url) ?>" class="<?= $activePage === $page['key'] ? 'active' : '' ?>"><?= hobc_e($page['label']) ?></a>
  <?php endforeach; ?>
</nav>
