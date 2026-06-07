<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This migration runner is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/../../public_html/app/db.php';

function migration_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if (!$inSingle && !$inDouble && $char === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($char === "'" && !$inDouble) {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle) {
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

$pdo = wallet_db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(190) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_schema_migrations_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$migrationDir = __DIR__ . '/migrations';
$files = glob($migrationDir . '/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE migration = ?");
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "Skipping {$name}; already applied.\n";
        continue;
    }

    echo "Applying {$name}...\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration: {$file}");
    }

    try {
        foreach (migration_split_sql($sql) as $statement) {
            $pdo->exec($statement);
        }
        $record = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?) ON DUPLICATE KEY UPDATE migration = VALUES(migration)");
        $record->execute([$name]);
        echo "Applied {$name}.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration {$name} failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Migrations complete.\n";
