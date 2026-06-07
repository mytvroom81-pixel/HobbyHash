#!/usr/bin/env bash
# Manage the HobbyHash social bot systemd service.
# System unit (boot): /etc/systemd/system/hobbyhash-social-bot.service
# User unit (no sudo): ~/.config/systemd/user/hobbyhash-social-bot.service

set -euo pipefail

SYSTEM_UNIT="hobbyhash-social-bot.service"
USER_UNIT="hobbyhash-social-bot.service"

usage() {
  cat <<'EOF'
Usage: service-ctl.sh <command> [system|user]

Commands:
  status    Show service and /health status
  start     Start the bot
  stop      Stop the bot
  restart   Restart the bot
  enable    Enable on boot
  logs      Tail recent journal/log output

Target (default: auto):
  system    Use /etc/systemd/system (requires sudo for start/stop)
  user      Use ~/.config/systemd/user (no sudo)

Auto picks system unit when active or when sudo works; otherwise user unit.

One-time setup for system boot (run as root):
  sudo cp /home/hobbyhashcoin/social-bot/systemd/hobbyhash-social-bot.service /etc/systemd/system/
  sudo systemctl daemon-reload
  sudo systemctl enable --now hobbyhash-social-bot
  sudo systemctl disable --now hobbyhash-social-bot.service  # disable user unit if duplicated
EOF
}

pick_target() {
  local arg="${1:-auto}"
  case "$arg" in
    system) echo system; return ;;
    user) echo user; return ;;
    auto)
      if systemctl is-active --quiet "$SYSTEM_UNIT" 2>/dev/null; then
        echo system
        return
      fi
      if systemctl --user is-active --quiet "$USER_UNIT" 2>/dev/null; then
        echo user
        return
      fi
      if sudo -n systemctl is-active --quiet "$SYSTEM_UNIT" 2>/dev/null; then
        echo system
        return
      fi
      echo user
      ;;
    *) echo "Unknown target: $arg" >&2; exit 1 ;;
  esac
}

ctl() {
  local action="$1"
  local target="$2"
  if [[ "$target" == system ]]; then
    sudo systemctl "$action" "$SYSTEM_UNIT"
  else
    systemctl --user "$action" "$USER_UNIT"
  fi
}

cmd="${1:-status}"
target="$(pick_target "${2:-auto}")"

case "$cmd" in
  status)
    echo "=== Target: $target ==="
    if [[ "$target" == system ]]; then
      systemctl status "$SYSTEM_UNIT" --no-pager 2>/dev/null || sudo systemctl status "$SYSTEM_UNIT" --no-pager || true
    else
      systemctl --user status "$USER_UNIT" --no-pager || true
    fi
    echo
    curl -sf http://127.0.0.1:3847/health && echo || echo "Health: unreachable"
    ;;
  start|stop|restart|enable)
    ctl "$cmd" "$target"
    ;;
  logs)
    if [[ "$target" == system ]]; then
      journalctl -u "$SYSTEM_UNIT" -n 40 --no-pager 2>/dev/null || sudo journalctl -u "$SYSTEM_UNIT" -n 40 --no-pager
    else
      journalctl --user -u "$USER_UNIT" -n 40 --no-pager 2>/dev/null || tail -40 /home/hobbyhashcoin/social-bot/logs/service.log
    fi
    ;;
  -h|--help|help)
    usage
    ;;
  *)
    usage
    exit 1
    ;;
esac
