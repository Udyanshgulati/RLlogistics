#!/usr/bin/env bash
# Build the static site and upload it to the Thoda Logic server.
# Usage: SERVER=user@200.141.13.226 ./deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."
: "${SERVER:?Set SERVER, e.g. SERVER=root@200.141.13.226}"
REMOTE_DIR="${REMOTE_DIR:-/var/www/rllogistics}"

npm ci
npm run export:subdomain
rsync -avz --delete out/ "$SERVER:$REMOTE_DIR/"
echo "Deployed to https://rllogistics.thodalogic.com"
