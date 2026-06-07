#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Public-site translation generator CLI.
 *
 * Usage:
 *   php jobs/i18n_translate.php translate:missing
 *   php jobs/i18n_translate.php translate:force
 *   php jobs/i18n_translate.php translate:dry-run
 *   php jobs/i18n_translate.php translate:check
 *   php jobs/i18n_translate.php translate:export-keys [--out=/path/to/keys.json]
 *
 * Environment (never commit secrets):
 *   GOOGLE_CLOUD_PROJECT_ID
 *   GOOGLE_APPLICATION_CREDENTIALS  (service-account JSON file path; preferred for v3 translateText)
 *   GOOGLE_CLOUD_API_KEY            (optional REST v2 fallback)
 *   GOOGLE_TRANSLATE_LOCATION       (default: global)
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload missing. Run: composer install\n");
    exit(1);
}
require $autoload;
require __DIR__ . '/../app/i18n_translate_generator.php';

$command = $argv[1] ?? 'help';
$options = parseCliOptions(array_slice($argv, 2));

try {
    $generator = new HobcI18nTranslateGenerator(
        force: in_array($command, ['translate:force', 'force'], true),
        dryRun: in_array($command, ['translate:dry-run', 'dry-run'], true),
    );

    $exit = match ($command) {
        'translate:missing', 'missing' => $generator->runMissing(),
        'translate:force', 'force' => $generator->runForce(),
        'translate:dry-run', 'dry-run' => $generator->runDryRun(),
        'translate:check', 'check' => $generator->runCheck(),
        'translate:export-keys', 'export-keys' => $generator->exportKeys($options['out'] ?? null),
        'help' => printHelp(),
        default => cliUnknownCommand($command),
    };
    exit($exit);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param list<string> $args
 * @return array{out:?string}
 */
function parseCliOptions(array $args): array
{
    $out = null;
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--out=')) {
            $out = substr($arg, 6);
        }
    }
    return ['out' => $out !== '' ? $out : null];
}

function printHelp(): int
{
    $help = <<<TXT
HOBC public translation generator (Google Cloud Translation API)

Commands:
  translate:missing     Translate only missing keys into cached lang/{locale}/*.json files
  translate:force       Re-translate all keys (overwrites existing locale strings)
  translate:dry-run       Report what would be translated; no API calls or writes
  translate:check         Validate credentials and report missing keys per locale
  translate:export-keys   Export English source keys (optional --out=/path/file.json)

Examples:
  php jobs/i18n_translate.php translate:check
  php jobs/i18n_translate.php translate:dry-run
  php jobs/i18n_translate.php translate:missing
  php jobs/i18n_translate.php translate:export-keys --out=/tmp/hobc-i18n-keys.json

TXT;
    fwrite(STDOUT, $help);
    return 0;
}

function cliUnknownCommand(string $command): int
{
    fwrite(STDERR, "Unknown command: {$command}\n\n");
    printHelp();
    return 1;
}
