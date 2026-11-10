#!/bin/bash
# Restores a daily backup INTO THE RUNNING SITE (replaces the database and uploads). Usage inside the container:
#   docker exec -it schermo-wordpress-1 /opt/schermo/restore.sh 2026-11-11
set -euo pipefail
day=${1:?usage: restore.sh YYYY-MM-DD}
db=/backups/db-$day.sql.gz; up=/backups/uploads-$day.tar.gz
[ -f "$db" ] && [ -f "$up" ] || { echo "No backup for $day. Available:"; ls /backups; exit 1; }
read -r -p "Replace the live database and uploads with the backup from $day? Type YES: " ok
[ "$ok" = "YES" ] || exit 1
cd /var/www/html
gunzip -c "$db" | wp --allow-root --path=/var/www/html db import -
rm -rf wp-content/uploads && tar xzf "$up" -C wp-content && chown -R www-data:www-data wp-content/uploads
rm -f wp-content/cache/schermo-page/*.html
echo "Restored $day."
