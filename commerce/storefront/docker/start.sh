#!/bin/bash
# Runs the first-time/upgrade setup in the background, then hands over to the official WordPress entrypoint.
set -euo pipefail
/opt/schermo/setup.sh &
# WordPress scheduled tasks run here every 5 minutes instead of inside visitors' page loads (DISABLE_WP_CRON).
( sleep 120; while true; do runuser -u www-data -- wp --path=/var/www/html cron event run --due-now >/dev/null 2>&1; sleep 300; done ) &
# Daily backup (database + uploads) into the backups volume, kept 14 days; the first one runs ~10 minutes after start.
( sleep 600; while true; do [ -f "/backups/db-$(date -u +%F).sql.gz" ] || /opt/schermo/backup.sh >>/backups/backup.log 2>&1 || true; sleep 3600; done ) &
exec docker-entrypoint.sh "$@"
