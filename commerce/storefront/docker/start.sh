#!/bin/bash
# Runs the first-time/upgrade setup in the background, then hands over to the official WordPress entrypoint.
set -euo pipefail
/opt/schermo/setup.sh &
# WordPress scheduled tasks run here every 5 minutes instead of inside visitors' page loads (DISABLE_WP_CRON).
( sleep 120; while true; do runuser -u www-data -- wp --path=/var/www/html cron event run --due-now >/dev/null 2>&1; sleep 300; done ) &
exec docker-entrypoint.sh "$@"
