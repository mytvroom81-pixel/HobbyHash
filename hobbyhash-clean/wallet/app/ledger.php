<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ledger_add(
    int $userId,
    string $entryType,
    string $amount,
    string $referenceType,
    int $referenceId,
    string $actorType,
    ?int $actorId = null,
    ?string $note = null
): int {
    $stmt = wallet_db()->prepare(
        "INSERT INTO ledger_entries
        (user_id, entry_type, amount, reference_type, reference_id, note, actor_type, actor_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId,
        $entryType,
        $amount,
        $referenceType,
        $referenceId,
        $note,
        $actorType,
        $actorId,
    ]);
    return (int)wallet_db()->lastInsertId();
}

function ledger_user_balance(int $userId): string
{
    $stmt = wallet_db()->prepare("SELECT COALESCE(SUM(amount), 0) AS bal FROM ledger_entries WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return number_format((float)($row['bal'] ?? 0), 8, '.', '');
}

function ledger_total_liabilities(): string
{
    $stmt = wallet_db()->query("SELECT COALESCE(SUM(amount), 0) AS bal FROM ledger_entries");
    $row = $stmt->fetch();
    return number_format((float)($row['bal'] ?? 0), 8, '.', '');
}
