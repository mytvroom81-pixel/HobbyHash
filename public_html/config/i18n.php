<?php
declare(strict_types=1);

/**
 * Supported public-site locales. English is the canonical source locale.
 * Admin pages never load this config for rendering.
 */
return [
    'default_locale' => 'en',
    'cookie_name' => 'hobc_lang',
    'cookie_max_age' => 31536000,
    'local_storage_key' => 'hobc_lang',
    'prefix_urls' => true,
    'catalog_paths' => [
        'ui' => 'ui.json',
        'js' => 'js.json',
    ],
    'languages' => [
        'en' => [
            'label' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
            'hreflang' => 'en',
        ],
        'es' => [
            'label' => 'Spanish',
            'native' => 'Español',
            'dir' => 'ltr',
            'hreflang' => 'es',
        ],
        'fr' => [
            'label' => 'French',
            'native' => 'Français',
            'dir' => 'ltr',
            'hreflang' => 'fr',
        ],
        'de' => [
            'label' => 'German',
            'native' => 'Deutsch',
            'dir' => 'ltr',
            'hreflang' => 'de',
        ],
        'it' => [
            'label' => 'Italian',
            'native' => 'Italiano',
            'dir' => 'ltr',
            'hreflang' => 'it',
        ],
        'pt-BR' => [
            'label' => 'Portuguese',
            'native' => 'Português',
            'selector' => 'Português',
            'dir' => 'ltr',
            'hreflang' => 'pt-BR',
            'url_slug' => 'pt-br',
        ],
        'nl' => [
            'label' => 'Dutch',
            'native' => 'Nederlands',
            'dir' => 'ltr',
            'hreflang' => 'nl',
        ],
        'pl' => [
            'label' => 'Polish',
            'native' => 'Polski',
            'dir' => 'ltr',
            'hreflang' => 'pl',
        ],
        'ru' => [
            'label' => 'Russian',
            'native' => 'Русский',
            'dir' => 'ltr',
            'hreflang' => 'ru',
        ],
        'uk' => [
            'label' => 'Ukrainian',
            'native' => 'Українська',
            'dir' => 'ltr',
            'hreflang' => 'uk',
        ],
        'tr' => [
            'label' => 'Turkish',
            'native' => 'Türkçe',
            'dir' => 'ltr',
            'hreflang' => 'tr',
        ],
        'ar' => [
            'label' => 'Arabic',
            'native' => 'العربية',
            'dir' => 'rtl',
            'hreflang' => 'ar',
        ],
        'he' => [
            'label' => 'Hebrew',
            'native' => 'עברית',
            'dir' => 'rtl',
            'hreflang' => 'he',
        ],
        'hi' => [
            'label' => 'Hindi',
            'native' => 'हिन्दी',
            'dir' => 'ltr',
            'hreflang' => 'hi',
        ],
        'bn' => [
            'label' => 'Bengali',
            'native' => 'বাংলা',
            'dir' => 'ltr',
            'hreflang' => 'bn',
        ],
        'ur' => [
            'label' => 'Urdu',
            'native' => 'اردو',
            'dir' => 'rtl',
            'hreflang' => 'ur',
        ],
        'id' => [
            'label' => 'Indonesian',
            'native' => 'Indonesia',
            'selector' => 'Indonesia',
            'dir' => 'ltr',
            'hreflang' => 'id',
        ],
        'ms' => [
            'label' => 'Malay',
            'native' => 'Melayu',
            'selector' => 'Melayu',
            'dir' => 'ltr',
            'hreflang' => 'ms',
        ],
        'vi' => [
            'label' => 'Vietnamese',
            'native' => 'Tiếng Việt',
            'dir' => 'ltr',
            'hreflang' => 'vi',
        ],
        'th' => [
            'label' => 'Thai',
            'native' => 'ไทย',
            'dir' => 'ltr',
            'hreflang' => 'th',
        ],
        'ja' => [
            'label' => 'Japanese',
            'native' => '日本語',
            'dir' => 'ltr',
            'hreflang' => 'ja',
        ],
        'ko' => [
            'label' => 'Korean',
            'native' => '한국어',
            'dir' => 'ltr',
            'hreflang' => 'ko',
        ],
        'zh-CN' => [
            'label' => 'Chinese (Simplified)',
            'native' => '简体中文',
            'dir' => 'ltr',
            'hreflang' => 'zh-CN',
            'url_slug' => 'zh-cn',
        ],
        'zh-TW' => [
            'label' => 'Chinese (Traditional)',
            'native' => '繁體中文',
            'dir' => 'ltr',
            'hreflang' => 'zh-TW',
            'url_slug' => 'zh-tw',
        ],
        'tl' => [
            'label' => 'Filipino',
            'native' => 'Filipino',
            'dir' => 'ltr',
            'hreflang' => 'tl',
        ],
        'sw' => [
            'label' => 'Swahili',
            'native' => 'Kiswahili',
            'dir' => 'ltr',
            'hreflang' => 'sw',
        ],
    ],
];
