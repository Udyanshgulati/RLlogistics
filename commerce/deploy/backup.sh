#!/usr/bin/env bash
# Nightly logical backup of the hub database, compressed and encrypted, then copied off the server.
# Usage: DB_NAME=commerce_hub BACKUP_DIR=/var/backups/hub AGE_RECIPIENT=age1... RCLONE_REMOTE=offsite:hub-backups ./backup.sh
# - Encryption uses `age` with a public recipient key; the private key is NOT kept on the server.
# - Off-site copy uses rclone to any S3-compatible bucket (e.g. Backblaze B2). A backup that never leaves
#   the server is not a backup. Point-in-time recovery additionally needs MariaDB binary logs shipped the same way.
set -euo pipefail
: "${DB_NAME:?}" "${BACKUP_DIR:?}"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"
file="$BACKUP_DIR/${DB_NAME}-${stamp}.sql.gz"
# --single-transaction: consistent InnoDB snapshot without locking checkouts. Triggers and routines included.
mariadb-dump --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 "$DB_NAME" | gzip -9 > "$file"
if [[ -n "${AGE_RECIPIENT:-}" ]]; then
  age -r "$AGE_RECIPIENT" -o "$file.age" "$file" && rm -f "$file" && file="$file.age"
fi
sha256sum "$file" > "$file.sha256"
if [[ -n "${RCLONE_REMOTE:-}" ]]; then
  rclone copy "$file" "$RCLONE_REMOTE/" && rclone copy "$file.sha256" "$RCLONE_REMOTE/"
fi
# Keep 14 local copies; the off-site bucket's lifecycle rule keeps 30 days (configure it there).
ls -1t "$BACKUP_DIR"/"$DB_NAME"-*.sql.gz* 2>/dev/null | grep -v '\.sha256$' | tail -n +15 | while read -r old; do rm -f "$old" "$old.sha256"; done
echo "$file"
