> Superseded by `/HANDOVER.md` at the repo root — read that first.

# Storefront handoff (Schermo)

Everything below is committed on branch `claude/modest-ptolemy-9yf78k`. From here on, one session (the one running on
the VPS at /opt/schermo) owns this code and the deployment. Pull before changing anything.

## Live
- https://schermo.thodalogic.com — Docker stack `schermo` (compose in this folder), WordPress on 127.0.0.1:8095,
  host nginx site `/etc/nginx/sites-available/schermo.thodalogic.com`, certificate via certbot. Secrets: /opt/schermo/schermo.env.
- Push-to-deploy (INSTALLED, working): pushing to the branch is enough. `deploy/autodeploy.sh` runs every minute
  (systemd timer `schermo-autodeploy.timer`), rebuilds when the branch moves, checks the site and rolls back on failure.
  Log: /var/log/schermo-autodeploy.log. Install once on the VPS:
  `git -C /opt/schermo/src checkout -q --detach origin/claude/modest-ptolemy-9yf78k && cp /opt/schermo/src/commerce/storefront/deploy/schermo-autodeploy.{service,timer} /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now schermo-autodeploy.timer`
- Manual deploy: `git -C /opt/schermo/src pull && cd /opt/schermo/src/commerce/storefront && docker compose -p schermo --env-file /opt/schermo/schermo.env up -d --build`.
  Themes are re-synced from the image on every start (docker/setup.sh), so edits made inside the container are lost on redeploy:
  change the repo, then deploy.

## Built (all in the repo)
- Themes: `themes/brandhouse` (shared parent) + `themes/schermo` (child). Logo SVGs extracted from Schermo_Logo.ai.
- Country/currency popup on first visit (IP geolocation), live rates (open.er-api.com, 12 h cache, INR fallback after 48 h),
  checkout always INR (`inc/markets.php`).
- Product page: sticky main photo + scrolling photos, phone swipe with counter, Buy now (`woocommerce/single-product/product-image.php`).
- Notify me (back-in-stock emails, Products → Stock alerts), free-shipping bar (uses the Free shipping method's minimum;
  hidden until one is set), drop countdown (Customize → Drop countdown; hidden until a future date is set) — `inc/extras.php`.
- Icon set designed in Stitch (project "Schermo icon set"), applied via the `brandhouse_icon_paths` filter in the child theme.

## Open items
See the repository root `HANDOVER.md` (current status, client sheet, next steps, security to-dos).
