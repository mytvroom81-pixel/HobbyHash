# HOBC Admin Panel Rebuild Plan

This document is a planning-only inspection result for the existing Hobby Cash Coin / HobbyHash Coin website project. No functional code changes are included in this step.

## Completed Foundation Rebuild Pass

Completed after the initial inspection:

- Rebuilt the shared admin shell in `public_html/app/admin_view.php` while preserving the existing `render_admin_header()` and `render_admin_footer()` calls used by current admin pages.
- Added the requested admin sidebar navigation sections:
  - Dashboard
  - Site Analytics
  - Visitors
  - Bots & Crawlers
  - Traffic Sources
  - Pages & Content
  - Downloads
  - Docs
  - Wallets
  - Custodial Wallet Controls
  - Users
  - Mining Pool
  - Nodes
  - Blockchain Stats
  - Explorer Stats
  - Reserve / Premine / Treasury
  - Burn Events
  - Announcements
  - Support Messages
  - Security Center
  - Admin Users & Roles
  - Audit Logs
  - System Health
  - Settings
- Added a unified admin top bar with mobile menu toggle, current admin username display, user wallet link, and logout button.
- Added breadcrumbs and a shared admin page title system.
- Added shared admin flash/alert support, stat cards, tables, filters, pagination, empty-state boxes, action buttons, and confirmation modal helpers.
- Added global admin CSS in `public_html/assets/css/hobc-admin.css`, matching the existing HOBC dark/gold theme without removing public CSS.
- Added global admin JavaScript in `public_html/assets/js/hobc-admin.js` for mobile menus, confirmation modals, modal open/close behavior, table filters, tabs, and opt-in live refresh widgets.
- Added `public_html/admin/section.php` as a shared data-backed section controller for the new admin navigation sections that do not already have a dedicated live page.
- Kept existing live admin routes and features:
  - `public_html/admin/login.php`
  - `public_html/admin/verify-sms.php`
  - `public_html/admin/verify-authenticator.php`
  - `public_html/admin/logout.php`
  - `public_html/admin/site-config.php`
  - `public_html/admin/wallet.php`
  - `public_html/admin/withdrawals.php`
  - `public_html/admin/reserve.php`
  - `public_html/admin/tickets.php`
  - `public_html/admin/smtp.php`
  - `public_html/admin/audit.php`
  - `public_html/admin/authenticator.php`
- Converted the dashboard, site config, wallet operations, and withdrawals pages to use shared layout/components where safe.
- Added confirmation modals for sensitive existing actions:
  - Saving public site status/messages.
  - Saving SMS settings.
  - Saving authenticator requirement settings.
  - Saving custodial wallet operation flags.
  - Approving or rejecting withdrawals.
- Replaced old dashboard "planned" module cards with real links to working admin routes or the new section controller.
- Added real read-only section data from current sources where available:
  - Wallet users, deposit addresses, deposits, withdrawals, ledger liabilities.
  - Pool collector JSON files.
  - Node/blockchain RPC.
  - Burn status helper.
  - Public pages/docs/download files.
  - Security events, rate limits, audit log counts.
  - Admin users.
  - System health/scanner/SMTP signals.
- Added "No data yet" empty states for analytics/visitor/bot/source sections until collector/storage tables exist.

Verification completed:

- `php -l` passed for changed PHP files.
- A broader syntax pass passed for all `public_html/admin/*.php` and `public_html/app/*.php`.

Remaining next work:

- Add database migrations for analytics, admin job runs, pool snapshots, health checks, roles/permissions, and content/download management.
- Add collectors before showing visitor/source/bot/pageview statistics.
- Add deeper role enforcement before enabling multi-role admin actions.
- Add encrypted-at-rest handling for stored secrets such as SMTP passwords and TOTP secrets.

## Completed Database Migration Pass

Completed after the admin foundation rebuild:

- Added production MySQL/MariaDB migration file:
  - `hobbyhash-clean/wallet/migrations/001_admin_panel_foundation.sql`
- Added migration instructions:
  - `hobbyhash-clean/wallet/migrations/README.md`
- Added CLI migration runner:
  - `hobbyhash-clean/wallet/run_migrations.php`
- Added admin migration status helper:
  - `public_html/app/admin_migrations.php`
- Updated `public_html/admin/section.php` so System Health warns when required admin database objects are missing.
- Updated analytics sections to check the production tables:
  - `site_pageviews`
  - `site_visitors`
  - `bot_events`

Migration apply command:

```bash
php /home/hobbyhashcoin/hobbyhash-clean/wallet/run_migrations.php
```

Tables or database objects added by the migration:

- `schema_migrations`
- `admin_audit_logs` compatibility view over existing `admin_audit_log`
- `site_pageviews`
- `site_visitors`
- `bot_events`
- `admin_security_events`
- `site_errors`
- `system_health_snapshots`
- `admin_settings`
- `announcements`
- `burn_events`
- `treasury_reserve_categories`
- `treasury_reserve_movements`
- `downloads`
- `download_events`
- `support_messages`
- `rate_limit_events`

Existing equivalent table handling:

- The existing `admin_audit_log` table is preserved and extended with:
  - `entity_type`
  - `entity_id`
  - `ip_hash`
  - `metadata_json`
- Existing audit rows are not erased.
- The `admin_audit_logs` object is created as a compatibility view so new admin code can read the requested shape without duplicating current audit data.

Indexes added where useful:

- `created_at`
- `visitor_id`
- `session_id`
- `is_bot`
- `bot_name`
- URL prefix indexes
- `status`
- `admin_user_id`
- event type and route indexes where useful

Important note:

- The migration file and runner were added, but the production database migration was not executed in this edit pass. Run the CLI command above when ready to apply it to the live MySQL database.

## Completed Analytics Collection Pass

Completed after the database migration pass:

- Added privacy-safe analytics helper file:
  - `public_html/app/analytics.php`
- Hooked public pageview collection into:
  - `public_html/includes/header.php`
- Added download click beacon endpoint:
  - `public_html/api/analytics/download/index.php`
- Added download click beacon JavaScript to:
  - `public_html/assets/js/hobc.js`
- Added admin login/security event recording to:
  - `public_html/admin/login.php`
- Added rate-limit event recording to:
  - `public_html/app/throttle.php`
- Updated admin analytics display logic in:
  - `public_html/admin/section.php`

Helper functions added:

- `getVisitorId()`
- `getSessionId()`
- `hashIpAddress()`
- `detectBot()`
- `detectDevice()`
- `detectBrowser()`
- `detectOS()`
- `recordPageView()`
- `recordBotEvent()`
- `recordDownloadEvent()`
- `recordSecurityEvent()`
- `recordSiteError()`

Privacy and security choices:

- Raw IP addresses are not stored by analytics.
- IP values are hashed with a server-side salt from `HOBC_ANALYTICS_SALT`, config `analytics.ip_hash_salt`, config `security.analytics_salt`, or a private config-derived fallback.
- No third-party tracker was added.
- No invasive fingerprinting was added.
- Visitor/session IDs use first-party random cookies only.
- Country code is captured only from existing proxy/CDN headers such as `CF-IPCountry`; no paid or external GeoIP lookup is used.
- Analytics writes are fail-open. If tables are missing or inserts fail, the public page continues loading.

Collected data:

- Page views.
- Unique visitors.
- Sessions.
- Active visitors right now through recent pageview timestamps.
- Visitors today, last 24 hours, last 7 days, and last 30 days.
- Human visitors versus bots.
- Known search engine bots.
- Suspicious/generic bots.
- Login probes and 404 probes when detectable by PHP.
- Referrers and UTM campaigns.
- Top pages.
- Entry pages and exit pages by session.
- Device type, browser, operating system.
- Country code from available request headers.
- Response status codes.
- Slow pages from shutdown timing.
- Download clicks.
- Admin login attempts and failures.
- Rate limit hits.
- Catchable PHP warnings/errors and fatal shutdown errors.

Bot classes detected:

- Googlebot
- Bingbot
- DuckDuckBot
- YandexBot
- Baiduspider
- Facebook crawler
- Twitter/X crawler
- Discordbot
- TelegramBot
- AhrefsBot
- SemrushBot
- MJ12bot
- DotBot
- Generic curl/wget/python requests
- Unknown suspicious bots

Admin analytics sections now read from production collection tables:

- `site_pageviews`
- `site_visitors`
- `bot_events`
- `download_events`
- `admin_security_events`
- `rate_limit_events`
- `site_errors`

Important note:

- Analytics collection depends on the admin foundation migration being applied. If the migration has not been run, the helper silently skips writes and System Health reports missing tables.

## Completed Site Analytics Admin Section

Completed after analytics collection:

- Added a dedicated protected analytics admin controller:
  - `public_html/admin/analytics.php`
- Updated admin navigation in:
  - `public_html/app/admin_view.php`
- Added analytics chart/filter styling in:
  - `public_html/assets/css/hobc-admin.css`

Analytics tabs added:

- Overview
- Real-time visitors
- Page views
- Unique visitors
- Sessions
- Referrers
- UTM campaigns
- Devices
- Browsers
- Operating systems
- Countries
- Status codes
- Slow pages
- 404 pages
- Downloads
- Search engine crawlers
- Suspicious bots

Each analytics tab includes, where applicable:

- Date range filter.
- Search/filter input.
- Pagination.
- Export CSV button.
- Summary cards.
- Tables.
- Lightweight internal bar charts.
- Empty states for missing/no data.

Detail views added:

- One visitor.
- One session.
- One URL/page.
- One referrer.
- One bot.
- One download.

Visitor/session detail includes:

- First seen.
- Last seen.
- Total page views.
- Device.
- Browser.
- OS.
- Country.
- Bot classification.
- Timeline of pages visited.
- Referrers in timeline.
- Download clicks linked by hashed IP.
- Site errors linked by hashed IP.
- Admin security events linked by hashed IP.

Privacy/security notes:

- No raw IP address is shown.
- Detail linking uses visitor/session IDs, and admin views show real IPs for new analytics rows where available.
- No third-party analytics scripts were added.
- Analytics remains inside protected admin pages.

## Completed Analytics IP And Live Visitor Correction

Completed after admin feedback on hashed IP display and live visitor counts:

- Added `hobbyhash-clean/wallet/migrations/008_admin_raw_ip_analytics.sql`.
- Added admin-only raw IP storage columns to `site_pageviews`, `site_visitors`, `bot_events`, and `download_events`.
- Updated analytics collection so visitor and session cookies are initialized at the start of public requests, preventing refreshes from being counted as new visitors when headers are already sent during shutdown logging.
- Updated Site Analytics and the analytics admin section to use `site_visitors.last_seen_at` for the Active Now metric.
- Updated Site Analytics, Bots & Crawlers, Download Event Logs, and Audit Logs to show real IPs in protected admin screens where available, with hash fallback only for older records.
- Added `/api/analytics/heartbeat/` and public browser heartbeat pings so people who stay on a page continue to count as active visitors.
- Updated the Real-time Visitors admin tab to list active visitor rows from `site_visitors.last_seen_at` instead of only recent pageview rows.
- Added auto-refresh to the Site Analytics overview and Real-time Visitors tabs so protected admin reporting updates without manual reloads.

## Completed Bots & Crawlers Admin Section

Completed after the Site Analytics section:

- Added bot management migration:
  - `hobbyhash-clean/wallet/migrations/002_bot_management.sql`
- Added protected Bots & Crawlers admin page:
  - `public_html/admin/bots.php`
- Updated bot/app-level enforcement helpers in:
  - `public_html/app/analytics.php`
- Updated admin navigation in:
  - `public_html/app/admin_view.php`
- Updated migration status required objects in:
  - `public_html/app/admin_migrations.php`

Bot admin tabs added:

- Overview
- Known good bots
- Search crawlers
- Social preview bots
- SEO crawlers
- Suspicious bots
- Login probes
- 404 scanners
- High-rate visitors
- User-agent search
- Event timeline
- Allowlist / Blocklist

Bot views and reports added:

- Bot traffic overview cards.
- Bot traffic by day chart.
- Bot vs human chart.
- Known good bot tables.
- Search engine crawler tables.
- Social preview bot tables.
- SEO crawler tables.
- Suspicious bot tables.
- Login probe tables.
- 404 scanner tables.
- High-rate hashed IP visitor tables.
- Top bot user agents.
- Top bot URLs hit.
- Bot event timeline.
- Bot detail views.
- User-agent detail views.
- URL detail views.
- Hashed IP detail views.
- CSV export for bot logs and reports.

Controls added:

- Add user-agent pattern to allowlist/blocklist.
- Add bot name to allowlist/blocklist.
- Add hashed IP to allowlist/blocklist.
- Mark bot/user-agent/hashed IP as harmless.
- Mark bot/user-agent/hashed IP as suspicious.
- Save admin notes per bot, user-agent, or hashed IP.
- Clear bot classification cache action.
- Activate/deactivate rules.

Safety behavior:

- Blocking is app-level only.
- PHP does not edit server firewall rules.
- Allowlist rules are evaluated before blocklist rules.
- Known good search/social crawlers are protected from accidental pattern blocks.
- Googlebot, Bingbot, DuckDuckBot, YandexBot, Baiduspider, Facebook crawler, Twitter/X crawler, Discordbot, and TelegramBot are protected from generic block patterns.
- Blocklist changes require confirmation modals.
- Bot admin actions are logged through the existing `admin_audit_log`/`admin_audit_logs` path.
- Raw IP addresses are not shown or stored by this feature; hashed IP is used.

## Inspection Scope

Primary project areas inspected:

- `public_html/` - live PHP website, wallet, admin panel, public API, includes, assets, jobs.
- `hobbyhash-clean/wallet/` - wallet database schema and installer SQL.
- `hobbyhash-clean/explorer/` - explorer database schema.
- `hobbyhash-clean/scripts/` - pool stats collector scripts.
- `hobbyhash-data/mainnet/` - generated JSON stats/cache files.
- `hobbyhash-logs/` - ckpool status, user, worker, and share log data sources.

Large generated/build areas exist under `hobbyhash-clean/src/build-*` and Electron `node_modules`; those should not be part of the admin panel rebuild except where download/version data is needed.

## Current Admin Login System

The current admin login system must be preserved.

Current flow:

1. Admin visits `public_html/admin/login.php`.
2. `wallet_start_session()` starts the configured PHP session from `public_html/app/bootstrap.php`.
3. Login POST validates CSRF using `public_html/app/csrf.php`.
4. Credentials are checked by `admin_verify_login_password()` in `public_html/app/auth.php`.
5. Admin credentials are loaded from the `admin_users` table by email or username.
6. Passwords are checked with `password_verify()`.
7. Admin login attempts are throttled through `throttle_check_and_increment('admin_login', ...)`.
8. If configured, SMS verification is required through `public_html/admin/verify-sms.php` and `sms_challenges`.
9. If configured, authenticator app/TOTP verification is required through `public_html/admin/verify-authenticator.php`.
10. Successful login calls `admin_complete_login()`, regenerates the session ID, stores `$_SESSION['admin_user_id']`, and writes an `admin_audit_log` event.
11. Admin logout is handled by `public_html/admin/logout.php` and clears admin session state.

Important files:

- `public_html/admin/login.php`
- `public_html/admin/verify-sms.php`
- `public_html/admin/verify-authenticator.php`
- `public_html/admin/logout.php`
- `public_html/app/auth.php`
- `public_html/app/bootstrap.php`
- `public_html/app/csrf.php`
- `public_html/app/sms.php`
- `public_html/app/totp.php`
- `public_html/app/throttle.php`
- `public_html/app/security_log.php`

Preserve these behaviors:

- Existing admin username/email plus password login.
- Existing CSRF tokens.
- Existing session regeneration on login.
- Existing admin throttling.
- Existing optional SMS admin 2FA.
- Existing optional TOTP admin 2FA.
- Existing admin audit logging.
- Existing redirects and admin URL structure.

## Current Admin Routes and Pages

Current admin pages:

- `public_html/admin/index.php` - control center dashboard with site mode, wallet liabilities, hot wallet balance, users, pending withdrawals, scanner status, operations links, module cards, recent admin events.
- `public_html/admin/site-config.php` - site mode, pre-launch/maintenance content, bypass IP, SMS settings, Twilio Verify SID, admin/user SMS requirement toggles, TOTP requirement toggles.
- `public_html/admin/authenticator.php` - admin TOTP setup, QR generation, confirm, disable.
- `public_html/admin/wallet.php` - wallet operations status, liabilities, hot wallet balances, wallet maintenance/deposit/withdrawal/scanner flags, scanner state.
- `public_html/admin/withdrawals.php` - pending withdrawal approval/rejection queue and recent withdrawals.
- `public_html/admin/reserve.php` - launch reserve status and public description/category editor for outgoing reserve spends.
- `public_html/admin/tickets.php` - support ticket inbox, selected ticket detail, admin replies, status changes, email notifications.
- `public_html/admin/smtp.php` - SMTP settings for support email.
- `public_html/admin/audit.php` - admin audit log.
- `public_html/admin/verify-sms.php` - admin SMS login verification.
- `public_html/admin/verify-authenticator.php` - admin TOTP login verification.
- `public_html/admin/logout.php` - admin logout.

Admin layout helper:

- `public_html/app/admin_view.php`

Current sidebar links rendered by `render_admin_header()`:

- Control Center
- Site Config
- Authenticator
- Wallet Ops
- Withdrawals
- Launch Reserve
- Support Tickets
- SMTP Settings
- Audit Log
- User Wallet
- Logout

## Existing Admin Features To Preserve

These features must remain functional during any rebuild:

- Admin dashboard metrics from real wallet/database/RPC sources.
- Site mode controls: `pre_launch`, `maintenance`, `full_launch`.
- Bypass IP preview behavior for public pages.
- Pre-launch and maintenance title/message/ETA/window fields.
- SMS provider mode controls: manual Twilio messaging and Twilio Verify.
- Admin SMS 2FA requirement toggle.
- Wallet registration/login/withdrawal SMS requirement toggles.
- Admin TOTP requirement toggle.
- Wallet login/withdrawal TOTP requirement toggles.
- Admin authenticator QR setup and disable flow.
- Wallet maintenance, deposits paused, withdrawals paused, scanner paused flags.
- Hot wallet balance and liabilities display.
- Scanner state display.
- Withdrawal approve/reject workflow.
- Ledger refund credit on rejected withdrawals.
- Launch reserve outgoing spend explanation/category editing.
- Support ticket inbox and replies.
- Support ticket status changes.
- Support email sending via SMTP settings.
- SMTP settings management.
- Admin audit log.
- Security event logging.
- Public site gate behavior.
- Existing public pages and APIs.

## Current Database Connection Method

The live PHP code uses MySQL/MariaDB through PDO.

Primary connection:

- `public_html/app/db.php`
- Function: `wallet_db(): PDO`
- Config source: `wallet_config()['db']`
- DSN shape: `mysql:host=%s;port=%d;dbname=%s;charset=%s`
- PDO options: exceptions enabled, associative fetch mode, emulated prepares disabled.

Config loading:

- `public_html/app/bootstrap.php`
- Function: `wallet_config(): array`
- Checks `HOBC_WALLET_CONFIG`.
- Falls back to `public_html/config.php`.
- Falls back to `/home/hobbyhashcoin/hobbyhash-clean/wallet/config.php`.

Public API helper connection:

- `public_html/api/_bootstrap.php`
- Function: `hobc_db(): ?PDO`
- Uses same config source pattern through `hobc_config()`.

Important note:

- `hobbyhash-clean/wallet/schema.sql` is a PostgreSQL-oriented schema file.
- `hobbyhash-clean/wallet/install.sql` is the MySQL/MariaDB schema that matches the live PHP queries and should be treated as the current production schema baseline unless verified otherwise against the live database.

## Existing Tables Found

### Wallet/Admin MySQL Schema

From `hobbyhash-clean/wallet/install.sql` and live PHP queries:

- `users`
- `pending_registrations`
- `account_recovery_requests`
- `user_security`
- `sessions`
- `deposit_addresses`
- `deposits`
- `withdrawals`
- `ledger_entries`
- `admin_users`
- `admin_audit_log`
- `sms_challenges`
- `faq_items`
- `support_tickets`
- `support_ticket_messages`
- `smtp_settings`
- `wallet_settings`
- `site_settings`
- `wallet_hot_balance_snapshots`
- `chain_scan_state`
- `security_event_log`
- `rate_limits`
- `reconciliation_reports`

Important existing wallet/admin columns and concepts:

- `admin_users`: username, email, phone number, SMS flag, TOTP secret/enabled flag, password hash, active flag.
- `wallet_settings`: wallet operational flags, SMS/TOTP requirement flags, withdrawal limits/thresholds, Twilio Verify SID.
- `site_settings`: public site mode, bypass IP, pre-launch fields, maintenance fields.
- `withdrawals`: approval/rejection fields, status, txid, fee, confirmations.
- `ledger_entries`: immutable wallet accounting entries.
- `support_tickets` and `support_ticket_messages`: public/wallet support workflow.
- `smtp_settings`: support email delivery settings.
- `chain_scan_state`: deposit scanner and RPC status.
- `wallet_hot_balance_snapshots` and `reconciliation_reports`: wallet monitoring/collector outputs.
- `rate_limits`: login and other throttling buckets.

### Explorer Schema

From `hobbyhash-clean/explorer/schema.sql`:

- `explorer_sync_state`
- `blocks`
- `transactions`
- `addresses`
- `tx_outputs`
- `tx_inputs`
- `known_labels`

Explorer-related concepts:

- Chain sync state.
- Indexed blocks and transactions.
- Address labels for launch reserve and burn.
- UTXO/output tracking.
- Burn/reserve output flags.

### Pool Data Structures

Pool stats are currently file/log based, not an admin MySQL schema.

Current sources:

- `hobbyhash-logs/ckpool-main/pool/pool.status`
- `hobbyhash-logs/ckpool-main/users/*`
- `hobbyhash-logs/ckpool-main/workers/*`
- `hobbyhash-logs/ckpool-main/**/*.sharelog`
- `hobbyhash-logs/ckpool-nano/pool/pool.status`
- `hobbyhash-logs/ckpool-nano/users/*`
- `hobbyhash-logs/ckpool-nano/workers/*`
- `hobbyhash-logs/ckpool-nano/**/*.sharelog`
- `hobbyhash-data/mainnet/pool-stats-main.json`
- `hobbyhash-data/mainnet/pool-stats-nano.json`
- `hobbyhash-data/mainnet/payoutd-main-state.json`
- `hobbyhash-data/mainnet/payoutd-nano-state.json`

Collector:

- `hobbyhash-clean/scripts/pool_stats_collector.py`
- `hobbyhash-clean/scripts/pool_stats_collector_loop.sh`

The collector reads ckpool sharelogs, pool status, and payout daemon state. It does not invent missing data.

## Current CSS and Theme Files

Website CSS:

- `public_html/assets/css/hobc.css` - main public website theme, layout, cards, nav, pool/download/docs styling.
- `public_html/assets/css/hobc-wallet.css` - wallet-specific public/user wallet styling.
- `public_html/assets/css/hobc-stats-overload.css` - advanced pool stats dashboard styling.

Admin CSS:

- Admin CSS is currently embedded inline inside `public_html/app/admin_view.php`.
- Login/verification pages also include inline CSS in:
  - `public_html/admin/login.php`
  - `public_html/admin/verify-sms.php`
  - `public_html/admin/verify-authenticator.php`

Desktop app CSS:

- `hobbyhash-clean/apps/hobbyhash-wallet-desktop/src/styles.css`
- `hobbyhash-clean/apps/hobbyhash-watch-wallet/src/styles.css`
- `hobbyhash-clean/apps/hobbyhash-cold-wallet/src/styles.css`

Future admin cleanup should move shared admin CSS into a dedicated file such as `public_html/assets/css/hobc-admin.css` while preserving the current theme colors and layout behavior.

## Current JavaScript Files

Website JavaScript:

- `public_html/assets/js/hobc.js` - public API widget refresh, pool widgets, burn/latest block/latest transaction rendering, mobile menu, copy buttons, email check, scroll memory.
- `public_html/assets/js/hobc-stats-overload.js` - advanced client-side pool stats dashboard rendering, miner/session/share tables, odds, charts, local history.

Inline admin JavaScript:

- `public_html/admin/authenticator.php` - modal open/close behavior for TOTP setup/disable.

Desktop app JavaScript:

- `hobbyhash-clean/apps/hobbyhash-wallet-desktop/src/main.js`
- `hobbyhash-clean/apps/hobbyhash-wallet-desktop/src/preload.js`
- `hobbyhash-clean/apps/hobbyhash-wallet-desktop/src/renderer.js`
- `hobbyhash-clean/apps/hobbyhash-watch-wallet/src/main.js`
- `hobbyhash-clean/apps/hobbyhash-watch-wallet/src/preload.js`
- `hobbyhash-clean/apps/hobbyhash-watch-wallet/src/renderer.js`
- `hobbyhash-clean/apps/hobbyhash-cold-wallet/src/main.js`
- `hobbyhash-clean/apps/hobbyhash-cold-wallet/src/preload.js`
- `hobbyhash-clean/apps/hobbyhash-cold-wallet/src/renderer.js`

Future admin cleanup should add a dedicated admin script only if needed, such as `public_html/assets/js/hobc-admin.js`, and should keep current public `hobc.js` behavior untouched.

## Current PHP and Backend Controller Files

### Admin PHP

- `public_html/admin/audit.php`
- `public_html/admin/authenticator.php`
- `public_html/admin/index.php`
- `public_html/admin/login.php`
- `public_html/admin/logout.php`
- `public_html/admin/reserve.php`
- `public_html/admin/site-config.php`
- `public_html/admin/smtp.php`
- `public_html/admin/tickets.php`
- `public_html/admin/verify-authenticator.php`
- `public_html/admin/verify-sms.php`
- `public_html/admin/wallet.php`
- `public_html/admin/withdrawals.php`

### App Helpers

- `public_html/app/account_security.php`
- `public_html/app/admin_view.php`
- `public_html/app/auth.php`
- `public_html/app/bootstrap.php`
- `public_html/app/csrf.php`
- `public_html/app/db.php`
- `public_html/app/ledger.php`
- `public_html/app/mailer.php`
- `public_html/app/rpc.php`
- `public_html/app/security_log.php`
- `public_html/app/settings.php`
- `public_html/app/site_status.php`
- `public_html/app/sms.php`
- `public_html/app/support_context.php`
- `public_html/app/throttle.php`
- `public_html/app/totp.php`
- `public_html/app/view.php`

### Job PHP

- `public_html/jobs/job_common.php`
- `public_html/jobs/deposit_scanner.php`
- `public_html/jobs/confirmation_updater.php`
- `public_html/jobs/hot_wallet_balance_checker.php`
- `public_html/jobs/liability_reconciler.php`
- `public_html/jobs/withdrawal_broadcaster.php`

### Public API PHP

- `public_html/api/_bootstrap.php`
- `public_html/api/_stats_model.php`
- `public_html/api/burn/status/index.php`
- `public_html/api/chain/latest-blocks/index.php`
- `public_html/api/chain/latest-transactions/index.php`
- `public_html/api/chain/status/index.php`
- `public_html/api/chain/supply/index.php`
- `public_html/api/explorer/status/index.php`
- `public_html/api/pool/main/overload/index.php`
- `public_html/api/pool/main/status/index.php`
- `public_html/api/pool/nano/overload/index.php`
- `public_html/api/pool/nano/status/index.php`
- `public_html/api/reserve/status/index.php`
- `public_html/api/stats/summary/index.php`
- `public_html/api/wallet/status/index.php`

### Public Pages and Includes

Important public pages/routes include:

- `public_html/index.php`
- `public_html/about/index.php`
- `public_html/mining/index.php`
- `public_html/pool/main/index.php`
- `public_html/pool/nano/index.php`
- `public_html/explorer/index.php`
- `public_html/wallet/index.php`
- `public_html/wallet/login.php`
- `public_html/wallet/register.php`
- `public_html/wallet/dashboard.php`
- `public_html/wallet/deposit.php`
- `public_html/wallet/withdraw.php`
- `public_html/wallet/transactions.php`
- `public_html/wallet/security.php`
- `public_html/wallet/support.php`
- `public_html/downloads/index.php`
- `public_html/docs/index.php`
- `public_html/docs/getting-started/index.php`
- `public_html/docs/mining-guide/index.php`
- `public_html/docs/pool-stats/index.php`
- `public_html/docs/wallet-guide/index.php`
- `public_html/docs/explorer-guide/index.php`
- `public_html/docs/security-guide/index.php`
- `public_html/docs/linux-node/index.php`
- `public_html/docs/ports-configuration/index.php`
- `public_html/docs/cli-rpc/index.php`
- `public_html/docs/faq/index.php`
- `public_html/launch-reserve/index.php`
- `public_html/burn/index.php`
- `public_html/stats/index.php`
- `public_html/contact/index.php`
- `public_html/contact.php`
- `public_html/ticket.php`
- `public_html/privacy/index.php`
- `public_html/terms/index.php`
- `public_html/roadmap/index.php`
- `public_html/faq/index.php`
- `public_html/faq.php`

Includes:

- `public_html/includes/header.php`
- `public_html/includes/nav.php`
- `public_html/includes/status-bar.php`
- `public_html/includes/footer.php`
- `public_html/includes/icon-meta.php`

## New Admin Sections To Add

The rebuild should expand the admin panel into production modules while preserving the current pages and routes.

Recommended new sections:

- Overview dashboard: consolidated health cards, recent warnings, quick links, no fake stats.
- Users: user search, user detail, balances, deposit addresses, sessions, security flags, support history.
- Wallet accounting: ledger viewer, deposit queue, withdrawal queue, hot wallet snapshots, reconciliation history.
- Pool control/status: main pool and nano pool real status from collectors/logs, active miners, shares, blocks, payout daemon status.
- Node status: RPC health, block height, headers, peers, difficulty, mempool, warnings. Read-only operational status only.
- Explorer status: sync state, latest indexed block, queue/backlog, labels for reserve/burn.
- Public content/status: current site mode, public status messages, docs/download visibility, FAQ items.
- Downloads/admin releases: wallet release metadata, checksums, visible version notes.
- Docs/FAQ management: edit or publish structured docs/FAQ only after table-backed content exists.
- Support center: improved inbox filters, assigned status, internal notes, email delivery status.
- Security center: admin audit, user security events, rate limits, 2FA status, suspicious activity views.
- Analytics/statistics: real page/API events after collector/storage tables are added; show "No data yet" until populated.
- System jobs: scanner, confirmation updater, hot wallet checker, liability reconciler, withdrawal broadcaster, pool stats collector status.

Do not add admin controls for coin/network consensus settings.

## Database Migrations Needed

Add migrations rather than editing production tables ad hoc. Proposed migration location:

- `hobbyhash-clean/wallet/migrations/`

Suggested migration files:

- `001_admin_panel_sections.sql`
- `002_admin_job_runs.sql`
- `003_admin_analytics_events.sql`
- `004_pool_stats_snapshots.sql`
- `005_admin_notifications.sql`
- `006_content_management.sql`

Proposed tables:

- `admin_roles` - role definitions if multiple admin roles are needed later.
- `admin_user_roles` - admin-to-role mapping.
- `admin_permissions` - named permissions for production-safe module access.
- `admin_permission_grants` - role permission mapping.
- `admin_saved_filters` - saved admin list filters/searches.
- `admin_notifications` - warnings/errors shown in admin.
- `admin_job_runs` - each cron/job run with job name, status, start/end, duration, error text, metadata JSON.
- `admin_health_checks` - latest measured health for wallet, node, explorer, pool, SMTP, SMS, API.
- `analytics_events` - privacy-conscious page/API event storage.
- `analytics_daily_rollups` - daily aggregated counts for public pages, API endpoints, downloads, errors.
- `pool_stats_snapshots` - time-series snapshots for main/nano pool stats from real collectors.
- `pool_miner_snapshots` - optional sanitized miner/session summary history.
- `download_releases` - release metadata for wallet downloads and checksums.
- `docs_pages` - optional table-backed docs metadata/content if admin-editable docs are desired.
- `faq_items` already exists; extend only if required.
- `support_ticket_internal_notes` - private admin notes, not emailed to users.
- `admin_action_confirmations` - optional record of high-risk action confirmations.

Columns to consider adding:

- `support_tickets.assigned_admin_id`
- `support_tickets.priority`
- `support_tickets.last_admin_reply_at`
- `support_tickets.last_user_reply_at`
- `smtp_settings.last_tested_at`
- `smtp_settings.last_test_status`
- `wallet_hot_balance_snapshots.source`
- `chain_scan_state.last_job_run_id`

Migration rules:

- Preserve all existing tables and columns.
- Add nullable columns or separate tables where possible.
- Backfill safely.
- Keep current admin/login queries working.
- Do not change consensus, supply, subsidy, burn address, reserve address, or network constants from admin migrations.

## Security Improvements Needed

Keep current security features and improve around them:

- Add a dedicated `public_html/assets/css/hobc-admin.css` and remove only duplicated inline admin CSS after parity is confirmed.
- Add stronger admin security headers, including a careful Content Security Policy that still allows current local assets and QR SVG output.
- Add admin session idle timeout and absolute timeout.
- Record admin last login time/IP in `admin_users` or a new `admin_login_events` table.
- Add admin password change flow with current password confirmation.
- Encrypt TOTP secrets at rest instead of storing plain Base32 text.
- Replace `smtp_settings.password_enc` base64 storage with real encryption using a server-side key from private config.
- Add re-authentication or TOTP confirmation for high-risk admin actions: withdrawal approval, SMTP password changes, disabling 2FA, site full-lock modes.
- Add explicit confirmation forms for withdrawal approval/rejection and wallet operational pauses.
- Add least-privilege roles before allowing multiple admins.
- Add audit detail consistency for every admin POST.
- Add immutable audit logs or append-only safeguards where possible.
- Add rate-limit views and administrative unlock controls with audit logging.
- Ensure error messages do not leak secrets, paths, RPC credentials, or provider credentials.
- Keep all admin pages behind `admin_require_user()`.

## Analytics and Statistics Collectors Needed

No fake statistics should be shown. For any section where data is not collected yet, the UI should show "No data yet."

Collectors/storage to add before showing metrics:

- `admin_job_runs` writer in all `public_html/jobs/*.php`.
- Pool snapshot collector that reads `hobbyhash-data/mainnet/pool-stats-main.json` and `pool-stats-nano.json` into `pool_stats_snapshots`.
- Node health collector using safe read-only RPC calls: `getblockchaininfo`, `getnetworkinfo`, `getmempoolinfo`, `getmininginfo`.
- Explorer health collector from `hobbyhash-clean/explorer/schema.sql` tables and/or explorer status API.
- Wallet health collector extending existing `wallet_hot_balance_snapshots` and `reconciliation_reports`.
- API/page analytics collector writing privacy-conscious aggregate events.
- Download event collector for release download counts.
- SMTP test collector storing last delivery status.
- SMS provider health collector storing last test/check status without storing verification codes.

Existing real stats sources to use:

- Wallet DB: users, deposits, withdrawals, ledger entries, wallet settings, scan state.
- RPC: hot wallet balances, chain status, peer/sync/mempool status.
- Explorer DB/API: blocks, transactions, addresses, sync state.
- Pool JSON/logs: main/nano pool stats, active sessions, shares, blocks.
- Existing jobs: deposit scanner, confirmation updater, hot wallet checker, liability reconciler, withdrawal broadcaster.

## Admin UI Cleanup Plan

Phase 1: Structure without behavior changes.

- Keep every current admin route.
- Extract shared admin CSS into `public_html/assets/css/hobc-admin.css`.
- Update `render_admin_header()` to link the CSS file.
- Preserve existing sidebar links and add new links only for built sections.
- Add shared cards/tables/form helper functions only if they reduce duplication without changing behavior.

Phase 2: Safer module layout.

- Add `public_html/admin/users.php`.
- Add `public_html/admin/security.php`.
- Add `public_html/admin/jobs.php`.
- Add `public_html/admin/pool.php`.
- Add `public_html/admin/node.php`.
- Add `public_html/admin/explorer.php`.
- Add `public_html/admin/downloads.php`.
- Add `public_html/admin/docs.php` only if backed by real editable docs/FAQ data.

Phase 3: Data-backed dashboards.

- Build dashboard widgets from collectors/tables.
- Show "No data yet" for empty collector tables.
- Do not estimate or invent values that are not actually measured.

Phase 4: Hardening.

- Add high-risk action confirmation.
- Add re-authentication for sensitive operations.
- Encrypt stored secrets.
- Add role/permission enforcement if more than one admin type is used.

## Exact Files Planned To Edit Or Create Later

Do not edit these until implementation is approved.

Existing files likely to edit:

- `public_html/app/admin_view.php`
- `public_html/app/auth.php`
- `public_html/app/security_log.php`
- `public_html/app/bootstrap.php`
- `public_html/app/csrf.php`
- `public_html/app/settings.php`
- `public_html/app/site_status.php`
- `public_html/app/sms.php`
- `public_html/app/totp.php`
- `public_html/app/mailer.php`
- `public_html/admin/index.php`
- `public_html/admin/site-config.php`
- `public_html/admin/wallet.php`
- `public_html/admin/withdrawals.php`
- `public_html/admin/tickets.php`
- `public_html/admin/smtp.php`
- `public_html/admin/audit.php`
- `public_html/admin/authenticator.php`
- `public_html/jobs/job_common.php`
- `public_html/jobs/deposit_scanner.php`
- `public_html/jobs/confirmation_updater.php`
- `public_html/jobs/hot_wallet_balance_checker.php`
- `public_html/jobs/liability_reconciler.php`
- `public_html/jobs/withdrawal_broadcaster.php`
- `hobbyhash-clean/scripts/pool_stats_collector.py`
- `hobbyhash-clean/wallet/install.sql`

New files likely to create:

- `public_html/assets/css/hobc-admin.css`
- `public_html/assets/js/hobc-admin.js`
- `public_html/app/admin_permissions.php`
- `public_html/app/admin_metrics.php`
- `public_html/app/admin_jobs.php`
- `public_html/app/admin_collectors.php`
- `public_html/app/admin_health.php`
- `public_html/app/admin_crypto.php`
- `public_html/admin/users.php`
- `public_html/admin/user-detail.php`
- `public_html/admin/security.php`
- `public_html/admin/jobs.php`
- `public_html/admin/pool.php`
- `public_html/admin/node.php`
- `public_html/admin/explorer.php`
- `public_html/admin/downloads.php`
- `public_html/admin/docs.php`
- `public_html/admin/analytics.php`
- `public_html/admin/admins.php`
- `hobbyhash-clean/wallet/migrations/001_admin_panel_sections.sql`
- `hobbyhash-clean/wallet/migrations/002_admin_job_runs.sql`
- `hobbyhash-clean/wallet/migrations/003_admin_analytics_events.sql`
- `hobbyhash-clean/wallet/migrations/004_pool_stats_snapshots.sql`
- `hobbyhash-clean/wallet/migrations/005_admin_notifications.sql`
- `hobbyhash-clean/wallet/migrations/006_content_management.sql`
- `hobbyhash-clean/scripts/admin_health_collector.php` or `public_html/jobs/admin_health_collector.php`
- `hobbyhash-clean/scripts/pool_stats_snapshot_importer.php` or `public_html/jobs/pool_stats_snapshot_importer.php`

Files/routes to avoid renaming:

- `public_html/admin/login.php`
- `public_html/admin/index.php`
- `public_html/app/db.php`
- `public_html/app/bootstrap.php`
- `public_html/app/auth.php`
- `public_html/includes/header.php`
- `public_html/includes/nav.php`
- Public routes under `public_html/`

## Implementation Guardrails

- Do not remove the current login system.
- Do not remove current admin features.
- Do not remove public page features.
- Do not rename core files unless absolutely required.
- Do not change coin/network consensus settings from the admin panel.
- Do not create fake statistics.
- If a stat is not measured yet, add collector/storage first and show "No data yet" until real rows exist.
- Keep all wallet accounting based on immutable ledger entries.
- Keep withdrawal approval/rejection auditable.
- Keep admin action POSTs CSRF-protected.
- Keep public API behavior stable.
- Keep public CSS/JS behavior stable.

## Suggested Build Order

1. Add migration framework/files and admin job run logging.
2. Extract admin CSS while preserving current rendering.
3. Add admin health/job pages with real collector status.
4. Add users/security pages from existing user/security tables.
5. Add pool/node/explorer read-only status pages from existing APIs/logs/RPC.
6. Expand wallet/admin dashboards with collector-backed charts.
7. Add content/download/docs admin only after storage tables are agreed.
8. Add roles/permissions and sensitive action confirmations.

## Security Center Build Completed

Completed Security Center foundation:

- Added `public_html/admin/security.php` as a dedicated protected Security Center page.
- Added admin navigation directly to the Security Center route.
- Added `hobbyhash-clean/wallet/migrations/003_security_center.sql`.
- Added `admin_sessions` for tracked active admin sessions, revocation, last-seen timestamps, expiry, and force logout support.
- Added `security_watchlist` for IP hash and user-agent watchlist entries.
- Added default `admin_settings` keys for failed login threshold, lockout duration, global registration, wallet signups, and a security notice/banner.
- Updated admin auth to keep the existing login flow while recording admin sessions and honoring revoked/expired sessions.
- Updated admin login throttling to use Security Center settings with safe bounds.
- Added force logout for a single admin session, force logout all admins except current, and expired session clearing.
- Added admin account lock/unlock controls without removing admin users.
- Added current-admin password change and admin password reset flow that revokes the reset admin's sessions.
- Added maintenance mode, global registration, public wallet signup, failed login threshold, lockout duration, and security notice controls.
- Added wallet registration enforcement for the registration and public wallet signup toggles, defaulting to enabled so existing behavior is preserved.
- Added optional TOTP visibility/status and link-through to the existing admin authenticator setup.
- Added admin login attempts, failed logins, successful logins, active sessions, suspicious request logs, 404/probe logs, recent site errors, and security audit timeline.
- Added CSRF, session cookie, error display, PHP version, MySQL version, public config exposure, installer/migration exposure, admin route guard, and detectable file permission checks.
- Added CSV export for security logs without exporting database credentials or secret config values.
- All Security Center POST controls use CSRF tokens and write admin audit log entries.

## Custodial Wallet Controls Build Completed

Completed Wallet / Custodial Wallet Controls foundation:

- Rebuilt `public_html/admin/wallet.php` into a full protected custodial wallet operations center.
- Added tabbed admin sections for wallet overview, user wallet balances, deposit addresses, deposit history, withdrawal requests, pending withdrawals, approved withdrawals, rejected withdrawals, manual review queue, hot wallet status, cold wallet/reserve notes, node RPC status, wallet RPC status, broadcast status, failed wallet operations, wallet audit logs, balance reconciliation, suspicious wallet activity, withdrawal limits, and custodial wallet settings.
- Added `hobbyhash-clean/wallet/migrations/004_wallet_admin_controls.sql`.
- Added `wallet_user_holds` for putting user wallets on hold and removing holds without deleting users or balances.
- Added `wallet_admin_notes` for user, withdrawal, reserve, and operation notes. Notes are explicitly not for secrets.
- Extended withdrawal status with `manual_review`.
- Added admin controls to pause/resume withdrawals, pause/resume deposit display/address creation, set minimum withdrawal, set maximum per-withdrawal amount, set maximum daily hot-wallet broadcast limit, set approval threshold, set confirmation requirements, put a user wallet on hold, release a user wallet hold, approve/reject/mark withdrawal for manual review, add admin notes, export wallet ledger CSV, run balance reconciliation, and refresh node/wallet status.
- Updated `public_html/wallet/withdraw.php` so active wallet holds block new withdrawal requests.
- Approval from admin only changes the withdrawal to `approved`; real transaction broadcast remains limited to the existing background `withdrawal_broadcaster.php` flow.
- Rejection records an admin audit event and writes a refund ledger entry only when a refund has not already been recorded.
- Balance reconciliation reads real wallet RPC balances and immutable ledger totals, then records hot balance snapshots and reconciliation reports.
- Wallet admin pages avoid private keys, seed phrases, and RPC credentials.
- All wallet admin POST actions use CSRF tokens and write `admin_audit_log` entries.

## Mining Pool, Node, Blockchain, And Explorer Admin Sections Completed

Completed operational stats/admin sections:

- Added `public_html/app/admin_ops.php` for shared admin operational helpers, safe RPC reads, pool status reads, cache clearing, formatting, and manual command display.
- Added `public_html/admin/mining-pool.php` as a dedicated Mining Pool admin page.
- Added `public_html/admin/node.php` as a dedicated Node admin page.
- Added `public_html/admin/blockchain.php` as a dedicated Blockchain Stats admin page.
- Added `public_html/admin/explorer.php` as a dedicated Explorer Stats admin page.
- Updated admin navigation in `public_html/app/admin_view.php` so Mining Pool, Nodes, Blockchain Stats, and Explorer Stats point to the dedicated pages.
- Updated `public_html/api/_bootstrap.php` so public pool status APIs honor the admin-controlled public pool stats pause flag and maintenance notice.

Mining Pool admin now shows:

- Pool status, stratum details, main/nano pool selection, connected workers, worker names, pool hashrate, accepted/rejected shares, reject percentage, best share, latest share difficulty, current network difficulty, estimated block odds, blocks found, payout queue/state rows, pool fee/source notes, safely readable collector logs, miner search, and CSV export for miner/share stats.
- Controls to refresh stats, pause/resume public pool stats display, and add an admin maintenance notice.
- Manual Webmin Terminal command guidance for collector refresh instead of restarting services from PHP.

Node admin now shows:

- Online/offline state, block height, best block hash, peer count, inbound/outbound peer counts, network difficulty, mempool count, verification progress, disk usage, RPC status, node version/subversion, uptime when available, and peer rows.
- Safe refresh action and manual service status command guidance.

Blockchain Stats admin now shows:

- Chain height, headers, difficulty, mempool count, peers, best block hash, verification status, network hashrate, median time, disk size, latest blocks, and latest transactions.
- Controls to refresh stats and rebuild derived stats by clearing API cache only.

Explorer Stats admin now shows:

- Explorer/indexer status based on available RPC-backed explorer API state, indexed height, node height, index lag, latest blocks, latest transactions, failed index job log snippets when safely readable, and explorer status fields.
- Controls to refresh stats, clear explorer/API cache, and rebuild derived stats.
- Manual Webmin Terminal command guidance for reindex/service operations because there is no safe PHP command queue for restarting services.

Safety notes:

- No RPC credentials are displayed.
- No fake blockchain, pool, or explorer data is generated.
- No services are restarted directly from PHP.
- All admin POST controls use CSRF tokens and write audit log entries.

## Public Website Content Admin Completed

Completed public content admin foundation:

- Added `hobbyhash-clean/wallet/migrations/005_content_admin_controls.sql`.
- Added `public_html/app/content_admin.php` for slug handling, content table checks, page-view counts, and hardcoded docs inventory.
- Added `public_html/admin/content.php` as a complete tabbed admin controller for Pages & Content, Docs, Downloads, Announcements, Burn Events, Treasury / Reserve, and Support Messages.
- Updated admin navigation in `public_html/app/admin_view.php` so Pages & Content, Docs, Downloads, Announcements, Burn Events, and Support Messages point to the new content admin tabs.
- Added `docs_pages` for admin-managed documentation pages without removing hardcoded public docs.
- Extended `downloads` with description, recommended/deprecated flags, and sort order.
- Extended `announcements` with homepage toggle, wallet dashboard toggle, SEO title, and SEO description.
- Extended `burn_events` with requested statuses, publish flag, and public notes while preserving existing burn tracker behavior.
- Extended treasury/reserve categories and movements with requested statuses and public/private toggles.
- Extended `support_messages` with read/unread and spam controls.
- Added `docs_pages` to migration health checks.

Docs admin now supports:

- Listing hardcoded public docs and managed docs pages.
- Creating/editing managed docs pages.
- Publish/unpublish/archive via status.
- Sort order, category, SEO title, SEO description, and preview.
- Docs page view counts from `site_pageviews` where analytics data exists.

Downloads admin now supports:

- Listing downloads, adding/editing downloads, publish/unpublish/archive status, version, platform, file URL, SHA256 checksum, download count, download event logs, recommended flag, deprecated flag, and sort order.
- Upload instructions explain that uploads are not enabled and external/already-uploaded file URLs are supported.

Announcements admin now supports:

- Create/edit, publish/unpublish/archive, pin/unpin, scheduled publish date, homepage toggle, wallet dashboard toggle, SEO title, and SEO description.

Burn Events admin now supports:

- Add/edit burn events with amount, burn address, TXID, proof URL, status, private notes, public notes, and publish/unpublish flag.

Treasury / Reserve admin now supports:

- Reserve categories with percentages, statuses, notes, and public/private toggle.
- Reserve movements with category, amount, TXID, movement type, status, notes, and public/private toggle.

Support Messages admin now supports:

- Inbox, read/unread, status changes, admin notes, search, and spam/archive controls.
- Existing threaded support ticket reply flow remains available through `public_html/admin/tickets.php`.

Safety notes:

- Existing hardcoded public pages remain in place.
- No current content is deleted.
- Admin-created content is stored separately until public templates are intentionally wired to consume it.
- All content admin POST actions use CSRF tokens and write `admin_audit_log` entries.

## Admin Roles And Permissions Completed

Completed admin roles and permissions foundation:

- Added `hobbyhash-clean/wallet/migrations/006_admin_roles_permissions.sql`.
- Added `role` to `admin_users` with roles: Super Admin, Site Admin, Content Manager, Support Manager, Wallet Manager, Pool Manager, Analytics Viewer, and Read Only.
- Existing admin accounts are automatically assigned `super_admin`, preserving current login access.
- Added `public_html/app/admin_permissions.php` for role labels, permission labels, role permission maps, route-to-permission mapping, server-side permission checks, friendly access denied pages, and last-Super-Admin count checks.
- Updated `public_html/app/auth.php` so protected admin requests enforce permissions server-side through `admin_require_user()`.
- Updated `public_html/app/admin_view.php` so sidebar navigation hides items the current admin role cannot access.
- Added `public_html/admin/admin-users.php` for admin role/status management and a role permission matrix.
- Updated dashboard shortcuts in `public_html/admin/index.php` so role-restricted shortcut cards/buttons are hidden.
- Updated Security Center lock-admin control so it cannot lock the last active Super Admin.

Permissions now cover:

- Dashboard, analytics, visitors, bots, downloads, docs, announcements, wallet controls, withdrawals, users, mining pool, nodes, explorer, treasury/reserve, burn events, support messages, security center, admin users, settings, and audit logs.

Safety notes:

- The current admin remains Super Admin.
- The last active Super Admin cannot be demoted or deactivated.
- Permission checks are enforced server-side, not only in the UI.
- Role and admin status changes are written to `admin_audit_log`.

## Settings Section Completed

Completed settings/admin-user work:

- Added `public_html/admin/settings.php` as a complete tabbed Settings section.
- Added tabs for General Site, Branding, Analytics, Bot / Rate Limit, SMS / Authenticator, Wallet, Mining Pool, Node / Explorer, Downloads, Docs, Email / Notification, SMTP, Site Status / Maintenance, SEO, and Legal / Risk Notice settings.
- Settings are stored in `admin_settings` where no existing operational table already owns the value.
- Wallet controls that already belong to `wallet_settings` update that table directly, including deposits, withdrawals, maintenance mode, minimum withdrawal, and manual review threshold.
- Site Status / Maintenance controls continue to update `site_settings`, including bypass IP, pre-launch title/message/ETA, and maintenance title/message/start/end.
- SMS / Authenticator controls now live in the new Settings page and continue to update `wallet_settings` and the current admin SMS flag.
- SMTP controls now live in the new Settings page and continue to update `smtp_settings`, preserving the old "leave password blank to keep existing password" behavior.
- Added `getSetting()` and `updateSetting()` wrappers in `public_html/app/settings.php` while preserving existing `admin_setting_get()` and `admin_setting_set()` helpers.
- Updated admin navigation so Settings opens the new complete settings page.
- Added admin user creation controls to `public_html/admin/admin-users.php`, including role selection, active status defaults, unique username/email validation, hashed passwords, and audit logging.
- Fixed admin checkbox/radio alignment in `public_html/assets/css/hobc-admin.css` so checkbox text lines up correctly.

Safety notes:

- Existing config constants remain untouched.
- Secret values such as Twilio account/API secrets and RPC credentials are not exposed on the Settings page. SMTP password values can be replaced there, but saved passwords are never rendered back into the form.
- All Settings and Admin Users POST actions use CSRF protection.
- Every setting change and admin-user creation action writes to the admin audit log.

## Admin Audit Logging Completed

Completed audit logging work:

- Hardened `public_html/app/security_log.php` so `admin_audit()` redacts sensitive metadata keys before JSON storage.
- New admin audit rows store the real request IP address in the protected admin audit field so admin logs can group and filter correctly.
- `admin_audit()` now catches write failures and logs them without breaking the admin action that triggered the audit entry.
- Normalized successful admin login audit action to `admin_login_success`.
- Added missing auth-helper audit rows for throttled admin login attempts and failed admin password checks.
- Added CSV export audit logging for the Bots & Crawlers export flows.
- Added `hobbyhash-clean/wallet/migrations/007_admin_audit_logging.sql` for searchable audit indexes on action and target/entity fields.
- Fixed System Health migration checks to use the real `admin_audit_log` table name.
- Rebuilt `public_html/admin/audit.php` into a full Audit Logs admin page.

Audit Logs admin page now supports:

- Date range filter.
- Admin user filter.
- Action filter.
- Entity type filter.
- Free-text search across action, admin, entity, and metadata JSON.
- Pagination.
- Detail view for individual audit rows.
- CSV export of filtered results.

Covered audit categories include:

- Login success, login failed, throttled login, logout, password changes, admin 2FA changes, settings changes, content/docs/download/announcement/burn/reserve updates, wallet holds, withdrawal approvals/rejections/manual review, admin user creation/status/role updates, bot allowlist/blocklist changes, security setting changes, maintenance mode changes, cache clears, and exports.

Safety notes:

- Secrets, passwords, seed phrases, private keys, one-time codes, CSRF tokens, and similar sensitive metadata are redacted from audit JSON.
- Existing admin actions continue to complete even if audit logging fails.
- Audit CSV exports label the IP column as IP address. Older rows may still contain prior hash values, but new rows use the real IP.

## Admin-To-Public Website Integration Pass Completed

Completed integration work:

- Added `public_html/app/public_settings.php` for safe public reads of admin settings and published content rows.
- Updated `public_html/includes/header.php` so public pages consume admin-managed site name, coin name, ticker, logo URL, primary theme color, SEO defaults, robots toggle, and public notice/banner.
- Updated `public_html/includes/icon-meta.php` so favicon, app title, and theme colors use admin branding settings when available.
- Updated `public_html/includes/footer.php` so footer logo/text and legal risk notice come from admin settings.
- Updated public navigation so disabled wallet, docs, downloads, explorer, main pool, and nano pool settings hide those links without deleting the pages.
- Updated `public_html/wallet/index.php` and `public_html/wallet/register.php` so `wallet.public_enabled` controls the public wallet landing and registration flow.
- Updated `public_html/docs/index.php` so `docs.public_enabled`, docs search visibility, docs last-updated visibility, and published `docs_pages` rows affect the public docs page.
- Updated `public_html/downloads/index.php` so `downloads.public_enabled`, `downloads.show_checksums`, legal download warning, and published admin `downloads` rows affect the public downloads page.
- Updated `public_html/explorer/index.php` and `public_html/api/explorer/status/index.php` so `explorer.public_enabled` and explorer maintenance message are enforced on direct public access and API status.
- Updated `public_html/pool/main/index.php`, `public_html/pool/nano/index.php`, and `public_html/api/_bootstrap.php` so main/nano pool enabled settings affect public pool pages and pool status APIs.
- Updated `public_html/api/wallet/status/index.php` so wallet public enabled and wallet custody notice affect public wallet API responses.
- Updated `public_html/api/chain/status/index.php` so `node.show_status_publicly` can hide public node status details.
- Updated `public_html/app/throttle.php` so `rate_limit.enabled` affects the shared throttle helper.
- Updated `public_html/index.php` so published homepage announcements managed by admin appear on the public homepage.
- Updated `public_html/burn/index.php` so published admin burn events appear on the public burn tracker.
- Updated `hobc_reserve_categories()` in `public_html/api/_bootstrap.php` so public reserve pages/APIs can use admin-managed public reserve categories when present, with the original hardcoded categories as a safe fallback.
- Updated `public_html/admin/content.php` so `downloads.require_checksum_before_publish` is enforced when publishing admin-managed downloads.
- Updated `public_html/wallet/dashboard.php` so published announcements flagged for the wallet dashboard appear to logged-in wallet users.
- Updated `public_html/contact.php` so support ticket creation sends admin notification email to `notifications.admin_email` when `notifications.support_enabled` is on.

Remaining follow-up improvements:

- Build individual public routes for admin-managed docs pages instead of only listing summaries on `/docs/`.
- Replace hardcoded download sections with a fully database-driven renderer once all production download rows are entered and verified.
- Add public announcement archive/detail pages if announcements become more than homepage cards.
- Expand frontend templates to consume every SEO/legal setting on a per-page basis where needed.

