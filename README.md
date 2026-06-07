# HobbyHash Coin — Web Portal

Public website, wallet UI, admin panel, APIs, i18n, and the social bot service for [hobbyhashcoin.com](https://hobbyhashcoin.com).

This repository contains **application source only**. Server secrets, chain data, pool binaries, and wallet private config stay on the host and are **not** tracked here.

## What's included

| Path | Purpose |
|------|---------|
| `public_html/` | PHP portal, wallet, admin, APIs, translations |
| `social-bot/` | Node.js social posting service (Discord / X / Facebook) |
| `docs/` | Deployment templates (no live secrets) |

## What's excluded (stays on server)

- `public_html/.env` — Google Translate keys, wallet config path
- `hobbyhash-wallet-private/config.php` — DB, RPC, Twilio, salts
- `social-bot/.env` and `social-bot/data/` — bot tokens, SQLite DB
- `vendor/`, `node_modules/` — install with Composer / npm
- `downloads/` binaries, logs, caches, SQL dumps

See each directory's `.gitignore` and the root `.gitignore`.

## Server setup (summary)

### 1. PHP site (`public_html/`)

```bash
cd public_html
composer install --no-dev
cp .env.example .env
# Edit .env locally — never commit it
```

Create wallet config from the template:

```bash
mkdir -p ~/hobbyhash-wallet-private
cp docs/wallet-config.example.php ~/hobbyhash-wallet-private/config.php
chmod 600 ~/hobbyhash-wallet-private/config.php
# Edit config.php with real DB/RPC/SMS values
```

Set `HOBC_WALLET_CONFIG` in `public_html/.env` to that private path.

### 2. Social bot (`social-bot/`)

```bash
cd social-bot
npm install
cp .env.example .env
# Edit .env — platform keys; internal token goes in data/internal-token
npm run migrate
npm run seed
```

See `social-bot/README.md` for systemd and admin integration.

### 3. Before pushing to GitHub

```bash
./scripts/check-secrets.sh
git status
```

Do **not** push if the check reports possible secrets.

## i18n

English catalogs live under `public_html/lang/en/`. After adding keys:

```bash
cd public_html
php jobs/i18n_translate.php translate:check
```

## Security notes

- Never commit `.env`, `config.php`, API keys, RPC cookies, or Twilio credentials.
- Keep wallet config **outside** `public_html/` with mode `600`.
- The social bot runs with `DRY_RUN=1` by default until you explicitly go live.
- Rotate any credential that was ever stored in a tracked file.

## License

Proprietary — HobbyHash Coin project infrastructure.
