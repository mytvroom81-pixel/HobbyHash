<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/analytics.php';

function throttle_check_and_increment(string $bucket, string $bucketKey, int $maxAttempts, int $windowSeconds): bool
{
    if (!admin_setting_bool('rate_limit.enabled', true)) {
        return true;
    }

    $pdo = wallet_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, attempt_count, window_started_at FROM rate_limits WHERE bucket = ? AND bucket_key = ? FOR UPDATE");
        $stmt->execute([$bucket, $bucketKey]);
        $row = $stmt->fetch();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (!$row) {
            $ins = $pdo->prepare("INSERT INTO rate_limits (bucket, bucket_key, attempt_count, window_started_at) VALUES (?, ?, 1, ?)");
            $ins->execute([$bucket, $bucketKey, $now->format('Y-m-d H:i:s')]);
            $pdo->commit();
            recordRateLimitEvent($bucket, 'attempt', 1);
            return true;
        }

        $windowStart = new DateTimeImmutable((string)$row['window_started_at'], new DateTimeZone('UTC'));
        $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();
        $count = (int)$row['attempt_count'];

        if ($elapsed > $windowSeconds) {
            $upd = $pdo->prepare("UPDATE rate_limits SET attempt_count = 1, window_started_at = ? WHERE id = ?");
            $upd->execute([$now->format('Y-m-d H:i:s'), (int)$row['id']]);
            $pdo->commit();
            recordRateLimitEvent($bucket, 'window_reset', 1);
            return true;
        }

        if ($count >= $maxAttempts) {
            $pdo->commit();
            recordRateLimitEvent($bucket, 'limit_hit', $count);
            return false;
        }

        $upd = $pdo->prepare("UPDATE rate_limits SET attempt_count = attempt_count + 1 WHERE id = ?");
        $upd->execute([(int)$row['id']]);
        $pdo->commit();
        recordRateLimitEvent($bucket, 'attempt', $count + 1);
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        wallet_log_error('Throttle failure: ' . $e->getMessage());
        return false;
    }
}
