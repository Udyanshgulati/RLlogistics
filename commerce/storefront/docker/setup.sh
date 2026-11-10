#!/bin/bash
# Idempotent: safe on every container start. Installs WordPress once, then keeps themes/plugin in sync with the image.
set -uo pipefail
cd /var/www/html
log() { echo "[schermo-setup] $*"; }
wp() { command wp --allow-root --path=/var/www/html "$@"; }

# Wait for the official entrypoint to copy WordPress and write wp-config.php, then for the database.
for _ in $(seq 1 120); do [ -f wp-config.php ] && [ -f wp-includes/version.php ] && break; sleep 2; done
for _ in $(seq 1 120); do wp db check >/dev/null 2>&1 && break; sleep 2; done

log "syncing themes and WooCommerce from image"
mkdir -p wp-content/themes wp-content/plugins
rm -rf wp-content/themes/brandhouse wp-content/themes/schermo
cp -a /opt/schermo/themes/brandhouse /opt/schermo/themes/schermo wp-content/themes/
for p in woocommerce woo-razorpay; do
  if [ ! -d "wp-content/plugins/$p" ] || ! cmp -s "/opt/schermo/plugins/$p/$p.php" "wp-content/plugins/$p/$p.php"; then
    rm -rf "wp-content/plugins/$p" && cp -a "/opt/schermo/plugins/$p" wp-content/plugins/
  fi
done
# Our own plugins ship with the image and are replaced on every deploy (like the themes).
rm -rf wp-content/plugins/tl-inventory && cp -a /opt/schermo/own-plugins/tl-inventory wp-content/plugins/
chown -R www-data:www-data wp-content

: "${WP_URL:?WP_URL must be set}"
: "${WP_ADMIN_USER:?WP_ADMIN_USER must be set}"
: "${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD must be set}"
: "${WP_ADMIN_EMAIL:?WP_ADMIN_EMAIL must be set}"

if ! wp core is-installed >/dev/null 2>&1; then
  log "first run: installing WordPress at ${WP_URL}"
  wp core install --url="$WP_URL" --title="Schermo" --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email
  wp option update blogdescription ""
  wp option update timezone_string "Asia/Kolkata"
  # Staging: keep search engines out until the client approves launch.
  wp option update blog_public 0
  wp rewrite structure '/%postname%/' --hard
  wp plugin delete akismet hello >/dev/null 2>&1 || true
  wp plugin activate woocommerce
  wp theme activate schermo
  wp option update woocommerce_currency INR
  wp option update woocommerce_default_country "IN:DL"
  wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
  wp option update woocommerce_coming_soon no
  wp eval-file /opt/schermo/seed-demo.php "${SEED_DEMO:-1}"
  wp theme delete twentytwentythree twentytwentyfour twentytwentyfive >/dev/null 2>&1 || true
  log "first run complete"
else
  wp plugin is-active woocommerce || wp plugin activate woocommerce
  wp wc update >/dev/null 2>&1 || true
  [ "$(wp option get stylesheet)" = schermo ] || wp theme activate schermo
fi
log "installing page cache"
cp /opt/schermo/advanced-cache.php wp-content/advanced-cache.php
mkdir -p wp-content/mu-plugins wp-content/cache/schermo-page && cp /opt/schermo/schermo-cache.php wp-content/mu-plugins/schermo-cache.php
rm -f wp-content/cache/schermo-page/*.html
wp option update woocommerce_default_customer_address base >/dev/null 2>&1 || true
# One-time: whole-rupee prices (₹2,999 instead of ₹2,999.00).
if [ "$(wp option get schermo_migr_decimals 2>/dev/null)" != "1" ]; then wp option update woocommerce_price_num_decimals 0 >/dev/null && wp option update schermo_migr_decimals 1 >/dev/null; fi
wp plugin is-active woo-razorpay || wp plugin activate woo-razorpay
wp plugin is-active tl-inventory || wp plugin activate tl-inventory
wp eval-file /opt/schermo/store-setup.php
log "importing catalog"
wp eval-file /opt/schermo/import-catalog.php
# The client's listing sheet: loaded only when the committed file changes (replace it in the repo and push).
SHEET=/opt/schermo/catalog/client-sheets/Schermo_Product_Listing_Sheet.xlsx
if [ -f "$SHEET" ] && [ "$(sha256sum "$SHEET" | cut -d' ' -f1)" != "$(wp option get schermo_sheet_sha 2>/dev/null)" ]; then
  log "importing client sheet"
  wp eval-file /opt/schermo/import-sheet.php "$SHEET" || log "client sheet import FAILED (site keeps running; see above)"
fi
chown -R www-data:www-data wp-content/uploads 2>/dev/null || true
wp cache flush >/dev/null 2>&1 || true
chown -R www-data:www-data wp-content/cache wp-content/mu-plugins wp-content/advanced-cache.php
log "ready"
