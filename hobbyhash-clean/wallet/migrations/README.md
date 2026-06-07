# HOBC Wallet/Admin Migrations

Run migrations from the server shell:

```bash
php /home/hobbyhashcoin/hobbyhash-clean/wallet/run_migrations.php
```

The runner uses the existing wallet database configuration loaded by `public_html/app/db.php`.

Safety rules:

- Migrations do not drop existing tables.
- Migrations do not erase current data.
- Applied migrations are recorded in `schema_migrations`.
- The admin panel System Health page checks required admin database objects and warns when migrations are missing.

