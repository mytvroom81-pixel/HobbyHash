<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/social_links.php';

$variant = isset($socialLinksVariant) && is_string($socialLinksVariant) ? $socialLinksVariant : 'footer';
hobc_render_social_links($variant);
