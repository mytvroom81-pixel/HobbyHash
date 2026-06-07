<?php
declare(strict_types=1);

if (!function_exists('hobc_i18n_enabled') || !hobc_i18n_enabled()) {
    return;
}

$currentLocale = hobc_i18n_locale();
$languages = hobc_i18n_config()['languages'] ?? [];
$currentPath = hobc_i18n_current_path();
$variant = $langSwitcherVariant ?? 'header';
$wrapperClass = 'lang-switcher lang-switcher--' . (preg_replace('/[^a-z0-9_-]/', '', $variant) ?: 'header');
$menuId = 'hobc-lang-menu-' . $variant;
$triggerId = 'hobc-lang-trigger-' . $variant;
$currentLabel = function_exists('hobc_i18n_language_selector_name')
    ? hobc_i18n_language_selector_name($currentLocale)
    : (string)($languages[$currentLocale]['native'] ?? $currentLocale);
?>
<div class="<?= hobc_e($wrapperClass) ?>" data-lang-switcher>
  <span class="lang-switcher-icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" width="14" height="14" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm7.93 9h-3.18a15.6 15.6 0 0 0-1.08-4.34A8.03 8.03 0 0 1 19.93 11ZM12 4c.95 1.4 1.72 3.17 2.12 5H9.88C10.28 7.17 11.05 5.4 12 4ZM4.33 11a8.03 8.03 0 0 1 3.43-4.34A15.6 15.6 0 0 0 6.68 11H4.33Zm2.35 2h3.18c.4 1.83 1.17 3.6 2.12 5a10.04 10.04 0 0 1-5.3-5Zm3.18 0h3.32c-.4 1.83-1.17 3.6-2.12 5-1.78-.95-3.22-2.39-4.2-5Zm5.5 0h3.18a10.04 10.04 0 0 1-4.2 5c.95-1.4 1.72-3.17 2.12-5Zm3.82-2h3.18a8.03 8.03 0 0 0-3.43-4.34 15.6 15.6 0 0 1-1.08 4.34Z"/></svg>
  </span>
  <label class="lang-switcher-label" for="<?= hobc_e($triggerId) ?>"><?= hobc_te('header.language_label', [], 'Language') ?></label>
  <button
    type="button"
    id="<?= hobc_e($triggerId) ?>"
    class="lang-switcher-trigger"
    aria-haspopup="listbox"
    aria-expanded="false"
    aria-controls="<?= hobc_e($menuId) ?>"
    title="<?= hobc_e($currentLabel) ?>"
    data-lang-trigger
    data-current-path="<?= hobc_e($currentPath) ?>"
    data-current-locale="<?= hobc_e($currentLocale) ?>"
  >
    <span class="lang-switcher-value"><?= hobc_e($currentLabel) ?></span>
    <span class="lang-switcher-chevron" aria-hidden="true"></span>
  </button>
  <ul
    id="<?= hobc_e($menuId) ?>"
    class="lang-switcher-menu"
    role="listbox"
    aria-label="<?= hobc_te('lang.select_aria', [], 'Select site language') ?>"
    data-lang-menu
    hidden
  >
    <?php foreach ($languages as $code => $meta): ?>
      <?php
        $display = function_exists('hobc_i18n_language_selector_name')
            ? hobc_i18n_language_selector_name($code)
            : (string)($meta['selector'] ?? $meta['native'] ?? $code);
        $selected = $code === $currentLocale;
      ?>
      <li
        role="option"
        aria-selected="<?= $selected ? 'true' : 'false' ?>"
        class="lang-switcher-option<?= $selected ? ' is-active' : '' ?>"
        data-locale="<?= hobc_e($code) ?>"
        lang="<?= hobc_e((string)($meta['hreflang'] ?? $code)) ?>"
        tabindex="-1"
      ><?= hobc_e($display) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
