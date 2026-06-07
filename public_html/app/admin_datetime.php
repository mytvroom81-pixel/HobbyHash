<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics.php';

function admin_local_timezone(): DateTimeZone
{
    return analytics_local_timezone();
}

function admin_local_date(string $format = 'Y-m-d', ?int $timestamp = null): string
{
    return analytics_local_date($format, $timestamp);
}

function admin_timezone_label(): string
{
    return analytics_timezone_label();
}

function admin_datetime_note(): string
{
    return 'Times shown in ' . admin_timezone_label() . ' (12-hour local).';
}

function admin_display_datetime_format(): string
{
    return 'M j, Y g:ia';
}

function admin_datetime_is_empty(?string $value): bool
{
    $value = trim((string)$value);

    return $value === '' || str_starts_with($value, '0000');
}

/** MySQL NOW() / CURRENT_TIMESTAMP — stored as admin local wall time (America/Los_Angeles). */
function admin_parse_mysql_datetime(?string $value): ?DateTimeImmutable
{
    if (admin_datetime_is_empty($value)) {
        return null;
    }

    try {
        if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
            return (new DateTimeImmutable($value))->setTimezone(admin_local_timezone());
        }

        return new DateTimeImmutable($value, admin_local_timezone());
    } catch (Throwable) {
        return null;
    }
}

/** SQLite UTC / explicit UTC_TIMESTAMP() columns — stored as UTC, displayed in admin local time. */
function admin_parse_utc_datetime(?string $value): ?DateTimeImmutable
{
    if (admin_datetime_is_empty($value)) {
        return null;
    }

    try {
        if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    } catch (Throwable) {
        return null;
    }
}

function admin_mysql_datetime_timestamp(?string $value): ?int
{
    return admin_parse_mysql_datetime($value)?->getTimestamp();
}

function admin_utc_datetime_timestamp(?string $value): ?int
{
    return admin_parse_utc_datetime($value)?->getTimestamp();
}

function admin_format_datetime(?string $value, ?string $format = null): string
{
    $dt = admin_parse_mysql_datetime($value);
    if (!$dt) {
        return 'not_available';
    }

    return $dt->format($format ?? admin_display_datetime_format());
}

function admin_format_utc_datetime(?string $value, ?string $format = null): string
{
    $dt = admin_parse_utc_datetime($value);
    if (!$dt) {
        return 'not_available';
    }

    return $dt->setTimezone(admin_local_timezone())->format($format ?? admin_display_datetime_format());
}

function admin_h_datetime(?string $value, ?string $format = null): string
{
    return h(admin_format_datetime($value, $format));
}

function admin_h_utc_datetime(?string $value, ?string $format = null): string
{
    return h(admin_format_utc_datetime($value, $format));
}

function admin_format_timestamp(?int $timestamp, ?string $format = null): string
{
    if ($timestamp === null || $timestamp <= 0) {
        return 'not_available';
    }

    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(admin_local_timezone())
        ->format($format ?? admin_display_datetime_format());
}

function admin_h_timestamp(?int $timestamp, ?string $format = null): string
{
    return h(admin_format_timestamp($timestamp, $format));
}

function admin_local_hour_label(int $hour): string
{
    $dateKey = admin_local_date('Y-m-d');
    $dt = new DateTimeImmutable($dateKey . ' ' . sprintf('%02d:00:00', max(0, min(23, $hour))), admin_local_timezone());

    return $dt->format('g:ia');
}

function admin_local_day_bounds(?string $dateKey = null): array
{
    $dateKey = $dateKey ?? admin_local_date('Y-m-d');
    $tz = admin_local_timezone();
    $start = new DateTimeImmutable($dateKey . ' 00:00:00', $tz);
    $end = $start->modify('+1 day');

    return [
        'date' => $dateKey,
        'start_utc' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        'end_utc' => $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ];
}

function admin_local_day_label(?string $dateKey = null): string
{
    $dateKey = $dateKey ?? admin_local_date('Y-m-d');
    $dt = new DateTimeImmutable($dateKey . ' 12:00:00', admin_local_timezone());

    return $dt->format('M j');
}
