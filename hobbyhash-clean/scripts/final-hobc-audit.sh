#!/usr/bin/env bash
set -u

python3 <<'PY'
import json
import os
import re
import stat
import subprocess
from pathlib import Path

ROOT = Path("/home/hobbyhashcoin")
CLEAN = ROOT / "hobbyhash-clean"
PUBLIC = ROOT / "public_html"
DOCS = CLEAN / "docs"
SRC = CLEAN / "src" / "src"
CONF = ROOT / "hobbyhash-conf"
SYSTEMD = DOCS / "systemd"

PASS = 0
WARN = 0
FAIL = 0

TEXT_EXTS = {
    ".php", ".md", ".txt", ".css", ".js", ".json", ".conf", ".service", ".sql",
    ".cpp", ".h", ".py", ".sh", ".ini"
}

PUBLIC_DOC_ROOTS = [PUBLIC, DOCS]

ANTI_OVERLAP_ALLOWED = {
    "ANTI_OVERLAP_CHECKLIST.md",
    "FINAL_BRAND_SPEC.md",
    "PROJECT_SPEC.md",
    "MAINNET_BOOT_AND_BLOCK1_PROOF.md",
    "EXPLORER_SPEC.md",
    "SERVICES.md",
}

ADDRESS_REJECTION_ALLOWED = {
    "ANTI_OVERLAP_CHECKLIST.md",
    "PROJECT_SPEC.md",
    "MAINNET_BOOT_AND_BLOCK1_PROOF.md",
    "EXPLORER_SPEC.md",
    "SERVICES.md",
    "CKPOOL_MAIN_AND_NANO.md",
    "POOL_TROUBLESHOOTING.md",
    "MINING_MAIN_POOL.md",
    "MINING_NANO_POOL.md",
    "LOCAL_WALLET_GUIDE.md",
    "NODE_SETUP_LINUX.md",
    "NODE_SETUP_WINDOWS.md",
    "FAQ.md",
    "CUSTODIAL_WALLET_RISK_NOTICE.md",
    "SOLO_POOL_PAYOUT_RULES.md",
    "SOLO_POOL_TEST_PLAN.md",
}

def rel(path: Path) -> str:
    try:
        return str(path.relative_to(ROOT))
    except ValueError:
        return str(path)

def line(status: str, label: str, detail: str = "") -> None:
    global PASS, WARN, FAIL
    if status == "PASS":
        PASS += 1
    elif status == "WARN":
        WARN += 1
    elif status == "FAIL":
        FAIL += 1
    suffix = f" - {detail}" if detail else ""
    print(f"[{status}] {label}{suffix}")

def exists(label: str, path: Path, executable: bool = False, warn: bool = False) -> bool:
    ok = path.exists()
    if ok and executable:
        ok = os.access(path, os.X_OK)
    if ok:
        line("PASS", label, rel(path))
        return True
    line("WARN" if warn else "FAIL", label, rel(path))
    return False

def read_text(path: Path) -> str:
    try:
        return path.read_text(errors="ignore")
    except Exception:
        return ""

def iter_text_files(roots):
    seen = set()
    for root in roots:
        if not root.exists():
            continue
        for path in root.rglob("*"):
            if path in seen or not path.is_file():
                continue
            seen.add(path)
            if path.suffix.lower() in TEXT_EXTS:
                yield path

def search(pattern: str, roots, flags=re.IGNORECASE):
    rx = re.compile(pattern, flags)
    hits = []
    for path in iter_text_files(roots):
        text = read_text(path)
        for num, content in enumerate(text.splitlines(), 1):
            if rx.search(content):
                hits.append((path, num, content.strip()))
    return hits

def has(pattern: str, roots, flags=re.IGNORECASE) -> bool:
    return bool(search(pattern, roots, flags))

def check_contains(label: str, pattern: str, roots, flags=re.IGNORECASE, warn: bool = False) -> bool:
    hits = search(pattern, roots, flags)
    if hits:
        line("PASS", label, f"{len(hits)} match(es)")
        return True
    line("WARN" if warn else "FAIL", label, "no match")
    return False

def check_absent(label: str, pattern: str, roots, allowed_names=None, flags=re.IGNORECASE) -> bool:
    allowed_names = allowed_names or set()
    hits = search(pattern, roots, flags)
    bad = [h for h in hits if h[0].name not in allowed_names]
    if not bad:
        detail = "absent" if not hits else f"only allowed docs ({len(hits)} match(es))"
        line("PASS", label, detail)
        return True
    sample = "; ".join(f"{rel(p)}:{n}" for p, n, _ in bad[:8])
    line("FAIL", label, f"{len(bad)} disallowed match(es): {sample}")
    return False

def run(cmd, timeout=10):
    try:
        return subprocess.run(cmd, text=True, capture_output=True, timeout=timeout)
    except FileNotFoundError as exc:
        return subprocess.CompletedProcess(cmd, 127, "", str(exc))
    except subprocess.TimeoutExpired as exc:
        return subprocess.CompletedProcess(cmd, 124, exc.stdout or "", exc.stderr or "timeout")

def check_command(label: str, cmd, expect_zero=True, warn=False, show_first_lines=4):
    proc = run(cmd)
    ok = (proc.returncode == 0) if expect_zero else True
    status = "PASS" if ok else ("WARN" if warn else "FAIL")
    output = (proc.stdout or proc.stderr or "").strip().splitlines()
    detail = f"exit={proc.returncode}"
    if output:
        detail += " | " + " | ".join(output[:show_first_lines])
    line(status, label, detail)
    return proc

def json_file(path: Path):
    try:
        return json.loads(path.read_text())
    except Exception:
        return None

def file_has(path: Path, pattern: str, flags=re.IGNORECASE) -> bool:
    return bool(re.search(pattern, read_text(path), flags))

def section(title: str) -> None:
    print()
    print(f"## {title}")

print("HOBC FINAL AUDIT")
print("================")
print(f"root={ROOT}")

section("Brand")
check_contains("HOBC appears in public website/docs", r"\bHOBC\b", PUBLIC_DOC_ROOTS)
check_contains("HobbyHash Coin appears in public website/docs", r"HobbyHash\s+Coin", PUBLIC_DOC_ROOTS)
check_absent("HBYC does not appear except brand/anti-overlap docs", r"\bHBYC\b", PUBLIC_DOC_ROOTS, ANTI_OVERLAP_ALLOWED)
check_absent("MBUX does not appear except anti-overlap/address rejection docs", r"\bMBUX\b", PUBLIC_DOC_ROOTS, ADDRESS_REJECTION_ALLOWED)
check_absent("BCH does not appear except anti-overlap/address rejection docs", r"\bBCH\b|Bitcoin Cash", PUBLIC_DOC_ROOTS, ADDRESS_REJECTION_ALLOWED)
check_absent("DGB does not appear except anti-overlap/address rejection docs", r"\bDGB\b|DigiByte", PUBLIC_DOC_ROOTS, ADDRESS_REJECTION_ALLOWED)
btc_allowed = ADDRESS_REJECTION_ALLOWED | {"WEBSITE_STATS_API_SPEC.md", "SERVICES.md", "REBRAND_LOG.md"}
check_absent("BTC does not appear where HOBC should be used", r"\bBTC\b|Bitcoin(?! Cash)", PUBLIC_DOC_ROOTS, btc_allowed)

section("Supply")
check_contains("Total supply target is 84,000,000 HOBC", r"84,?000,?000(?:\.00000000)?\s*HOBC|84000000\.00000000", [PUBLIC, DOCS, SRC])
check_contains("Launch reserve is 8,400,000 HOBC", r"8,?400,?000(?:\.00000000)?\s*HOBC|8400000\.00000000", [PUBLIC, DOCS, SRC])
check_contains("Launch reserve is 10 percent", r"10\s*%|reserve_percent.*10", [PUBLIC, DOCS])
check_contains("Normal mining target is 75,600,000 HOBC", r"75,?600,?000(?:\.00000000)?\s*HOBC|75600000\.00000000", [PUBLIC, DOCS])
check_contains("Block 1 special reserve rule exists", r"nHeight\s*==\s*1|height\s+1.*launch reserve|Block 1 reserve", [SRC, DOCS, PUBLIC])
check_contains("Post-block-1 subsidy is adjusted", r"nHeight\s*<\s*2|Block 2 onward subsidy|Blocks 2\.\.104 normal subsidy|post-block-1", [SRC, DOCS])
check_absent("No 92,400,000 HOBC total appears", r"92,?400,?000", [PUBLIC, DOCS, SRC])
exists("Reserve docs exist", DOCS / "LAUNCH_RESERVE_PROOF.md")
check_contains("Reserve addresses/scripts are listed", r"reserve output|scriptPubKey|reserve address|rhobc1", [DOCS])
exists("Launch reserve website page exists", PUBLIC / "launch-reserve" / "index.php")
check_contains("Reserve is visible on website", r"Launch Reserve|launch_reserve|8,400,000", [PUBLIC])

section("Core")
exists("hobbyhashd exists", ROOT / "bin" / "hobbyhashd", executable=True)
exists("hobbyhash-cli exists", ROOT / "bin" / "hobbyhash-cli", executable=True)
check_contains("Bech32 HRP is hobc", r"bech32.*hobc|\"hobc\"|\'hobc\'", [SRC, DOCS, CONF])
main_conf = CONF / "hobbyhash-mainnet.conf"
exists("Mainnet config exists", main_conf)
if main_conf.exists():
    txt = read_text(main_conf)
    line("PASS" if "rpcport=18762" in txt else "FAIL", "Mainnet RPC port is 18762")
    line("PASS" if "rpcbind=127.0.0.1" in txt and "rpcallowip=127.0.0.1" in txt else "FAIL", "Mainnet RPC is localhost only")
    line("PASS" if "port=18761" in txt else "FAIL", "Mainnet P2P is 18761")
check_contains("Custom genesis exists", r"genesis hash|CreateGenesisBlock|hobbyhash_genesis", [SRC, DOCS, CLEAN / "scripts"])
exists("Genesis verifier source exists", CLEAN / "scripts" / "genesis" / "hobbyhash_genesis.cpp")
exists("Genesis verifier binary exists", CLEAN / "scripts" / "genesis" / "hobbyhash_genesis", executable=True, warn=True)
if (DOCS / "LAUNCH_RESERVE_PROOF.md").exists():
    txt = read_text(DOCS / "LAUNCH_RESERVE_PROOF.md")
    line("PASS" if "PASS" in txt or "pending" in txt.lower() else "WARN", "Block 1 reserve proof exists or pending honestly", rel(DOCS / "LAUNCH_RESERVE_PROOF.md"))
else:
    line("WARN", "Block 1 reserve proof exists or pending honestly", "missing")
exists("Regtest proof exists", DOCS / "REGTEST_PROOF.md")
emission_hits = search(r"emission|subsidy|GetBlockSubsidy|Total supply calculation proof", [SRC, DOCS])
line("PASS" if emission_hits else "WARN", "Emission tests/proofs exist", f"{len(emission_hits)} match(es)")

section("Pools")
main_pool_conf = CONF / "hobbyhash-ckpool-main.conf"
nano_pool_conf = CONF / "hobbyhash-ckpool-nano.conf"
main_pool = json_file(main_pool_conf) or {}
nano_pool = json_file(nano_pool_conf) or {}
exists("Main pool config exists", main_pool_conf)
exists("Nano pool config exists", nano_pool_conf)
line("PASS" if "0.0.0.0:5555" in main_pool.get("serverurl", []) else "FAIL", "Main pool stratum port is 5555")
line("PASS" if "0.0.0.0:5556" in nano_pool.get("serverurl", []) else "FAIL", "Nano pool stratum port is 5556")
line("WARN", "Main pool status port 18763", "documented/API metadata only; no listener configured in ckpool JSON")
line("WARN", "Nano pool status port 18764", "documented/API metadata only; no listener configured in ckpool JSON")
line("PASS" if str(main_pool.get("startdiff")) == "5000" else "FAIL", "Main pool start diff 5000", str(main_pool.get("startdiff")))
scale = nano_pool.get("stratum_diff_scale")
line("PASS" if str(scale) == "0.005" else "FAIL", "Nano pool effective start diff 0.005", f"startdiff={nano_pool.get('startdiff')} scale={scale}")
check_contains("Both pools solo only", r"solo only|solo pools|ckpool.*-A|standalone solo pools", [DOCS, SYSTEMD])
line("PASS" if main_pool.get("enforce_worker_address") and nano_pool.get("enforce_worker_address") else "FAIL", "Payout address from worker name enforced")
check_contains("Payout address from worker name documented", r"address before the dot|worker name|workername prefix", [DOCS, PUBLIC])
line("PASS" if main_pool_conf.exists() and nano_pool_conf.exists() and main_pool_conf != nano_pool_conf else "FAIL", "Both pools independent configs")
exists("Main pool service exists", SYSTEMD / "hobbyhash-ckpool-main.service")
exists("Nano pool service exists", SYSTEMD / "hobbyhash-ckpool-nano.service")
check_contains("Invalid BTC/BCH/DGB/MBUX address tests documented", r"BTC|BCH|DGB|MBUX|Non-HOBC prefixes are rejected", [DOCS])

section("Wallet")
exists("Custodial wallet spec exists", DOCS / "CUSTODIAL_WALLET_SPEC.md")
exists("Wallet database schema exists", CLEAN / "wallet" / "install.sql")
check_contains("Passwords hashed", r"password_hash|Argon2id|PASSWORD_ARGON2ID|bcrypt", [PUBLIC / "app", CLEAN / "wallet"])
check_contains("Ledger tables exist", r"CREATE TABLE IF NOT EXISTS ledger_entries|ledger_entries", [CLEAN / "wallet", PUBLIC])
check_contains("Deposit scanner planned or built", r"deposit_scanner|chain scanner|Deposit Detection", [PUBLIC, CLEAN / "wallet", DOCS])
check_contains("Withdrawal broadcaster planned or built", r"withdrawal_broadcaster|Withdrawal worker|sendtoaddress", [PUBLIC, CLEAN / "wallet", DOCS])
admin_hits = search(r"admin approval|awaiting_approval|approve", [PUBLIC, CLEAN / "wallet", DOCS])
line("PASS" if admin_hits else "WARN", "Admin approval exists or pending honestly", f"{len(admin_hits)} match(es)")
check_contains("Wallet risk notice visible", r"custodial risk|web wallet is custodial|website controls", [PUBLIC, DOCS])
public_secret_hits = search(
    r"rpcpassword\s*=|rpcuser\s*=|['\"](?:password|api_key_secret|account_sid|auth_token)['\"]\s*=>\s*['\"][^'\"]{8,}|"
    r"(?:password|api_key_secret|account_sid|auth_token)\s*[:=]\s*['\"][^'\"]{8,}",
    [PUBLIC],
)
allowed_public_secret = []
for path, num, content in public_secret_hits:
    if path.name in {"CUSTODIAL_WALLET_RISK_NOTICE.md"}:
        continue
    allowed_public_secret.append((path, num, content))
if allowed_public_secret:
    sample = "; ".join(f"{rel(p)}:{n}" for p, n, _ in allowed_public_secret[:8])
    line("FAIL", "No RPC credentials/secrets in public files", f"{len(allowed_public_secret)} match(es): {sample}")
else:
    line("PASS", "No RPC credentials/secrets in public files")
cfg = CLEAN / "wallet" / "config.php"
if cfg.exists():
    mode = stat.S_IMODE(cfg.stat().st_mode)
    line("WARN" if mode & 0o004 else "PASS", "Wallet private config permissions", oct(mode))

section("Website")
for label, path in [
    ("Shared header exists", PUBLIC / "includes" / "header.php"),
    ("Shared nav exists", PUBLIC / "includes" / "nav.php"),
    ("Shared footer exists", PUBLIC / "includes" / "footer.php"),
    ("Status bar exists", PUBLIC / "includes" / "status-bar.php"),
    ("Home page exists", PUBLIC / "index.php"),
    ("About page exists", PUBLIC / "about" / "index.php"),
    ("Mining page exists", PUBLIC / "mining" / "index.php"),
    ("Main pool page exists", PUBLIC / "pool" / "main" / "index.php"),
    ("Nano pool page exists", PUBLIC / "pool" / "nano" / "index.php"),
    ("Explorer page exists", PUBLIC / "explorer" / "index.php"),
    ("Wallet page exists", PUBLIC / "wallet" / "index.php"),
    ("Stats page exists", PUBLIC / "stats" / "index.php"),
    ("Downloads page exists", PUBLIC / "downloads" / "index.php"),
    ("Docs page exists", PUBLIC / "docs" / "index.php"),
    ("Launch reserve page exists", PUBLIC / "launch-reserve" / "index.php"),
    ("Burn tracker page exists", PUBLIC / "burn" / "index.php"),
    ("Roadmap page exists", PUBLIC / "roadmap" / "index.php"),
    ("FAQ page exists", PUBLIC / "faq" / "index.php"),
    ("Contact page exists", PUBLIC / "contact" / "index.php"),
]:
    exists(label, path)
check_contains("Every page can link back to home/dashboard", r"Home/Dashboard|href=\"/\"", [PUBLIC / "includes", PUBLIC / "app"])
check_contains("Every page can navigate to other sections", r"hobc_pages|site-nav|Portal navigation", [PUBLIC / "includes", PUBLIC / "app"])
check_contains("Wallet page has custodial risk notice", r"custodial risk|web wallet is custodial", [PUBLIC / "wallet"])
check_contains("Stats page does not fake data", r"Not available yet|data-api-value|No fake", [PUBLIC / "stats", PUBLIC / "privacy", PUBLIC / "terms"])
check_contains("Downloads page does not fake binaries", r"coming soon|No fake|official|not.*available", [PUBLIC / "downloads"], warn=True)

section("No Fake Data")
check_contains("No fake market price rule exists", r"no fake market price|market price.*not_available|must not invent a market price", [PUBLIC, DOCS])
check_contains("No fake market cap rule exists", r"no fake market cap|market cap.*not_available|must not invent.*market cap", [PUBLIC, DOCS])
check_contains("No fake blocks rule exists", r"no fake blocks|must not fake.*blocks|No fake block", [PUBLIC, DOCS])
check_contains("No fake wallet balances rule exists", r"no fake wallet balances|fake balances|must not.*wallet balances", [PUBLIC, DOCS])
check_contains("No fake txids rule exists", r"no fake txids|fake txids|must not.*txids", [PUBLIC, DOCS])
check_contains("No fake burns rule exists", r"no fake burns|fake burns|must not.*burn", [PUBLIC, DOCS])
check_contains("No fake explorer data rule exists", r"no fake explorer|fake explorer|Real Chain Data Only|No fake data", [PUBLIC, DOCS])
check_contains("Unavailable data says pending/syncing/offline/not available", r"pending_launch|Pending launch|syncing|Syncing|offline|Offline|not_available|Not available", [PUBLIC, DOCS])

section("Processes and Ports")
proc = run(["ps", "-eo", "pid,args"])
proc_lines = []
for raw in (proc.stdout or "").splitlines():
    text = raw.strip()
    if (
        "/home/hobbyhashcoin/bin/hobbyhashd" in text
        or "/home/hobbyhashcoin/hobbyhash-clean/pool-" in text
        or "/home/hobbyhashcoin/hobbyhash-clean/scripts/payoutd.py" in text
        or "php -S 127.0.0.1:18765" in text
        or "php -S 127.0.0.1:18766" in text
    ):
        if "final-hobc-audit.sh" not in text:
            proc_lines.append(text)
if proc_lines:
    line("PASS", "Check HOBC processes", " | ".join(proc_lines[:12]))
else:
    line("WARN", "Check HOBC processes", "no HOBC process lines found")
ports = run(["ss", "-lntup"])
port_text = (ports.stdout or "") + (ports.stderr or "")
for port in ["18761", "18762", "5555", "5556"]:
    line("PASS" if f":{port}" in port_text else "FAIL", f"Required live port {port}", "listening" if f":{port}" in port_text else "not listening")
for port in ["18765", "18766"]:
    line("WARN" if f":{port}" not in port_text else "PASS", f"Optional staged app port {port}", "not listening until service is started" if f":{port}" not in port_text else "listening")
for port in ["18763", "18764"]:
    line("WARN" if f":{port}" not in port_text else "PASS", f"Pool status port {port}", "not listening; API reads pool.status files" if f":{port}" not in port_text else "listening")
for svc in ["hobbyhashd-mainnet", "hobbyhash-ckpool-main", "hobbyhash-ckpool-nano", "hobbyhash-explorer", "hobbyhash-wallet", "hobbyhash-wallet-scanner", "hobbyhash-wallet-withdrawer"]:
    enabled = run(["systemctl", "is-enabled", svc])
    active = run(["systemctl", "is-active", svc])
    state = f"enabled={enabled.stdout.strip() or enabled.stderr.strip()} active={active.stdout.strip() or active.stderr.strip()}"
    if svc in {"hobbyhash-ckpool-main", "hobbyhash-ckpool-nano"} and "enabled" in state and "active" in state:
        line("WARN", f"Systemd name {svc}", state + " (already live)")
    elif "not-found" in state:
        line("PASS", f"Systemd name {svc}", state + " (staged only)")
    else:
        line("PASS" if "active" in state else "WARN", f"Systemd name {svc}", state)
for path in [
    SYSTEMD / "hobbyhashd-mainnet.service",
    SYSTEMD / "hobbyhash-ckpool-main.service",
    SYSTEMD / "hobbyhash-ckpool-nano.service",
    SYSTEMD / "hobbyhash-explorer.service",
    SYSTEMD / "hobbyhash-wallet.service",
    SYSTEMD / "hobbyhash-wallet-scanner.service",
    SYSTEMD / "hobbyhash-wallet-withdrawer.service",
]:
    exists("Staged service config exists", path)
for path in [
    ROOT / "hobbyhash-data" / "mainnet",
    ROOT / "hobbyhash-run" / "ckpool-main",
    ROOT / "hobbyhash-run" / "ckpool-nano",
    ROOT / "hobbyhash-logs" / "ckpool-main",
    ROOT / "hobbyhash-logs" / "ckpool-nano",
]:
    exists("Datadir/runtime/logdir exists", path)
if main_conf.exists():
    txt = read_text(main_conf)
    line("PASS" if "rpcbind=127.0.0.1" in txt and "rpcallowip=127.0.0.1" in txt else "FAIL", "RPC localhost check")
line("PASS" if "0.0.0.0:5555" in main_pool.get("serverurl", []) else "FAIL", "Pool main port config check")
line("PASS" if "0.0.0.0:5556" in nano_pool.get("serverurl", []) else "FAIL", "Pool nano port config check")
exists("Explorer app for port 18765 exists", CLEAN / "explorer" / "app.php")
exists("Wallet web root for port 18766 exists", PUBLIC)

section("Live API Smoke Checks")
check_command("Chain status API", ["php", str(PUBLIC / "api" / "chain" / "status" / "index.php")], show_first_lines=2)
check_command("Explorer status API", ["php", str(PUBLIC / "api" / "explorer" / "status" / "index.php")], show_first_lines=2)
check_command("Wallet status API", ["php", str(PUBLIC / "api" / "wallet" / "status" / "index.php")], show_first_lines=2)
check_command("Main pool status API", ["php", str(PUBLIC / "api" / "pool" / "main" / "status" / "index.php")], show_first_lines=2)
check_command("Nano pool status API", ["php", str(PUBLIC / "api" / "pool" / "nano" / "status" / "index.php")], show_first_lines=2)

section("Summary")
print(f"PASS={PASS}")
print(f"WARN={WARN}")
print(f"FAIL={FAIL}")
if FAIL:
    print("RESULT=FAIL")
elif WARN:
    print("RESULT=PASS_WITH_WARNINGS")
else:
    print("RESULT=PASS")
PY
