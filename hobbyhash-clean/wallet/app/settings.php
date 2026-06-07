<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function wallet_settings(): array
{
    $stmt = wallet_db()->query("SELECT * FROM wallet_settings WHERE id = 1");
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('wallet_settings row missing');
    }
    return $row;
}
