# HOBC Web Wallet MVP Test Plan

## Required Go-Live Tests

### 1) Database install works

Command:

```bash
mysql -u hobbyhash_wallet_app -p hobbyhash_wallet < /home/hobbyhashcoin/hobbyhash-clean/wallet/install.sql
```

Verify required tables exist:

```bash
mysql -u hobbyhash_wallet_app -p -e "USE hobbyhash_wallet; SHOW TABLES;"
```

### 2) Registration works

- Open `/wallet/register.php`
- Register a new user.
- Verify row in `users` and `user_security`.
- Verify password is hashed (not plaintext).

### 3) Login works

- Open `/wallet/login.php`
- Login with registered user.
- Verify session record created in `sessions`.
- Verify dashboard accessible.

### 4) Deposit address generation works

- Open `/wallet/deposit.php`
- Generate address.
- Verify new row in `deposit_addresses`.
- If RPC offline, confirm clean error shown (no fake address).

### 5) Scanner can read chain height

Run:

```bash
php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/deposit_scanner.php
```

Verify:
- `chain_scan_state.last_scanned_height` updates
- `chain_scan_state.scanner_status = ok` when healthy

### 6) Ledger entries work

- Create a controlled deposit detection/credit scenario (or manual adjustment in test DB).
- Verify immutable entry inserted in `ledger_entries`.
- Attempt update/delete on ledger row must fail by trigger.

### 7) Withdrawal request creates pending record

- Submit from `/wallet/withdraw.php`.
- Verify `withdrawals` row created with `pending` or `awaiting_approval`/`approved`.
- Verify `withdraw_debit` ledger entry exists.

### 8) Admin approval works

- Login at `/wallet/admin_login.php`.
- Approve or reject from `/wallet/admin_withdrawals.php`.
- Verify status transition.
- Verify `admin_audit_log` row exists.
- If rejected, verify `refund_credit` ledger entry.

### 9) Broadcaster creates real txid on regtest/controlled test

Run:

```bash
php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/withdrawal_broadcaster.php
```

Verify:
- `withdrawals.txid` is real for successful broadcast
- Status moves to `broadcasted` then `confirming/confirmed` via updater
- No fake txids accepted

### 10) Reconciliation report works

Run:

```bash
php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/hot_wallet_balance_checker.php
php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/liability_reconciler.php
```

Verify row in `reconciliation_reports`:
- liabilities total
- trusted hot balance
- delta
- `status` (`ok` or `warning`)

## Security Test Checklist

- CSRF required for all POST forms.
- Session cookies are HttpOnly/Secure/SameSite.
- Login throttling blocks repeated abuse.
- Withdrawal throttling blocks rapid abuse.
- Admin pages reject non-admin sessions.
- No RPC credentials appear in page source.
- Config file is outside `public_html`.
- SQL uses prepared statements.

## Honesty Behavior Checklist

- Scanner offline -> dashboard warns deposits unavailable/syncing.
- RPC offline -> dashboard shows backend offline.
- Withdrawal broadcaster paused -> withdrawals remain pending/approved (not fake complete).
- Low hot wallet balance -> withdrawal fails with real reason and refund entry.
- No market price shown.
- No fake confirmations.
- No fake txids.
