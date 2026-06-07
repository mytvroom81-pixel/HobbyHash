<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n_glossary.php';

/**
 * Mask placeholders and protected segments before sending text to Google Translate.
 *
 * @return array{0:string,1:array<string,string>}
 */
function hobc_translate_mask_string(string $text): array
{
    if ($text === '') {
        return ['', []];
    }

    $tokens = [];
    $index = 0;
    $mask = static function (string $original) use (&$tokens, &$index): string {
        $key = 'HOBCPLH' . str_pad((string)$index, 4, '0', STR_PAD_LEFT);
        $tokens[$key] = $original;
        $index++;
        return $key;
    };

    $applyPattern = static function (string $subject, string $pattern, int $flags = 0) use ($mask): string {
        $result = preg_replace_callback(
            $pattern,
            static fn(array $m): string => $mask($m[0]),
            $subject,
            -1,
            $count,
            $flags
        );
        return is_string($result) ? $result : $subject;
    };

    $masked = $text;

    $masked = $applyPattern($masked, '#<(pre|code)\b[^>]*>.*?</\1>#is');
    $masked = $applyPattern($masked, '#`[^`]+`#');
    $masked = $applyPattern($masked, '#\{\{[a-zA-Z0-9_]+\}\}#');
    $masked = $applyPattern($masked, '#\{[a-zA-Z0-9_]+\}#');
    $masked = $applyPattern($masked, '#%[sdif]#');
    $masked = $applyPattern($masked, '#:[a-zA-Z_][a-zA-Z0-9_]*#');
    $masked = $applyPattern($masked, '#</?[a-zA-Z][^>]*>#');
    $masked = $applyPattern($masked, '#\bhttps?://[^\s<>"\'\)]+#i');
    $masked = $applyPattern($masked, '#\bstratum\+(?:tcp|ssl)://[^\s<>"\'\)]+#i');
    $masked = $applyPattern($masked, '#\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b#');
    $masked = $applyPattern($masked, '#\b(?:/|\./|\../|~/)?[a-zA-Z0-9_\-./]+(?:\.(?:php|js|css|json|sql|sh|txt|md|pdf|zip|tar|gz|service|conf|log))\b#');
    $masked = $applyPattern($masked, '#\b(?:hobc1|bc1|tb1)[a-z0-9]{20,}\b#i');
    $masked = $applyPattern($masked, '#\b[a-f0-9]{64}\b#i');
    $masked = $applyPattern($masked, '#\b(?::|port\s*)(?:5555|5556|18761|18762|18765|28761|28762)\b#i');
    $masked = $applyPattern($masked, '#\b(?:sudo|curl|wget|systemctl|chmod|chown|docker|npm|composer|php)\b[^\n\r]*#i');

    foreach (hobc_i18n_glossary_terms() as $term) {
        $quoted = preg_quote($term, '#');
        $masked = preg_replace_callback(
            '#(?<![\w\-])' . $quoted . '(?![\w\-])#u',
            static fn(array $m): string => $mask($m[0]),
            $masked
        ) ?? $masked;
    }

    return [$masked, $tokens];
}

function hobc_translate_unmask_string(string $text, array $tokens): string
{
    if ($text === '' || $tokens === []) {
        return $text;
    }

    $restored = $text;
    uksort($tokens, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($tokens as $token => $original) {
        $restored = str_replace($token, $original, $restored);
    }

    return $restored;
}

/** Catalog values that must stay exactly as authored in English (pool URLs, etc.). */
function hobc_i18n_is_preserved_literal(string $value): bool
{
    $value = trim($value);

    return $value !== '' && preg_match('#^stratum\+(?:tcp|ssl)://#i', $value) === 1;
}
