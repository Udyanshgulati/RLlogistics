# Storefront themes

Classic WooCommerce themes. No WordPress or WooCommerce core files are modified.

- `themes/brandhouse` — shared parent theme for all three stores: header, footer, listing with size filter, product page (size buttons from real variation stock, size guide, accordions), cart/checkout styling. Checkout stays WooCommerce's own.
- `themes/schermo` — Schermo child theme: monochrome tokens from the logo, Syncopate + Inter (self-hosted, OFL), homepage. `assets/img/schermo-{logo,mark,wordmark}.svg` are vector paths extracted unchanged from the client file `Schermo_Logo.ai`; the S mark is also used as a faint background motif (hero, footer, story, shop header, placeholders, 404) and as the favicon.

## Client content (placeholders until supplied)
Nothing is invented. Empty slots show a labelled grey placeholder.

| What | Where |
|---|---|
| Logo | Built in from the supplied vector logo. A logo uploaded in Customize → Site Identity overrides it. |
| Hero image, headline, line, button; brand story text and image | Customize → Homepage |
| Announcement bar, footer note | Customize → General |
| Delivery and returns text, size guide table | Customize → Product pages |
| Category images | Products → Categories → Thumbnail |
| Menus | Appearance → Menus (Primary, Footer: Shop / Help / Policies) |

## Local preview
Install WordPress + WooCommerce, symlink both theme folders into `wp-content/themes`, activate **Schermo**, and create the global attribute **Size** (`pa_size`, terms S–XXXL). Product variations use that attribute.

## Deploy with Coolify (staging: https://schermo.thodalogic.com)
`Dockerfile` + `docker-compose.yml` in this folder build WordPress 7.1.4 + WooCommerce 11.2.1 (checksums pinned) with both themes, plus MariaDB 11.4. The first start installs WordPress, activates WooCommerce and the Schermo theme, sets INR, pretty links, the Size attribute (S–XXXL), categories, the menu and the homepage. With `SEED_DEMO=1` it also adds the labelled demo products. Later starts only re-sync the themes from the image, so a redeploy ships theme changes and keeps orders, products and uploads (volumes `wordpress-files`, `mariadb-data`). Search engines are blocked (Settings → Reading) until launch is approved.

1. DNS: `A schermo → 200.141.13.226`, DNS only (already set).
2. Coolify → Projects → New Resource → Private Repository (GitHub App) → `udyanshgulati/rllogistics`, branch `claude/modest-ptolemy-9yf78k`.
3. Build Pack **Docker Compose**, Base Directory `/commerce/storefront`, Docker Compose Location `/docker-compose.yml`.
4. On the `wordpress` service set Domain `https://schermo.thodalogic.com`.
5. Environment variables: set `WP_ADMIN_EMAIL`. Coolify generates `SERVICE_PASSWORD_*` and `SERVICE_USER_MARIADB`; the WordPress admin password is `SERVICE_PASSWORD_WPADMIN` (user `schermo-admin`). Set `SEED_DEMO=0` to start with an empty catalogue.
6. Deploy. Coolify issues the Let's Encrypt certificate. Admin: `https://schermo.thodalogic.com/wp-admin`.

No payment gateway is configured; add Razorpay in WooCommerce → Settings → Payments with test keys first. Keys go in WordPress admin only, never in this repository.
