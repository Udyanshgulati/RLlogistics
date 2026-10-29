#!/usr/bin/env bash
# Restore drill: loads a backup into a throwaway database and proves it is usable.
# Run monthly on staging (never against production). Exit code 0 = the backup restores and the ledger is consistent.
# Usage: ./restore-check.sh /path/to/commerce_hub-YYYYMMDDTHHMMSSZ.sql.gz[.age]   (AGE_IDENTITY=key.txt for encrypted files)
set -euo pipefail
src="${1:?backup file}"
target="${RESTORE_DB:-commerce_restore_check}"
[[ "$target" == commerce_restore_* ]] || { echo "RESTORE_DB must start with commerce_restore_"; exit 2; }
if [[ -f "$src.sha256" ]]; then (cd "$(dirname "$src")" && sha256sum -c "$(basename "$src").sha256" >/dev/null) || { echo "Checksum mismatch"; exit 1; }; fi
mysql -e "DROP DATABASE IF EXISTS \`$target\`; CREATE DATABASE \`$target\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if [[ "$src" == *.age ]]; then age -d -i "${AGE_IDENTITY:?}" "$src" | gunzip | mysql "$target"; else gunzip -c "$src" | mysql "$target"; fi
here="$(cd "$(dirname "$0")" && pwd)"
DB_DSN="mysql:host=localhost;dbname=$target;charset=utf8mb4" DB_USER="${DB_USER:-root}" DB_PASSWORD="${DB_PASSWORD:-}" HUB_ENV_FILE=/dev/null php "$here/../hub/bin/hub" reconcile
for t in stock_levels stock_movements reservations orders inventory_items listings users; do
  printf '%-18s %s rows\n' "$t" "$(mysql -N -e "SELECT COUNT(*) FROM \`$target\`.\`$t\`")"
done
echo "Restore check passed for $src"
