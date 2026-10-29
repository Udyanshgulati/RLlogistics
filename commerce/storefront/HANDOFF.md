# Storefront handoff (Schermo)

Everything below is committed on branch `claude/modest-ptolemy-9yf78k`. From here on, one session (the one running on
the VPS at /opt/schermo) owns this code and the deployment. Pull before changing anything.

## Live
- https://schermo.thodalogic.com — Docker stack `schermo` (compose in this folder), WordPress on 127.0.0.1:8095,
  host nginx site `/etc/nginx/sites-available/schermo.thodalogic.com`, certificate via certbot. Secrets: /opt/schermo/schermo.env.
- Push-to-deploy (once installed): pushing to the branch is enough. `deploy/autodeploy.sh` runs every minute
  (systemd timer `schermo-autodeploy.timer`), rebuilds when the branch moves, checks the site and rolls back on failure.
  Log: /var/log/schermo-autodeploy.log. Install once on the VPS:
  `git -C /opt/schermo/src checkout -q --detach origin/claude/modest-ptolemy-9yf78k && cp /opt/schermo/src/commerce/storefront/deploy/schermo-autodeploy.{service,timer} /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now schermo-autodeploy.timer`
- Manual deploy: `git -C /opt/schermo/src pull && cd /opt/schermo/src/commerce/storefront && docker compose -p schermo --env-file /opt/schermo/schermo.env up -d --build`.
  Themes are re-synced from the image on every start (docker/setup.sh), so edits made inside the container are lost on redeploy:
  change the repo, then deploy.

## Not yet on GitHub
- The flag images in the country selector (themes/brandhouse/assets/flags/*.svg + the markets.php change) exist only in
  the VPS checkout. Commit and push them before the next deploy, or they disappear.

## Built (all in the repo)
- Themes: `themes/brandhouse` (shared parent) + `themes/schermo` (child). Logo SVGs extracted from Schermo_Logo.ai.
- Country/currency popup on first visit (IP geolocation), live rates (open.er-api.com, 12 h cache, INR fallback after 48 h),
  checkout always INR (`inc/markets.php`).
- Product page: sticky main photo + scrolling photos, phone swipe with counter, Buy now (`woocommerce/single-product/product-image.php`).
- Notify me (back-in-stock emails, Products → Stock alerts), free-shipping bar (uses the Free shipping method's minimum;
  hidden until one is set), drop countdown (Customize → Drop countdown; hidden until a future date is set) — `inc/extras.php`.
- Icon set designed in Stitch (project "Schermo icon set"), applied via the `brandhouse_icon_paths` filter in the child theme.

## Open items
1. Milan Icons products: the owner shared "Milan_Icons_Merchandise.pdf" (29 pages, 28 products: playing jersey, playing trouser,
   playing cap, practice kit, practice kit sleeveless, practice shorts, helmet, cricket pads, playing gloves, kit bag, backpack,
   winter warmer full sleeve, winter warmer sleeveless (PDF page 14 is mislabelled "full sleeve"), tracksuit, winter travel hoodie,
   travel kit (maroon tee), round neck travel kit, polo t-shirt, fashion cap 01, fashion cap 02, bucket cap, winter cap, socks,
   bag tag, travel bag, travel bag 02, sipper, medal). The PDF must be uploaded again to the session that will do the import.
   Plan: crop each product's views from the page renders (remove the black title label), pad to 4:5, attach as main image + gallery;
   apparel as variable products with sizes S–XXXL; no prices or stock until the owner supplies them (do not invent).
   Decide with the owner whether the "Demo …" products are removed once real ones exist.
2. Prices, stock, returns policy, size chart, free-shipping minimum: owner to supply.
3. Payments: Razorpay with test keys first; keys only in WP admin. The live keys shared in chat must be rotated.
