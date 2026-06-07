#!/usr/bin/env bash
# Scan staged/untracked repo files for common secret patterns before push.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PATTERNS=(
  'AIzaSy[A-Za-z0-9_-]{20,}'
  'sk-[A-Za-z0-9]{20,}'
  'AC[a-f0-9]{32}'
  'SK[a-f0-9]{32}'
  '-----BEGIN (RSA |EC )?PRIVATE KEY-----'
  'rpcpassword='
  'rpcpassword\s*='
  'MYSQL_PASSWORD=[^[:space:]]+'
  'api_key_secret\s*=\s*[^x[:space:]]{8,}'
)

IGNORE_GLOBS=(
  ':!**/.env'
  ':!**/.env.*'
  ':!**/config.php'
  ':!**/internal-token'
  ':!**/vendor/**'
  ':!**/node_modules/**'
  ':!**/*.log'
)

if ! git rev-parse --git-dir >/dev/null 2>&1; then
  echo "Not a git repository."
  exit 1
fi

FILES=$(git ls-files --cached --others --exclude-standard "${IGNORE_GLOBS[@]}" 2>/dev/null || true)
if [[ -z "$FILES" ]]; then
  echo "No tracked/untracked source files to scan."
  exit 0
fi

FOUND=0
while IFS= read -r file; do
  [[ -f "$file" ]] || continue
  [[ "$file" == "scripts/check-secrets.sh" ]] && continue
  for pat in "${PATTERNS[@]}"; do
    if grep -qE "$pat" "$file" 2>/dev/null; then
      # Ignore obvious documentation placeholders and upstream example configs
      if grep -qE 'CHANGE_THIS|change_this|your_.*_password|replace-with|xxxxxxxx|#.*rpcpassword|password=.*CHANGE|rpcauth=' "$file" 2>/dev/null; then
        continue
      fi
      # Source/tests document the rpcpassword flag name, not live secrets
      if [[ "$file" == *.cpp ]] || [[ "$file" == *.c ]] || [[ "$file" == *.h ]]; then
        continue
      fi
      if [[ "$file" == *"/test/"* ]] || [[ "$file" == *"/test_framework/"* ]]; then
        continue
      fi
      # Skip compiled binaries and man pages that document CLI flags
      if [[ "$file" == *"/doc/man/"* ]] || [[ "$file" == *"/share/examples/"* ]] || [[ "$file" == *"/contrib/"* ]]; then
        continue
      fi
      if [[ "$file" == hobbyhash-clean/src/src/hobbyhash* ]] && [[ "$file" != *.* ]]; then
        continue
      fi
      echo "POSSIBLE SECRET: $file matches /$pat/"
      FOUND=1
    fi
  done
done <<< "$FILES"

if [[ "$FOUND" -eq 1 ]]; then
  echo ""
  echo "Fix or gitignore these files before pushing to GitHub."
  exit 1
fi

echo "Secret scan passed."
