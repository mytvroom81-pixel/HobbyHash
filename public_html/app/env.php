<?php
declare(strict_types=1);

/**
 * Load key=value pairs from public_html/.env into getenv/$_ENV (without overwriting existing env).
 */
function hobc_load_env_file(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = $path ?? (defined('WALLET_ROOT') ? WALLET_ROOT : dirname(__DIR__)) . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') {
            continue;
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}
