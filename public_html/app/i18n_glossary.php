<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_catalog.php';

function hobc_i18n_glossary_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    $path = hobc_i18n_lang_root() . '/en/glossary.json';
    if (!is_file($path)) {
        $data = [];
        return $data;
    }

    $raw = file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($decoded) ? $decoded : [];
    return $data;
}

function hobc_i18n_glossary_terms(): array
{
    $data = hobc_i18n_glossary_data();
    $terms = [];
    foreach ($data as $group) {
        if (!is_array($group)) {
            continue;
        }
        foreach ($group as $term) {
            $term = trim((string)$term);
            if ($term !== '') {
                $terms[] = $term;
            }
        }
    }

    usort($terms, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    return array_values(array_unique($terms));
}

function hobc_i18n_notranslate_open(): string
{
    return '<span class="notranslate" translate="no" data-notranslate="1">';
}

function hobc_i18n_notranslate_close(): string
{
    return '</span>';
}

function hobc_i18n_notranslate_wrap(string $value): string
{
    if ($value === '') {
        return '';
    }
    return hobc_i18n_notranslate_open() . $value . hobc_i18n_notranslate_close();
}

function hobc_notranslate_e(string $value): string
{
    $safe = function_exists('h')
        ? h($value)
        : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return hobc_i18n_notranslate_open() . $safe . hobc_i18n_notranslate_close();
}

function hobc_i18n_protect_text(string $text): string
{
    if ($text === '') {
        return '';
    }

    $placeholders = [];
    $index = 0;
    $protected = preg_replace_callback(
        '#<(pre|code)\b[^>]*>.*?</\1>#is',
        static function (array $matches) use (&$placeholders, &$index): string {
            $token = '%%HOBC_I18N_CODE_' . $index . '%%';
            $placeholders[$token] = $matches[0];
            $index++;
            return $token;
        },
        $text
    );
    if (!is_string($protected)) {
        $protected = $text;
    }

    $patterns = [
        '#\bhttps?://[^\s<>"\'\)]+#i',
        '#\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b#',
        '#\b(?:/|\./|\../|~/)?[a-zA-Z0-9_\-./]+(?:\.(?:php|js|css|json|sql|sh|txt|md|pdf|zip|tar|gz|service|conf|log))\b#',
        '#\b(?:hobc1|bc1|tb1)[a-z0-9]{20,}\b#i',
        '#\b[a-f0-9]{64}\b#i',
        '#\b[A-Za-z0-9_\-]{24,}\b#',
        '#\b(?::|port\s*)(?:5555|5556|18761|18762|18765|28761|28762)\b#i',
    ];

    foreach ($patterns as $pattern) {
        $protected = preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                return hobc_i18n_notranslate_open() . $matches[0] . hobc_i18n_notranslate_close();
            },
            $protected
        ) ?? $protected;
    }

    foreach (hobc_i18n_glossary_terms() as $term) {
        $quoted = preg_quote($term, '#');
        $protected = preg_replace_callback(
            '#(?<![\w\-])' . $quoted . '(?![\w\-])#u',
            static function (array $matches): string {
                return hobc_i18n_notranslate_open() . $matches[0] . hobc_i18n_notranslate_close();
            },
            $protected
        ) ?? $protected;
    }

    foreach ($placeholders as $token => $original) {
        $protected = str_replace($token, $original, $protected);
    }

    return $protected;
}

function hobc_i18n_protect_html(string $html): string
{
    if ($html === '' || !str_contains($html, '<')) {
        return hobc_i18n_protect_text($html);
    }

    $parts = preg_split('#(<[^>]+>)#', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return hobc_i18n_protect_text($html);
    }

    $skipTags = ['pre', 'code', 'script', 'style', 'kbd', 'samp', 'var'];
    $openTag = '';
    $output = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if ($part[0] === '<') {
            if (preg_match('#^<\s*/?\s*([a-z0-9]+)#i', $part, $match) === 1) {
                $tag = strtolower($match[1]);
                if (str_starts_with($part, '</')) {
                    $openTag = '';
                } elseif (!str_ends_with($part, '/>') && !in_array($tag, ['br', 'img', 'meta', 'link', 'input', 'hr'], true)) {
                    $openTag = in_array($tag, $skipTags, true) ? $tag : '';
                }
            }
            $output .= $part;
            continue;
        }

        $output .= ($openTag !== '' || str_contains($part, hobc_i18n_notranslate_open()))
            ? $part
            : hobc_i18n_protect_text($part);
    }

    return $output;
}
