# HOBC Web Wallet - Custodial Wallet Specification

## 1) Scope and Intent

This document defines the full backend and security architecture for **HOBC Web Wallet**, a **custodial** wallet service.

Custodial means:
- The server controls private keys and signs on-chain transactions.
- Users do **not** control private keys in this system.
- Users hold internal account balances represented by an auditable ledger.

This design is intentionally honest about trust and risk:
- No fake balances
- No fake deposits
- No fake confirmations
- No fake withdrawals
- No fake txids

## 2) Custodial Risk Statement

Users must understand:
- Funds in the web wallet depend on operator security, solvency, and operational correctness.
- Compromise of server/admin access can compromise custody.
- Service downtime can delay access/withdrawals.
- For large balances, users should prefer a local non-custodial wallet they control.

UI and policy must clearly disclose this risk at registration, deposit, and withdraw screens.

## 3) High-Level System Architecture

### 3.1 Components

1. **Web/API service** (`127.0.0.1:18766` only)
   - Handles register/login/session/user actions/admin actions.
   - Reads/writes PostgreSQL.
   - Never exposes node RPC to browsers.

2. **Background chain scanner**
   - Talks to HOBC RPC at `127.0.0.1:18762`.
   - Detects/updates deposits.
   - Detects withdrawal confirmations.
   - Writes scan progress to `chain_scan_state`.

3. **Withdrawal worker**
   - Processes approved withdrawals.
   - Builds/sends real on-chain transaction.
   - Stores txid and state transitions.

4. **Admin controls service path**
   - Approval queue for withdrawals above threshold.
   - Pause switches for maintenance/deposit/withdraw/scanner.
   - Solvency checks (liabilities vs hot balance).

5. **PostgreSQL (`hobbyhash_wallet`)**
   - Source of truth for users, ledger, deposits, withdrawals, settings, audits.

### 3.2 Network Boundaries

- Browser <-> Web app only.
- Web app/worker/scanner <-> local DB.
- Web app/worker/scanner <-> local HOBC RPC (`127.0.0.1:18762`) only.
- RPC credentials remain server-side only (never in JavaScript).

## 4) Functional Flows

## 4.1 Registration and Authentication

Required:
- Username + email + password registration.
- Password hashing: **Argon2id preferred**; bcrypt acceptable fallback.
- Login throttling and IP/account rate limits.
- Optional 2FA (TOTP) support.
- Secure session cookie flags: `HttpOnly`, `Secure`, `SameSite=Strict` (or `Lax` if strict breaks required flows), rotation on privilege changes/login.
- CSRF protection for all state-changing endpoints.

Recommended auth policy:
- Minimum password length >= 12.
- Block common/breached passwords.
- Progressive lockout after repeated failed logins.
- Session revocation on password reset.

## 4.2 Deposit Address Assignment

- Server generates deposit addresses via local RPC (`getnewaddress`) from hot wallet.
- Address is stored in `deposit_addresses` and mapped to one user.
- Address reuse policy:
  - Default: issue new address when requested, keep old addresses valid indefinitely.
  - Never reassign an address to a different user.

## 4.3 Deposit Detection and Crediting

1. Scanner reads new blocks from `chain_scan_state.last_scanned_height`.
2. For each tx output, match outputs to known addresses in `deposit_addresses`.
3. Create or update `deposits` rows with txid/vout/amount/block_height/confirmations.
4. Deposit state machine:
   - `detected` -> `confirming` -> `credited`
   - `orphaned` for reorg invalidation.
5. Credit only when confirmations >= `wallet_settings.deposit_confirmations_required`.
6. On credit:
   - Insert immutable `ledger_entries` credit row.
   - Mark `deposits.credited_at`.

Reorg handling:
- If a credited deposit becomes orphaned, insert compensating negative ledger entry and set deposit `orphaned`.

## 4.4 Internal Ledger and Balances

- User balances are derived from immutable `ledger_entries`.
- Every balance-changing operation writes a ledger entry with:
  - `entry_type` (`deposit_credit`, `withdraw_debit`, `withdraw_fee`, `adjustment`, `reorg_reversal`)
  - Signed `amount`
  - `reference_table` + `reference_id`
  - `created_by_type` (`system`, `admin`, `scanner`, `withdrawal_worker`)
- No direct mutable "balance field" as source of truth.

## 4.5 Withdrawals

1. User submits withdrawal request.
2. Prechecks:
   - withdrawal pause switch
   - maintenance mode
   - min/max limits
   - user balance sufficient
   - rate limiting/throttling
   - optional 2FA challenge
3. Insert `withdrawals` row as:
   - `awaiting_approval` if amount > approval threshold
   - else `approved`
4. Lock funds by writing `withdraw_debit` ledger entry immediately when request is accepted.
5. Withdrawal worker picks approved jobs, calls `sendtoaddress`/`sendmany`, stores real `txid`, marks `broadcasted`.
6. Scanner tracks tx confirmations:
   - `broadcasted` -> `confirming` -> `confirmed`
7. If broadcast fails before txid is known:
   - mark `failed`
   - insert compensating credit ledger entry (unlock funds).

Critical rule:
- Withdrawal status cannot be `broadcasted`/`confirming`/`confirmed` without real txid.
- Withdrawal cannot be `confirmed` unless chain confirmations >= required threshold.

## 4.6 Admin Approval and Controls

Admin panel capabilities:
- View pending withdrawals.
- Approve/reject withdrawals above threshold.
- View liabilities and hot wallet balance.
- View warnings when liabilities > hot wallet balance.
- Set pause switches and limits.

All admin actions must be written to `admin_audit_log` with actor, timestamp, IP, action, and target.

## 4.7 Solvency and Reconciliation

Definitions:
- **Liabilities** = sum of all user balances from ledger.
- **Hot wallet balance** = on-chain balance snapshot from wallet RPC.

Reconciliation job:
- Periodically snapshots hot wallet (`wallet_hot_balance_snapshots`).
- Calculates current liabilities (DB query/view).
- Raises warning if liabilities > hot wallet.
- Logs warning event for admin visibility.

## 4.8 Pause and Maintenance Controls

Required switches in `wallet_settings`:
- `maintenance_mode`
- `deposits_paused`
- `withdrawals_paused`
- `scanner_paused`

Behavior:
- Maintenance mode: block most user actions, allow login + status messaging.
- Deposits paused: stop issuing new addresses (existing deposit processing policy configurable).
- Withdrawals paused: block new withdrawal creation and worker broadcast.
- Scanner paused: freeze chain processing (manual intervention mode).

## 4.9 Recovery from RPC Downtime

When RPC is down:
- Do not fabricate deposit/withdraw status.
- Mark scanner/worker health degraded.
- Queue pending actions.

Recovery sequence:
1. Restore RPC connectivity.
2. Resume scanner from `chain_scan_state` last safe height.
3. Reprocess recent safety window (e.g., last N blocks) to handle missed events/reorg.
4. Reconcile pending withdrawals by txid lookup.
5. Recompute liabilities and emit audit event for recovery completion.

## 5) Security Requirements

1. Password hash with Argon2id (or bcrypt fallback).
2. No plaintext passwords.
3. No plaintext wallet passphrase storage.
4. CSRF token validation on all non-GET state-changing actions.
5. Secure cookie flags.
6. Session fixation protection (rotate on login).
7. API rate limits (per IP + per account + endpoint bucket).
8. Login throttling and lockout.
9. Withdrawal throttling (count/amount windows).
10. Strict server-side input validation.
11. Prepared statements/ORM protections (no SQL injection).
12. Admin endpoints role-gated and separately audited.
13. Optional IP allowlist for admin panel.

## 6) Required Pages and API Surfaces

User:
- register
- login
- logout
- dashboard
- deposit
- withdraw
- transaction history
- security settings

Admin:
- admin dashboard
- admin withdrawals
- admin audit log

Ops:
- health check

## 7) Configuration (Suggested Defaults)

- App bind: `127.0.0.1:18766`
- DB name: `hobbyhash_wallet`
- RPC host/port: `127.0.0.1:18762`
- Deposit confirmations required: `6` (configurable)
- Withdrawal confirmations considered final: `1` or higher (configurable)
- Manual approval threshold: operator-configurable (example: `5000 HOBC`)
- Withdrawal daily user limit: configurable
- Hot wallet reserve alert threshold: configurable

## 8) Operational Guidance

- Keep hot wallet online balance limited; move excess to cold storage.
- Encrypt wallet and unlock only for broadcast operations where practical.
- Back up encrypted wallet and DB regularly.
- Monitor:
  - scanner lag
  - RPC health
  - failed withdrawals
  - liabilities vs hot balance
  - auth failures spikes

## 9) Non-Goals (for this phase)

- No UI implementation in this phase.
- No public API key ecosystem in this phase.
- No multi-asset support in this phase.

This spec defines backend-truthful custodial behavior only.
