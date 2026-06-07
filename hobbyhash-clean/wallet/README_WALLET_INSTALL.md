# HOBC Web Wallet MVP Install Guide (PHP 8.3 + MariaDB)

## Overview

This installs the **custodial** HOBC web wallet MVP built in:
- PHP 8.3
- MySQL/MariaDB
- Local HOBC RPC backend (`127.0.0.1:18762`)

Code source:
- `/home/hobbyhashcoin/hobbyhash-clean/wallet`

Public target:
- `/home/hobbyhashcoin/public_html/wallet`

## 1) Required Files

- `install.sql`
- `config.example.php`
- `public/*.php` pages
- `jobs/*.php` backend jobs
- `app/*.php` internal backend logic

## 2) Create Database and User

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS hobbyhash_wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'hobbyhash_wallet_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON hobbyhash_wallet.* TO 'hobbyhash_wallet_app'@'localhost'; FLUSH PRIVILEGES;"
```

Apply schema:

```bash
mysql -u hobbyhash_wallet_app -p hobbyhash_wallet < /home/hobbyhashcoin/hobbyhash-clean/wallet/install.sql
```

## 3) Create Private Config (Outside public_html)

Create private config path:

```bash
mkdir -p /home/hobbyhashcoin/hobbyhash-wallet-private
cp /home/hobbyhashcoin/hobbyhash-clean/wallet/config.example.php /home/hobbyhashcoin/hobbyhash-wallet-private/config.php
```

Edit:
- DB credentials
- RPC password/cookie value
- security/session settings

Set secure file perms:

```bash
chmod 600 /home/hobbyhashcoin/hobbyhash-wallet-private/config.php
chown hobbyhashcoin:hobbyhashcoin /home/hobbyhashcoin/hobbyhash-wallet-private/config.php
```

## 4) Deploy Wallet Code to Public Target

```bash
mkdir -p /home/hobbyhashcoin/public_html/wallet
rsync -a --delete /home/hobbyhashcoin/hobbyhash-clean/wallet/public/ /home/hobbyhashcoin/public_html/wallet/
mkdir -p /home/hobbyhashcoin/public_html/app
rsync -a --delete /home/hobbyhashcoin/hobbyhash-clean/wallet/app/ /home/hobbyhashcoin/public_html/app/
```

Set environment variable for PHP runtime (Apache/FPM/vhost):

```bash
HOBC_WALLET_CONFIG=/home/hobbyhashcoin/hobbyhash-wallet-private/config.php
```

Do **not** place config inside `public_html`.

## 5) Permissions

```bash
mkdir -p /home/hobbyhashcoin/hobbyhash-clean/wallet/logs
chown -R hobbyhashcoin:hobbyhashcoin /home/hobbyhashcoin/hobbyhash-clean/wallet/logs
chmod 750 /home/hobbyhashcoin/hobbyhash-clean/wallet/logs
```

## 6) Seed First Admin

Generate password hash:

```bash
php -r 'echo password_hash("CHANGE_ME_ADMIN_PASSWORD", PASSWORD_ARGON2ID), PHP_EOL;'
```

Insert admin:

```bash
mysql -u hobbyhash_wallet_app -p hobbyhash_wallet -e "INSERT INTO admin_users (username,email,password_hash,is_active) VALUES ('admin','admin@example.com','PASTE_HASH_HERE',1);"
```

## 7) Run Backend Jobs (Cron)

Run every minute:

```bash
* * * * * php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/deposit_scanner.php >> /home/hobbyhashcoin/hobbyhash-clean/wallet/logs/scanner.log 2>&1
* * * * * php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/confirmation_updater.php >> /home/hobbyhashcoin/hobbyhash-clean/wallet/logs/confirmations.log 2>&1
* * * * * php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/withdrawal_broadcaster.php >> /home/hobbyhashcoin/hobbyhash-clean/wallet/logs/broadcaster.log 2>&1
* * * * * php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/hot_wallet_balance_checker.php >> /home/hobbyhashcoin/hobbyhash-clean/wallet/logs/hot_balance.log 2>&1
* * * * * php /home/hobbyhashcoin/hobbyhash-clean/wallet/jobs/liability_reconciler.php >> /home/hobbyhashcoin/hobbyhash-clean/wallet/logs/reconciler.log 2>&1
```

## 8) Health Check

Open:

`/wallet/health.php`

Expected JSON includes:
- db status
- rpc status
- scanner status

## 9) MVP Route Map

User:
- `/wallet/register.php`
- `/wallet/login.php`
- `/wallet/logout.php`
- `/wallet/dashboard.php`
- `/wallet/deposit.php`
- `/wallet/withdraw.php`
- `/wallet/transactions.php`
- `/wallet/security.php`

Admin:
- `/wallet/admin_login.php`
- `/wallet/admin_dashboard.php`
- `/wallet/admin_withdrawals.php`
- `/wallet/admin_audit.php`

Other:
- `/wallet/maintenance.php`
- `/wallet/health.php`

## 10) Pre-Public Checklist

Do not expose publicly until all pass:
1. database install works
2. registration works
3. login works
4. deposit address generation works
5. scanner can read chain height
6. ledger entries work
7. withdrawal request creates pending record
8. admin approval works
9. broadcaster creates real txid (regtest/controlled test)
10. reconciliation report works
