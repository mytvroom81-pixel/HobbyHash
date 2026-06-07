<?php
declare(strict_types=1);

require_once __DIR__ . '/public_settings.php';

function hobc_social_platform_definitions(): array
{
    return [
        'x' => [
            'setting' => 'listing.social_x',
            'default' => 'https://x.com/HobbyHashCoin',
            'label_key' => 'social.platform_x',
            'aria_key' => 'social.aria_x',
        ],
        'facebook' => [
            'setting' => 'listing.social_facebook',
            'default' => 'https://www.facebook.com/people/HobbyHash-Coin/61590689639798/',
            'label_key' => 'social.platform_facebook',
            'aria_key' => 'social.aria_facebook',
        ],
        'discord' => [
            'setting' => 'listing.social_discord',
            'default' => '',
            'label_key' => 'social.platform_discord',
            'aria_key' => 'social.aria_discord',
        ],
        'telegram' => [
            'setting' => 'listing.social_telegram',
            'default' => '',
            'label_key' => 'social.platform_telegram',
            'aria_key' => 'social.aria_telegram',
        ],
    ];
}

function hobc_social_raw_value(string $settingKey, string $default = ''): string
{
    return trim(hobc_public_setting_text($settingKey, $default));
}

function hobc_social_is_pending(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }

    return stripos($value, 'to be confirmed') !== false;
}

function hobc_social_normalize_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || hobc_social_is_pending($value)) {
        return '';
    }
    if (preg_match('#^https?://#i', $value) === 1) {
        return $value;
    }
    if (str_starts_with($value, '@')) {
        return 'https://x.com/' . ltrim($value, '@');
    }
    if (preg_match('#^(discord\.gg/|discord\.com/)#i', $value) === 1) {
        return 'https://' . ltrim($value, '/');
    }
    if (preg_match('#^(t\.me/|telegram\.me/)#i', $value) === 1) {
        return 'https://' . ltrim($value, '/');
    }
    if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $value) === 1) {
        return 'https://' . ltrim($value, '/');
    }

    return '';
}

function hobc_social_links(): array
{
    $links = [];
    foreach (hobc_social_platform_definitions() as $platform => $def) {
        $raw = hobc_social_raw_value((string)$def['setting'], (string)$def['default']);
        $url = hobc_social_normalize_url($raw);
        if ($url === '') {
            continue;
        }
        $links[] = [
            'platform' => $platform,
            'url' => $url,
            'raw' => $raw,
            'label_key' => (string)$def['label_key'],
            'aria_key' => (string)$def['aria_key'],
        ];
    }

    return $links;
}

function hobc_social_links_same_as(): array
{
    return array_values(array_map(static fn(array $link): string => (string)$link['url'], hobc_social_links()));
}

function hobc_social_link_label(array $link): string
{
    if (!function_exists('hobc_te')) {
        return (string)$link['platform'];
    }

    return hobc_te((string)$link['label_key']);
}

function hobc_social_link_aria(array $link): string
{
    if (!function_exists('hobc_te')) {
        return 'Visit on ' . (string)$link['platform'];
    }

    return hobc_te((string)$link['aria_key']);
}

function hobc_social_link_display_text(string $raw, string $platform): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return hobc_social_link_label(['platform' => $platform, 'label_key' => 'social.platform_' . $platform]);
    }

    return $raw;
}

function hobc_social_link_svg(string $platform): string
{
    return match ($platform) {
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.5 22v-8.5h2.9l.4-3.4h-3.3V8.1c0-1 .3-1.7 1.8-1.7h1.7V3.2c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3V10H7.8v3.4h2.9V22h2.8z"/></svg>',
        'discord' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19.5 5.2A16.9 16.9 0 0 0 15.6 4l-.2.4a15.2 15.2 0 0 0-6.8 0L8.4 4a16.8 16.8 0 0 0-3.9 1.2C2.5 8.7 1.8 12.1 2.1 15.4a17 17 0 0 0 5.2 2.6l1.1-1.7a10.8 10.8 0 0 1-1.7-.8l.4-.3c3.2 1.5 6.7 1.5 9.8 0l.4.3c-.5.3-1.1.6-1.7.8l1.1 1.7a17 17 0 0 0 5.2-2.6c.5-4.8-.8-8.1-2.8-10.2zM8.7 13.6c-.9 0-1.6-.8-1.6-1.8s.7-1.8 1.6-1.8 1.7.8 1.6 1.8-.7 1.8-1.6 1.8zm6.6 0c-.9 0-1.6-.8-1.6-1.8s.7-1.8 1.6-1.8 1.7.8 1.6 1.8-.7 1.8-1.6 1.8z"/></svg>',
        'telegram' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.8 4.2 2.9 11.5c-1.2.5-1.2 1.2-.2 1.5l4.9 1.5 1.9 5.8c.2.7.4.9 1 .9.6 0 .8-.3 1.1-.7l2.6-2.5 5.4 4c1 .6 1.7.3 2-1.1L23.7 5.8c.4-1.5-.6-2.2-1.9-1.6zM8.8 14.3l9.9-6.2c.5-.3.9-.1.5.2L10.6 15l-.4 3.7-1.4-4.4z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/></svg>',
    };
}

function hobc_render_social_links(string $variant = 'footer'): void
{
    $links = hobc_social_links();
    if ($links === []) {
        return;
    }

    $variantClass = 'social-links--' . preg_replace('/[^a-z0-9_-]/', '', $variant);
    ?>
  <?php if ($variant === 'contact'): ?>
<div class="card social-links-card">
  <?php endif; ?>
<div class="social-links-wrap <?= hobc_e($variantClass) ?>">
  <?php if ($variant === 'footer'): ?>
  <p class="social-links-heading"><?= function_exists('hobc_te') ? hobc_te('social.follow') : 'Follow HOBC' ?></p>
  <?php elseif ($variant === 'contact'): ?>
  <h3><?= function_exists('hobc_te') ? hobc_te('social.follow') : 'Follow HOBC' ?></h3>
  <p class="fine-print"><?= function_exists('hobc_te') ? hobc_te('social.contact_blurb') : 'Updates, mining tips, and community chat on our official channels.' ?></p>
  <?php endif; ?>
  <ul class="social-links" role="list">
    <?php foreach ($links as $link): ?>
      <li>
        <a
          class="social-link social-link--<?= hobc_e((string)$link['platform']) ?>"
          href="<?= hobc_e((string)$link['url']) ?>"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="<?= hobc_e(hobc_social_link_aria($link)) ?>"
        >
          <?= hobc_social_link_svg((string)$link['platform']) ?>
          <span class="social-link-label"><?= hobc_e(hobc_social_link_label($link)) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
  <?php if ($variant === 'contact'): ?>
</div>
  <?php endif; ?>
    <?php
}
