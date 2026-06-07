<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_datetime.php';

function content_admin_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'untitled-' . time(), 0, 190);
}

function content_admin_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function content_admin_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!content_admin_table_exists($pdo, $table)) {
        return 0;
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE {$where}")->fetchColumn();
}

function content_admin_hobc(float|string|null $value): string
{
    return number_format((float)($value ?? 0), 8, '.', '');
}

function content_admin_short(string $value, int $limit = 120): string
{
    $value = trim($value);
    return strlen($value) > $limit ? substr($value, 0, $limit - 3) . '...' : $value;
}

function content_admin_public_doc_rows(): array
{
    $base = realpath(__DIR__ . '/../docs');
    if ($base === false) {
        return [];
    }
    $rows = [];
    foreach (scandir($base) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $base . '/' . $entry . '/index.php';
        if (!is_file($path)) {
            continue;
        }
        $rows[] = [
            'title' => ucwords(str_replace('-', ' ', $entry)),
            'slug' => $entry,
            'url' => '/docs/' . $entry . '/',
            'updated' => admin_format_timestamp((int)filemtime($path)),
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp($a['title'], $b['title']));
    return $rows;
}

function content_admin_page_views(PDO $pdo, string $url): int
{
    if (!content_admin_table_exists($pdo, 'site_pageviews')) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_pageviews WHERE url LIKE ? OR route_name = ?");
    $stmt->execute(['%' . $url . '%', trim($url, '/')]);
    return (int)$stmt->fetchColumn();
}
