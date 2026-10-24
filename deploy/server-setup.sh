#!/bin/sh
# One-time nginx setup for rllogistics.thodalogic.com on the Thoda Logic server.
# Run as root AFTER the site files are in /var/www/rllogistics. Safe to re-run.
set -e
DOMAIN=rllogistics.thodalogic.com
ROOT=/var/www/rllogistics

# If uploaded as the bundle (server-setup.sh next to a site/ folder), install the site first.
HERE=$(cd "$(dirname "$0")" && pwd)
if [ -f "$HERE/site/index.html" ]; then
  mkdir -p "$ROOT"
  rm -rf "$ROOT.new" && cp -r "$HERE/site" "$ROOT.new"
  rm -rf "$ROOT.old" && { [ -d "$ROOT" ] && mv "$ROOT" "$ROOT.old" || true; } && mv "$ROOT.new" "$ROOT"
  echo "✓ site files installed in $ROOT"
fi

[ -f "$ROOT/index.html" ] || { echo "✗ $ROOT/index.html not found — upload the site first"; exit 1; }
chmod -R a+rX "$ROOT"

# Debian/Ubuntu layout uses sites-available; others use conf.d.
if [ -d /etc/nginx/sites-available ]; then
  CONF=/etc/nginx/sites-available/$DOMAIN
else
  CONF=/etc/nginx/conf.d/$DOMAIN.conf
fi

cat > "$CONF" <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    root $ROOT;
    index index.html;
    location / { try_files \$uri \$uri/ \$uri.html =404; }
    location /_next/static/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files \$uri =404;
    }
    location = /opengraph-image { default_type image/png; }
    error_page 404 /404.html;
    gzip on;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;
}
NGINX

[ -d /etc/nginx/sites-enabled ] && ln -sf "$CONF" /etc/nginx/sites-enabled/$DOMAIN

# Only reload if the whole config is valid — never break the other sites.
if nginx -t; then
  (systemctl reload nginx 2>/dev/null || nginx -s reload)
  echo "✓ nginx serving $DOMAIN from $ROOT"
else
  echo "✗ nginx config test failed — removing the new site so nothing else is affected"
  rm -f "$CONF" /etc/nginx/sites-enabled/$DOMAIN
  exit 1
fi

# HTTPS via Let's Encrypt (needs the Cloudflare record on "DNS only" while issuing).
if ! command -v certbot >/dev/null 2>&1 && command -v apt-get >/dev/null 2>&1; then
  apt-get install -y certbot python3-certbot-nginx >/dev/null 2>&1 || true
fi
if command -v certbot >/dev/null 2>&1; then
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m udyanshgulati@thodalogic.com --redirect \
    && echo "✓ HTTPS ready: https://$DOMAIN" \
    || echo "! certbot failed — site still works on http://$DOMAIN (check the Cloudflare record is DNS only)"
else
  echo "! certbot not available — site works on http://$DOMAIN"
fi
