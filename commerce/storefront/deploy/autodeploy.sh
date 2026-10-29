#!/bin/bash
# Push-to-deploy for the Schermo storefront. Run every minute by schermo-autodeploy.timer.
# Pulls the branch; if it changed, rebuilds the stack, checks the site and rolls back to the previous commit on failure.
set -uo pipefail
SRC=/opt/schermo/src
BRANCH=claude/modest-ptolemy-9yf78k
ENV=/opt/schermo/schermo.env
APP=$SRC/commerce/storefront
LOG=/var/log/schermo-autodeploy.log
exec 9>/run/schermo-autodeploy.lock; flock -n 9 || exit 0
log() { echo "$(date -u +%FT%TZ) $*" >> "$LOG"; }

cd "$SRC" || exit 1
git fetch -q origin "$BRANCH" || { log "fetch failed"; exit 1; }
OLD=$(git rev-parse HEAD); NEW=$(git rev-parse FETCH_HEAD)
[ "$OLD" = "$NEW" ] && exit 0

deploy() {
  git checkout -q --detach "$1" && cd "$APP" && docker compose -p schermo --env-file "$ENV" up -d --build >> "$LOG" 2>&1 \
    && for _ in $(seq 1 60); do docker logs --since 10m schermo-wordpress-1 2>&1 | grep -q '\[schermo-setup\] ready' && break; sleep 5; done \
    && [ "$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: schermo.thodalogic.com' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8095/)" = 200 ]
}

log "deploying $NEW (was $OLD)"
if deploy "$NEW"; then
  log "live $NEW"
else
  log "FAILED $NEW, rolling back to $OLD"
  cd "$SRC" && deploy "$OLD" && log "rolled back to $OLD" || log "ROLLBACK FAILED"
  exit 1
fi
