#!/bin/bash
# One-time setup on the VPS (run as root): brings /opt/schermo/src up to date, deploys it, and installs the
# push-to-deploy timer. After this, every push to the branch goes live on its own (deploy/autodeploy.sh).
set -euo pipefail
SRC=/opt/schermo/src
BRANCH=claude/modest-ptolemy-9yf78k
cd "$SRC"
if [ -n "$(git status --porcelain)" ]; then
  git diff > "/root/schermo-local-changes-$(date +%F-%H%M).patch" || true
  git stash push -u -q -m "before autodeploy install"
  echo "Local server-only changes saved (git stash + /root/schermo-local-changes-*.patch)."
fi
git fetch -q origin "$BRANCH"
git checkout -q --detach FETCH_HEAD
cp commerce/storefront/deploy/schermo-autodeploy.service commerce/storefront/deploy/schermo-autodeploy.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now schermo-autodeploy.timer
echo "Deploying $(git log -1 --oneline) …"
cd commerce/storefront
docker compose -p schermo --env-file /opt/schermo/schermo.env up -d --build
for _ in $(seq 1 90); do docker logs --since 15m schermo-wordpress-1 2>&1 | grep -q '\[schermo-setup\] ready' && break; sleep 5; done
code=$(curl -s -o /dev/null -w '%{http_code}' https://schermo.thodalogic.com/)
echo "Site answered HTTP $code. Push-to-deploy is on: $(systemctl is-active schermo-autodeploy.timer)."
