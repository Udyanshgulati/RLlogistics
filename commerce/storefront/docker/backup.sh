#!/bin/bash
# Daily backup of the store: database + uploaded files, kept for 14 days in the "backups" volume (/backups).
# Restore: docker/restore.sh <date>, e.g. docker/restore.sh 2026-11-11 (see HANDOVER.md).
set -euo pipefail
umask 077
cd /var/www/html
day=$(date -u +%F)
mkdir -p /backups
tmp=$(mktemp -d)
wp --allow-root --path=/var/www/html db export "$tmp/db.sql" >/dev/null
gzip -c "$tmp/db.sql" > "/backups/db-$day.sql.gz.part" && mv "/backups/db-$day.sql.gz.part" "/backups/db-$day.sql.gz"
tar czf "/backups/uploads-$day.tar.gz.part" -C wp-content uploads && mv "/backups/uploads-$day.tar.gz.part" "/backups/uploads-$day.tar.gz"
rm -rf "$tmp"
find /backups -name 'db-*.sql.gz' -mtime +14 -delete
find /backups -name 'uploads-*.tar.gz' -mtime +14 -delete
echo "$(date -u +%FT%TZ) backup $day ok ($(du -sh /backups | cut -f1) in /backups)" >> /backups/backup.log
