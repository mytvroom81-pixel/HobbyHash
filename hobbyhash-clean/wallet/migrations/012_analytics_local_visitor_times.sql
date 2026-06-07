-- site_visitors used UTC_TIMESTAMP(); site_pageviews use MySQL NOW() (server local).
-- Shift legacy visitor timestamps to server local so live tracking matches pageviews.
UPDATE site_visitors
SET
    first_seen_at = DATE_ADD(first_seen_at, INTERVAL TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) SECOND),
    last_seen_at = DATE_ADD(last_seen_at, INTERVAL TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) SECOND)
WHERE first_seen_at IS NOT NULL;
