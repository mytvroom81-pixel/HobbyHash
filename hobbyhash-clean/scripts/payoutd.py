#!/usr/bin/env python3
import argparse
import json
import re
import subprocess
import sys
import time
from decimal import Decimal, ROUND_DOWN
from pathlib import Path
from typing import Any, Dict, List, Optional, Set, Tuple


SOLVE_RE = re.compile(r"Solved and confirmed block (\d+) by (\S+)")


def now_ts() -> int:
    return int(time.time())


def split_worker(workername: str) -> str:
    if "." in workername:
        return workername.split(".", 1)[0]
    return workername


def decimal_from(value: Any) -> Decimal:
    return Decimal(str(value))


class PayoutDaemon:
    def __init__(
        self,
        cfg: Dict[str, Any],
        dry_run: bool = False,
        verbose: bool = False,
        backfill_sharelogs: bool = False,
    ):
        self.cfg = cfg
        self.dry_run = dry_run
        self.verbose = verbose
        self.backfill_sharelogs = backfill_sharelogs
        self.state_path = Path(cfg["state_file"])
        self.log_path = Path(cfg["pool_log_file"])
        sharelog_dir = cfg.get("sharelog_dir", "")
        self.sharelog_dir = Path(sharelog_dir) if sharelog_dir else None
        self.cli_path = cfg["hobbyhash_cli"]
        self.node_conf = cfg["node_conf"]
        self.wallet = cfg.get("wallet", "")
        self.treasury_address = cfg["treasury_address"]
        self.confirmations_required = int(cfg.get("confirmations_required", 100))
        self.fee_percent = decimal_from(cfg.get("pool_fee_percent", "0.0"))
        self.poll_seconds = int(cfg.get("poll_seconds", 15))
        self.min_payout = decimal_from(cfg.get("min_payout_amount", "0.00000001"))
        self.max_payout = decimal_from(cfg.get("max_payout_amount", "21000000"))
        self._locked_non_treasury: List[Dict[str, Any]] = []
        self.state = self._load_state()

    def _log(self, msg: str) -> None:
        print(f"[payoutd] {msg}", flush=True)

    def _debug(self, msg: str) -> None:
        if self.verbose:
            self._log(msg)

    def _cli(self, method: str, *params: Any, wallet: Optional[bool] = None) -> Any:
        cmd: List[str] = [self.cli_path, f"-conf={self.node_conf}"]
        use_wallet = self.wallet and (wallet if wallet is not None else True)
        if use_wallet:
            cmd.append(f"-rpcwallet={self.wallet}")
        cmd.append(method)
        for p in params:
            if isinstance(p, bool):
                cmd.append("true" if p else "false")
            elif isinstance(p, (dict, list)):
                cmd.append(json.dumps(p))
            else:
                cmd.append(str(p))
        self._debug("RPC " + " ".join(cmd))
        proc = subprocess.run(cmd, capture_output=True, text=True)
        if proc.returncode != 0:
            raise RuntimeError(proc.stderr.strip() or proc.stdout.strip() or f"{method} failed")
        out = proc.stdout.strip()
        if out == "":
            return None
        try:
            return json.loads(out)
        except json.JSONDecodeError:
            return out

    def _load_state(self) -> Dict[str, Any]:
        if not self.state_path.exists():
            return {
                "version": 2,
                "last_log_offset": 0,
                "sharelog_scanned_files": [],
                "candidates": [],
                "paid": [],
                "last_run": 0,
            }
        with self.state_path.open("r", encoding="utf-8") as f:
            data = json.load(f)
        data.setdefault("version", 2)
        data.setdefault("last_log_offset", 0)
        data.setdefault("sharelog_scanned_files", [])
        data.setdefault("candidates", [])
        data.setdefault("paid", [])
        data.setdefault("last_run", 0)
        return data

    def _save_state(self) -> None:
        self.state["last_run"] = now_ts()
        self.state_path.parent.mkdir(parents=True, exist_ok=True)
        tmp = self.state_path.with_suffix(".tmp")
        with tmp.open("w", encoding="utf-8") as f:
            json.dump(self.state, f, indent=2, sort_keys=True)
        tmp.replace(self.state_path)

    def _candidate_keys(self) -> Set[Tuple[int, str]]:
        return {(int(c["height"]), c["workername"]) for c in self.state["candidates"]}

    def _add_candidate(self, height: int, worker: str, source: str) -> bool:
        key = (height, worker)
        if key in self._candidate_keys():
            return False
        winner_address = split_worker(worker)
        self.state["candidates"].append(
            {
                "height": height,
                "workername": worker,
                "winner_address": winner_address,
                "seen_at": now_ts(),
                "status": "pending",
                "source": source,
            }
        )
        self._log(f"Detected solved block height={height} worker={worker} ({source})")
        return True

    def _address_is_valid(self, address: str) -> bool:
        try:
            info = self._cli("validateaddress", address, wallet=False)
            return bool(info.get("isvalid", False))
        except Exception:
            return False

    def _scan_ckpool_log(self) -> None:
        if not self.log_path.exists():
            self._debug(f"Pool log missing: {self.log_path}")
            return

        file_size = self.log_path.stat().st_size
        offset = int(self.state.get("last_log_offset", 0))
        if offset > file_size:
            offset = 0

        existing = self._candidate_keys()
        with self.log_path.open("r", encoding="utf-8", errors="replace") as f:
            f.seek(offset)
            for line in f:
                m = SOLVE_RE.search(line)
                if not m:
                    continue
                height = int(m.group(1))
                worker = m.group(2)
                if (height, worker) in existing:
                    continue
                self._add_candidate(height, worker, "pool_log")
                existing.add((height, worker))
            self.state["last_log_offset"] = f.tell()

    def _looks_like_block_hash(self, value: str) -> bool:
        return (
            isinstance(value, str)
            and len(value) == 64
            and value.startswith("0000000")
            and all(ch in "0123456789abcdef" for ch in value)
        )

    def _sharelog_candidate_from_row(
        self,
        row: Dict[str, Any],
        block_cache: Dict[str, Optional[Dict[str, Any]]],
    ) -> Optional[Tuple[int, str, str]]:
        if not row.get("result"):
            return None
        blockhash = row.get("hash")
        if not self._looks_like_block_hash(blockhash):
            return None
        worker = row.get("workername") or row.get("username")
        if not worker or not isinstance(worker, str):
            return None
        if blockhash not in block_cache:
            try:
                block_cache[blockhash] = self._cli("getblock", blockhash, "2", wallet=False)
            except Exception:
                block_cache[blockhash] = None
        block = block_cache[blockhash]
        if not block or not self._coinbase_pays_treasury(block):
            return None
        height = int(block["height"])
        return height, worker, blockhash

    def _scan_sharelogs(self) -> None:
        if not self.sharelog_dir or not self.sharelog_dir.exists():
            self._debug(f"Sharelog dir missing: {self.sharelog_dir}")
            return

        scanned = set(self.state.get("sharelog_scanned_files", []))
        block_cache: Dict[str, Optional[Dict[str, Any]]] = {}
        files = sorted(self.sharelog_dir.rglob("*.sharelog"), key=lambda p: p.stat().st_mtime)
        for path in files:
            rel = str(path.relative_to(self.sharelog_dir))
            if not self.backfill_sharelogs and rel in scanned:
                continue
            added = 0
            try:
                content = path.read_text(encoding="utf-8", errors="replace")
            except OSError as exc:
                self._log(f"Sharelog read failed {rel}: {exc}")
                continue
            for line in content.splitlines():
                line = line.strip()
                if not line.startswith("{"):
                    continue
                try:
                    row = json.loads(line)
                except json.JSONDecodeError:
                    continue
                parsed = self._sharelog_candidate_from_row(row, block_cache)
                if not parsed:
                    continue
                height, worker, _blockhash = parsed
                if self._add_candidate(height, worker, f"sharelog:{rel}"):
                    added += 1
            scanned.add(rel)
            if added:
                self._log(f"Sharelog {rel}: added {added} candidate(s)")
        self.state["sharelog_scanned_files"] = sorted(scanned)

    def _is_paid(self, blockhash: str, workername: str) -> bool:
        for p in self.state["paid"]:
            if p["blockhash"] == blockhash and p["workername"] == workername:
                return True
        return False

    def _treasury_coinbase_reward(self, block: Dict[str, Any]) -> Decimal:
        """Coinbase value paid to the pool treasury (excludes OP_RETURN / zero vouts)."""
        txs = block.get("tx", [])
        if not txs:
            raise RuntimeError("block has no transactions")
        coinbase_tx = txs[0]
        total = Decimal("0")
        for vout in coinbase_tx.get("vout", []):
            spk = vout.get("scriptPubKey", {})
            addr = spk.get("address")
            addresses = spk.get("addresses") or []
            if addr == self.treasury_address or self.treasury_address in addresses:
                total += decimal_from(vout.get("value", "0"))
        if total <= 0:
            raise RuntimeError("treasury coinbase output missing")
        return total

    def _coinbase_pays_treasury(self, block: Dict[str, Any]) -> bool:
        txs = block.get("tx", [])
        if not txs:
            return False
        coinbase_tx = txs[0]
        for vout in coinbase_tx.get("vout", []):
            spk = vout.get("scriptPubKey", {})
            addr = spk.get("address")
            if addr == self.treasury_address:
                return True
            addresses = spk.get("addresses") or []
            if self.treasury_address in addresses:
                return True
        return False

    def _compute_payout(self, reward: Decimal) -> Decimal:
        # Whole HOBC block subsidy to miner; sub-unit coinbase dust remains at treasury.
        base = reward.quantize(Decimal("1"), rounding=ROUND_DOWN)
        fee = (base * self.fee_percent) / Decimal("100")
        payout = base - fee
        return payout.quantize(Decimal("0.00000001"), rounding=ROUND_DOWN)

    def _treasury_unspent(self) -> List[Dict[str, Any]]:
        # Coinbase treasury outputs may be immature or marked unsafe; include both.
        return self._cli(
            "listunspent",
            1,
            9999999,
            [self.treasury_address],
            True,
            {
                "minimumAmount": str(self.min_payout),
                "include_immature_coinbase": True,
            },
        )

    def _coinbase_utxo_for_height(self, height: int) -> Optional[Dict[str, Any]]:
        try:
            blockhash = self._cli("getblockhash", str(height), wallet=False)
            block = self._cli("getblock", blockhash, "2", wallet=False)
        except Exception:
            return None
        if not self._coinbase_pays_treasury(block):
            return None
        coinbase_txid = block["tx"][0]["txid"]
        for vout_index, vout in enumerate(block["tx"][0]["vout"]):
            spk = vout.get("scriptPubKey", {})
            addr = spk.get("address")
            addresses = spk.get("addresses") or []
            if addr == self.treasury_address or self.treasury_address in addresses:
                txout = self._cli("gettxout", coinbase_txid, str(vout_index), wallet=False)
                if txout is None:
                    return None
                return {
                    "txid": coinbase_txid,
                    "vout": vout_index,
                    "amount": decimal_from(vout["value"]),
                }
        return None

    def _lock_non_treasury_outputs(self) -> None:
        if self._locked_non_treasury:
            return
        all_unspent = self._cli("listunspent", 1, 9999999, [], False)
        to_lock = []
        for utxo in all_unspent:
            if utxo.get("address") == self.treasury_address:
                continue
            entry = {"txid": utxo["txid"], "vout": int(utxo["vout"])}
            to_lock.append(entry)
        if not to_lock:
            return
        if self.dry_run:
            self._log(f"DRY RUN lock {len(to_lock)} non-treasury UTXO(s)")
            return
        self._cli("lockunspent", False, to_lock)
        self._locked_non_treasury = to_lock
        self._debug(f"Locked {len(to_lock)} non-treasury UTXO(s)")

    def _unlock_non_treasury_outputs(self) -> None:
        if not self._locked_non_treasury:
            return
        if self.dry_run:
            self._locked_non_treasury = []
            return
        try:
            self._cli("lockunspent", True, self._locked_non_treasury)
        finally:
            self._locked_non_treasury = []

    def _treasury_balance(self) -> Decimal:
        total = Decimal("0")
        for utxo in self._treasury_unspent():
            total += decimal_from(utxo.get("amount", "0"))
        return total

    def _send_payout(
        self,
        address: str,
        amount: Decimal,
        comment: str,
        coinbase_utxo: Optional[Dict[str, Any]] = None,
    ) -> str:
        amount_str = f"{amount:.8f}"
        if self.dry_run:
            self._log(
                f"DRY RUN payout {address} {amount_str} '{comment}' "
                f"utxo={coinbase_utxo}"
            )
            return "dry-run-txid"

        self._lock_non_treasury_outputs()
        try:
            if coinbase_utxo:
                inputs = [
                    {
                        "txid": coinbase_utxo["txid"],
                        "vout": int(coinbase_utxo["vout"]),
                    }
                ]
                outputs = [
                    {
                        address: float(amount_str),
                    }
                ]
                raw = self._cli("createrawtransaction", inputs, outputs)
                funded = self._cli(
                    "fundrawtransaction",
                    raw,
                    {
                        "add_inputs": True,
                        "changeAddress": self.treasury_address,
                        "lockUnspents": True,
                    },
                )
                signed = self._cli(
                    "signrawtransactionwithwallet",
                    funded["hex"],
                )
                if not signed.get("complete"):
                    raise RuntimeError(f"signing failed: {signed.get('errors')}")
                txid = self._cli(
                    "sendrawtransaction",
                    signed["hex"],
                )
            else:
                txid = self._cli(
                    "sendtoaddress",
                    address,
                    amount_str,
                    comment,
                    "",
                    False,
                    True,
                )
        finally:
            self._unlock_non_treasury_outputs()
        if not isinstance(txid, str):
            raise RuntimeError("unexpected payout broadcast response")
        return txid

    def _process_candidate(self, c: Dict[str, Any]) -> None:
        height = int(c["height"])
        workername = c["workername"]
        winner = c["winner_address"]

        if not self._address_is_valid(winner):
            c["status"] = "invalid_winner_address"
            c["last_error"] = f"winner address invalid: {winner}"
            self._log(f"Skipping height={height}, invalid winner address {winner}")
            return

        try:
            blockhash = self._cli("getblockhash", str(height), wallet=False)
        except Exception as e:
            c["status"] = "waiting_blockhash"
            c["last_error"] = str(e)
            return

        if not isinstance(blockhash, str):
            c["status"] = "waiting_blockhash"
            c["last_error"] = "getblockhash response not string"
            return

        if self._is_paid(blockhash, workername):
            c["status"] = "paid"
            if "payout_txid" not in c:
                for p in self.state["paid"]:
                    if p["blockhash"] == blockhash and p["workername"] == workername:
                        c["payout_txid"] = p.get("payout_txid")
                        break
            return

        block = self._cli("getblock", blockhash, "2", wallet=False)
        conf = int(block.get("confirmations", 0))
        if conf < self.confirmations_required:
            c["status"] = "waiting_maturity"
            c["confirmations"] = conf
            c.pop("last_error", None)
            return

        if not self._coinbase_pays_treasury(block):
            c["status"] = "treasury_mismatch"
            c["last_error"] = "coinbase does not pay configured treasury"
            self._log(f"Skipping height={height}, treasury address mismatch")
            return

        reward = self._treasury_coinbase_reward(block)
        payout = self._compute_payout(reward)
        if payout < self.min_payout or payout > self.max_payout:
            c["status"] = "payout_out_of_bounds"
            c["last_error"] = f"payout {payout} not in bounds"
            return

        coinbase_utxo = self._coinbase_utxo_for_height(height)
        if coinbase_utxo is None:
            if self._recover_spent_coinbase_payout(c, blockhash, winner, payout):
                return
            c["status"] = "coinbase_spent"
            c["last_error"] = "treasury coinbase output already spent or missing"
            return
        if coinbase_utxo["amount"] < payout:
            c["status"] = "payout_out_of_bounds"
            c["last_error"] = (
                f"coinbase {coinbase_utxo['amount']} < payout {payout}"
            )
            return

        comment = f"HOBC solo payout h={height} worker={workername}"
        try:
            txid = self._send_payout(winner, payout, comment, coinbase_utxo)
        except Exception as e:
            c["status"] = "payout_failed"
            c["last_error"] = str(e)
            self._log(f"Payout failed height={height} worker={workername}: {e}")
            return

        paid_entry = {
            "height": height,
            "blockhash": blockhash,
            "workername": workername,
            "winner_address": winner,
            "coinbase_reward": f"{reward:.8f}",
            "payout_amount": f"{payout:.8f}",
            "fee_percent": f"{self.fee_percent}",
            "payout_txid": txid,
            "paid_at": now_ts(),
            "dry_run": self.dry_run,
        }
        self.state["paid"].append(paid_entry)
        c["status"] = "paid"
        c["confirmations"] = conf
        c["payout_txid"] = txid
        c.pop("last_error", None)
        self._log(
            f"Payout height={height} worker={workername} address={winner} "
            f"amount={payout:.8f} txid={txid}"
        )

    def _recover_spent_coinbase_payout(
        self,
        c: Dict[str, Any],
        blockhash: str,
        winner: str,
        payout: Decimal,
    ) -> bool:
        """If a prior bundled payout already spent this block's coinbase to the winner, mark paid."""
        try:
            block = self._cli("getblock", blockhash, "2", wallet=False)
            coinbase_txid = block["tx"][0]["txid"]
            vout_index = 0
            for idx, vout in enumerate(block["tx"][0]["vout"]):
                spk = vout.get("scriptPubKey", {})
                addr = spk.get("address")
                addresses = spk.get("addresses") or []
                if addr == self.treasury_address or self.treasury_address in addresses:
                    vout_index = idx
                    break
            # Wallet may have thousands of backfill payouts; scan enough history.
            spends = self._cli(
                "listtransactions",
                "*",
                15000,
                0,
                True,
            )
            send_txids: Set[str] = set()
            for entry in spends:
                if entry.get("category") != "send":
                    continue
                txid = entry.get("txid")
                if txid:
                    send_txids.add(txid)
            for txid in send_txids:
                try:
                    tx = self._cli("getrawtransaction", txid, "true", wallet=False)
                except Exception:
                    continue
                used_coinbase = any(
                    vin.get("txid") == coinbase_txid and int(vin.get("vout", -1)) == vout_index
                    for vin in tx.get("vin", [])
                )
                if not used_coinbase:
                    continue
                paid_winner = False
                for vout in tx.get("vout", []):
                    spk = vout.get("scriptPubKey", {})
                    addr = spk.get("address")
                    if addr == winner and decimal_from(vout.get("value", 0)) >= payout:
                        paid_winner = True
                        break
                if not paid_winner:
                    continue
                height = int(c["height"])
                workername = c["workername"]
                self.state["paid"].append(
                    {
                        "height": height,
                        "blockhash": blockhash,
                        "workername": workername,
                        "winner_address": winner,
                        "payout_amount": f"{payout:.8f}",
                        "payout_txid": txid,
                        "paid_at": now_ts(),
                        "recovered_spent_coinbase": True,
                    }
                )
                c["status"] = "paid"
                c["payout_txid"] = txid
                c.pop("last_error", None)
                self._log(
                    f"Recovered spent-coinbase payout height={height} worker={workername} "
                    f"txid={txid}"
                )
                return True
        except Exception as exc:
            self._debug(f"spent-coinbase recovery failed height={c.get('height')}: {exc}")
        return False

    def _recover_paid_from_log(self, log_path: Path) -> int:
        if not log_path.exists():
            return 0
        paid_re = re.compile(
            r"Payout height=(\d+) worker=(\S+) address=(\S+) amount=([0-9.]+) txid=(\S+)"
        )
        recovered = 0
        paid_keys = {
            (int(p["height"]), p["workername"])
            for p in self.state.get("paid", [])
        }
        for line in log_path.read_text(encoding="utf-8", errors="replace").splitlines():
            m = paid_re.search(line)
            if not m:
                continue
            height = int(m.group(1))
            worker = m.group(2)
            winner = m.group(3)
            amount = m.group(4)
            txid = m.group(5)
            key = (height, worker)
            if key in paid_keys:
                continue
            blockhash = self._cli("getblockhash", str(height), wallet=False)
            self.state["paid"].append(
                {
                    "height": height,
                    "blockhash": blockhash,
                    "workername": worker,
                    "winner_address": winner,
                    "payout_amount": amount,
                    "payout_txid": txid,
                    "paid_at": now_ts(),
                    "recovered_from_log": True,
                }
            )
            paid_keys.add(key)
            recovered += 1
            for c in self.state["candidates"]:
                if int(c["height"]) == height and c["workername"] == worker:
                    c["status"] = "paid"
                    c["payout_txid"] = txid
                    c.pop("last_error", None)
        return recovered

    def cycle(self) -> None:
        self._scan_ckpool_log()
        self._scan_sharelogs()
        recover_log = self.cfg.get("recover_log_file", "")
        if recover_log:
            recovered = self._recover_paid_from_log(Path(recover_log))
            if recovered:
                self._log(f"Recovered {recovered} paid payout(s) from log")
        pending = [
            c for c in self.state["candidates"] if c.get("status") != "paid"
        ]
        pending.sort(key=lambda c: int(c["height"]))
        for c in pending:
            self._process_candidate(c)
        self._save_state()

    def run_forever(self) -> None:
        self._log("Starting payout daemon loop")
        while True:
            try:
                self.cycle()
            except Exception as e:
                self._log(f"Cycle error: {e}")
                self._save_state()
            time.sleep(self.poll_seconds)


def run_self_test() -> int:
    cases = [
        ("hobc1abc.worker1", "hobc1abc"),
        ("hobc1abc", "hobc1abc"),
        ("HVGFZctR2yb548KhVw2dmQMECWEvmKJp1C.rigx", "HVGFZctR2yb548KhVw2dmQMECWEvmKJp1C"),
    ]
    for worker, expected in cases:
        got = split_worker(worker)
        if got != expected:
            print(f"self-test failed: {worker} -> {got}, expected {expected}")
            return 1
    line = "[2026-01-01] Solved and confirmed block 42 by hobc1abc.worker9"
    m = SOLVE_RE.search(line)
    if not m:
        print("self-test failed: regex did not match solve line")
        return 1
    if int(m.group(1)) != 42 or m.group(2) != "hobc1abc.worker9":
        print("self-test failed: regex groups mismatch")
        return 1
    daemon = PayoutDaemon.__new__(PayoutDaemon)
    assert daemon._looks_like_block_hash(
        "00000000002209333b204f54267f2ca9986a3af5ad7e11f33342a41a03742cf4"
    )
    print("self-test passed")
    return 0


def load_config(path: str) -> Dict[str, Any]:
    with open(path, "r", encoding="utf-8") as f:
        cfg = json.load(f)
    required = [
        "hobbyhash_cli",
        "node_conf",
        "wallet",
        "pool_log_file",
        "state_file",
        "treasury_address",
    ]
    missing = [k for k in required if not cfg.get(k)]
    if missing:
        raise RuntimeError(f"missing config keys: {', '.join(missing)}")
    return cfg


def main() -> int:
    ap = argparse.ArgumentParser(description="HOBC solo pool automated payout daemon")
    ap.add_argument(
        "--config",
        required=False,
        default="/home/hobbyhashcoin/hobbyhash-conf/hobbyhash-payout-main.json",
    )
    ap.add_argument("--once", action="store_true", help="run one cycle and exit")
    ap.add_argument("--dry-run", action="store_true", help="do not broadcast payouts")
    ap.add_argument(
        "--backfill-sharelogs",
        action="store_true",
        help="rescan all sharelog files (not only new ones)",
    )
    ap.add_argument("--verbose", action="store_true")
    ap.add_argument("--self-test", action="store_true")
    args = ap.parse_args()

    if args.self_test:
        return run_self_test()

    cfg = load_config(args.config)
    daemon = PayoutDaemon(
        cfg,
        dry_run=args.dry_run,
        verbose=args.verbose,
        backfill_sharelogs=args.backfill_sharelogs,
    )
    if args.once:
        daemon.cycle()
        return 0
    daemon.run_forever()
    return 0


if __name__ == "__main__":
    sys.exit(main())
