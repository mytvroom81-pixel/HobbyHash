# HobbyHash Social Bot

Official **HobbyHash Update Bot** for [hobbyhashcoin.com](https://hobbyhashcoin.com). Posts natural, varied updates to Discord, X (Twitter), and Facebook Page on a randomized schedule.

## Features

- **Live HOBC data** — pool JSON, public APIs, MySQL analytics/downloads/docs, payout state
- **Dry-run mode** — ON by default; generates and logs posts without publishing
- Randomized posting (1 post every 3–6 hours, max 5/day per platform)
- Event-driven posts (blocks, milestones, releases, docs, pool stats)
- Integrated admin at `/admin/social-bot.php` (existing login + 2FA)
- Duplicate detection, quiet hours, approval queue

## Install

```bash
cd /home/hobbyhashcoin/social-bot
npm install
cp .env.example .env
# Edit .env — platform keys; token auto-stored in data/internal-token
npm run migrate
npm run seed
npm run test-generate
npm start
```

Admin: **`https://hobbyhashcoin.com/admin/social-bot.php`** (sidebar → Social Bot)

## Systemd (production)

```bash
# As root — install service, fix .env ownership if needed
sudo cp /home/hobbyhashcoin/social-bot/systemd/hobbyhash-social-bot.service /etc/systemd/system/
sudo chown hobbyhashcoin:hobbyhashcoin /home/hobbyhashcoin/social-bot/.env
sudo systemctl daemon-reload
sudo systemctl enable hobbyhash-social-bot
sudo systemctl start hobbyhash-social-bot
sudo systemctl status hobbyhash-social-bot
```

Logs:

```bash
journalctl -u hobbyhash-social-bot -f
tail -f /home/hobbyhashcoin/social-bot/logs/dry-run.log
```

Restart after config changes:

```bash
sudo systemctl restart hobbyhash-social-bot
```

## Data sources (wired)

| Collector | Sources |
|-----------|---------|
| **blocks.js** | `/api/chain/status/`, `/api/chain/latest-blocks/`, `/api/pool/main/status/`, `pool-stats-*.json`, `payoutd-*-state.json`, `hobbyhash-cli`, explorer DB (if granted) |
| **miners.js** | `/api/pool/main/status/`, `/api/pool/nano/status/`, `pool-stats-*.json`, `pool/pool.status`, miner leaderboard (privacy-redacted) |
| **siteStats.js** | MySQL `site_pageviews`, `site_visitors` (aggregates only — no IPs/emails/UA) |
| **downloads.js** | MySQL `downloads`, `docs_pages`, `announcements`, PDF mtime for whitepaper/factsheet |

MySQL credentials auto-load from HOBC `wallet/config.php` when `MYSQL_USER` is not set.

## Dry-run mode

**Enabled by default.** Posts are generated and written to `logs/dry-run.log` with status `dry_run` — nothing hits Discord/X/Facebook.

Turn off when ready:

1. Admin → Social Bot → Settings → uncheck **Dry-run mode**, or
2. Set `DRY_RUN=0` in `.env` and `dry_run_mode: false` in bot settings

Admin **Approve & publish** always uses live publish (explicit human action).

## Scripts

```bash
npm run migrate          # SQLite schema
npm run seed             # Templates, platforms, default settings
npm run test-generate    # Live data report + sample posts (no publish)
npm run test-discord     # Test Discord (dry-run unless --live)
npm run test-x           # Test X (dry-run unless --live)
npm run test-facebook    # Test Facebook (dry-run unless --live)
npm run test-discord -- --live   # Actually publish
npm start                # Run scheduler + internal API
```

## Environment

See `.env.example`. Key integration vars:

```
SOCIAL_BOT_INTERNAL_TOKEN=...   # or data/internal-token file
SOCIAL_BOT_STANDALONE_ADMIN=false
DRY_RUN=1
```

## Admin tabs

Overview · Queue · History · Replies · Templates · Platforms · Settings · Preview · Test Post · Audit

## Safety

- No IPs, emails, wallet balances, or full addresses in posts
- Worker names redacted to labels like `worker BITAXE601`
- Blocked hype/investment phrases
- Rate limits and cooldowns

## License

Part of the HobbyHash Coin project infrastructure.
