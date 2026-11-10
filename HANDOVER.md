# Handover — Schermo storefront & multi-brand commerce platform

Owner: Udyansh Gulati (Thoda Logic). Last updated 10 Nov 2026.
Everything is in GitHub: **`Udyanshgulati/RLlogistics`**, branch **`claude/modest-ptolemy-9yf78k`** (public repo).
Read this file first; it is written so a new person — or a new Claude Code session on another account — can pick up
with no other context.

---

## 1. What exists

| Part | Where | Status |
|---|---|---|
| **Schermo storefront** (WooCommerce, live) | `commerce/storefront/` | **Live** at https://schermo.thodalogic.com |
| Shared parent theme *Brandhouse* (for all 3 brands) | `commerce/storefront/themes/brandhouse/` | Used by Schermo |
| Schermo child theme | `commerce/storefront/themes/schermo/` | Live |
| Milan Icons catalogue (24 products + images) | `commerce/storefront/catalog/milan-icons/` | Live, **no prices/stock yet** |
| Client listing sheet (Excel) | `commerce/storefront/catalog/client-sheets/Schermo_Product_Listing_Sheet.xlsx` | Sent to client, waiting |
| Inventory ledger core (PHP + MariaDB) | `commerce/core/` | Built + tested, **not deployed** |
| OMS / inventory dashboard ("hub") | `commerce/hub/` | Built + tested, **not deployed** |
| Architecture & planning docs (12 docs) | `commerce/docs/` | Complete |
| RL Logistics website (separate, older project) | repo root (non-`commerce/` folders) | Not part of this handover |

Brands in the original brief: **Schermo** (live), **Milan Icons** (sold inside Schermo as a collection),
**Significant Sports** (not started). Warehouse: Delhi.

---

## 2. How the live site runs

**Server:** Hostinger VPS `200.141.13.226` (Ubuntu 24.04, root). The same VPS also hosts other live sites
(The Asense, Everlasting Events, RL Logistics …) and Coolify. **Never touch other sites, containers, nginx sites or Coolify.**

**DNS:** `schermo.thodalogic.com` → A record → `200.141.13.226` (DNS only).

**Stack (Docker Compose project `schermo`):**
- `schermo-wordpress-1` — WordPress 7.1.4 + WooCommerce 11.2.1 + both themes (image built from `commerce/storefront/Dockerfile`), published on **127.0.0.1:8095** only.
- `schermo-mariadb-1` — MariaDB 11.4.
- Volumes: `wordpress-files` (uploads, plugins), `mariadb-data` (database). **Redeploys keep these.**
- Host nginx proxies the domain to 8095: `/etc/nginx/sites-available/schermo.thodalogic.com` (template: `commerce/storefront/deploy/nginx-schermo.conf`). SSL by certbot (auto-renews).

**Server paths:**
- `/opt/schermo/src` — git checkout of the repo (public, no key needed).
- `/opt/schermo/schermo.env` — passwords (DB, WP admin). Root-only, never in git.
- `/var/log/schermo-autodeploy.log` — deploy log.

### Push-to-deploy (already installed and working)
A systemd timer `schermo-autodeploy.timer` runs `commerce/storefront/deploy/autodeploy.sh` **every minute**:
fetch branch → if new commit: `docker compose up -d --build` → wait for `[schermo-setup] ready` → check site answers 200 →
if not, **rolls back** to the previous commit.

**So: to change the live site, commit and push to `claude/modest-ptolemy-9yf78k`. It is live in ~1–2 minutes.** No server login needed.

On every container start `docker/setup.sh` runs: syncs themes from the image, (first run only) installs WordPress,
then runs `docker/import-catalog.php`, which adds/updates/removes catalogue products (see §4). Edits made directly inside
the container's theme folder are overwritten on the next deploy — always change the repo.

Useful server commands (only if something breaks):
```
systemctl status schermo-autodeploy.timer --no-pager
tail -50 /var/log/schermo-autodeploy.log
docker logs --tail 50 schermo-wordpress-1
cd /opt/schermo/src/commerce/storefront && docker compose -p schermo --env-file /opt/schermo/schermo.env up -d --build
grep WPADMIN /opt/schermo/schermo.env      # WordPress admin password
```

**WordPress admin:** https://schermo.thodalogic.com/wp-admin — user `schermo-admin`, password = `SERVICE_PASSWORD_WPADMIN` in `/opt/schermo/schermo.env`. Change it after first login.

---

## 3. What the site has (features)

- Dark glass/bento design (references supplied by the owner). Logo top-left; standard icons; system default cursor (owner's choice).
- **Homepage:** hero with 4 products switchable 01–04 + feature callouts; category tiles; New arrivals slider; Milan Icons banner.
- **Shop page:** bento header, glass toolbar (category pills, size pills, sort), all products on one page, Milan Icons strip.
- **Product cards:** transparent cut-out images on a studio background; hover → background behind the product turns deep red with glow, second view slides in.
- **Product page:** main photo sticky + scrolling gallery (swipe on phone), sizes, size guide, accordions (Details / Specs / Delivery / Returns), related products. **Until a product has a price**, sizes and disabled Add to cart / Buy now are shown with "Ordering opens when the price is announced".
- **Country/currency popup** on first visit (IP geolocation): India, US, UK, EU, UAE, Rest of world, with flags. Prices shown converted with live rates (open.er-api.com, 12 h cache); **checkout always charges INR**.
- Notify me (back-in-stock emails; list under Products → Stock alerts), free-shipping progress bar (appears once a Free shipping minimum is set in WooCommerce → Settings → Shipping), drop countdown (Customize → Drop countdown).
- Menu links to empty categories hide automatically (Fan Merch is hidden since the Medal was removed).

Code map: `themes/brandhouse/inc/woocommerce.php` (shop/product behaviour), `inc/markets.php` (country/currency),
`inc/extras.php` (notify me, shipping bar, countdown), `assets/css/base.css`, `assets/js/theme.js`;
`themes/schermo/front-page.php`, `woocommerce/archive-product.php`, `functions.php`, `assets/css/schermo.css`.

---

## 4. Product catalogue

`commerce/storefront/catalog/milan-icons/products.json` is the source of truth for the Milan Icons products
(SKU MI-xxx, name, category, sizes yes/no, description, image files). Images: `mi-<product>-<view>.webp`
(transparent, 1200×1500), cut from the owner's deck *Milan_Icons_Merchandise.pdf* with background removal.

Importer rules (`docker/import-catalog.php`, runs on every deploy):
- New SKU → product created (apparel gets sizes S–XXXL as variations).
- Existing SKU → **never overwritten** (admin edits are kept), except photos are replaced when that product's `image_version` goes up.
- SKUs listed under `"removed"` → moved to trash. Removed so far: MI-007 helmet, MI-008 pads, MI-009 gloves, MI-028 medal.

- A product trashed in WP admin stays trashed (it is not re-created on the next deploy).

**Prices and stock are not set** — the owner/client must supply them (never invent). They come from the client sheet
(§5), loaded by `docker/import-sheet.php`.

---

## 5. Waiting on the client

Sheet: `commerce/storefront/catalog/client-sheets/Schermo_Product_Listing_Sheet.xlsx` (tabs: How to fill, Products,
Stock by size, Size chart, Store details & policies). Needed:
1. **Selling price** and **stock per size** for each product → this switches on Add to cart / Buy now.
2. HSN code, GST rate, packed weight (for invoices and courier rates).
3. Size chart (cm) → paste into Customize → Product pages → Size guide (HTML table).
4. Returns policy, delivery text, free-shipping minimum, COD yes/no, international countries.
5. Business details (legal name, GSTIN, address, support contact) and brand story.

### Loading the filled sheet (`docker/import-sheet.php`)
1. Save the client's file over `commerce/storefront/catalog/client-sheets/Schermo_Product_Listing_Sheet.xlsx` (same name).
2. Check it on a local copy first: `wp eval-file docker/import-sheet.php <sheet.xlsx> dry-run`. This prints every change,
   the warnings and a "to do by hand" list, and saves nothing.
3. Commit and push. On deploy, `setup.sh` loads the sheet once, and only when the file has changed (its sha256 is stored
   in the `schermo_sheet_sha` option). The output is in `docker logs schermo-wordpress-1`.

What it sets: price, sale price and MRP; stock per size (`MI-001-M`), barcode and low-stock level; Show on site
(Publish / Keep hidden → draft / Remove → trash); short description; specifications (material, GSM, fit, care, colour,
made in); HSN; GST rate (switches on WooCommerce tax, with prices including GST); packed weight and size; search
keywords (tags); size guide; India shipping rate and free-shipping minimum; COD on/off; countries we sell to (EU expands
to its member states); delivery and returns text; drop countdown; announcement bar; brand story.
Rules: empty cells change nothing. Running it again changes nothing. A product with a price but no stock filled in gets
stock 0 (it shows Sold out and can't be oversold). The whole run is one database transaction.
Not covered (it prints these as "to do by hand"): international shipping rate (the sheet doesn't ask for one), COD
fee, courier plugin, footer/contact details, full returns page.

### Payments and shipping (ready, waiting on keys)
- **Razorpay** (official plugin `woo-razorpay`, version and checksum pinned in the Dockerfile) is installed and active
  but **switched off**. To go live: WooCommerce → Settings → Payments → Razorpay, paste the **test** Key ID/Secret,
  tick Enable, then place a test order. Add the webhook in the Razorpay dashboard:
  `https://schermo.thodalogic.com/wp-admin/admin-post.php?action=rzp_wc_webhook`. Then swap in the live keys.
  Keys go only in WP admin.
- **Shipping:** an "India" zone exists (`docker/store-setup.php`). Its rates come from the sheet. Other countries use
  WooCommerce's "Rest of the world" zone, and a rate must be added there by hand.

---

## 6. Next steps (in order)

1. When the sheet comes back: dry-run it locally, then commit it (see "Loading the filled sheet"). Work through the "to do by hand" list.
2. **Payments:** enter the Razorpay **test keys** (from the owner directly) in WP admin, place a test order, then switch to live keys. Keys go only in WP admin, never in git or chat.
3. Shipping: add the international rate and connect the courier (Shiprocket/Delhivery/DHL; the client chooses).
4. Before public launch: Settings → Reading → allow search engines (currently blocked); remove leftover "Demo" products from Trash.
5. Later (designed in `commerce/docs`, code in `commerce/core` + `commerce/hub`): central inventory ledger + OMS dashboard for all brands, WhatsApp/email notifications, Milan Icons and Significant Sports storefronts as more child themes of Brandhouse.

---

## 7. Security to-dos (important)

- **Rotate**: the Razorpay *live* key secret + webhook secret, VPS root password, Coolify password, Postgres password and the "Secret API (ops)" key — they were shared in a screenshot in chat.
- Change the WordPress admin password after first login.
- Keep `/opt/schermo/schermo.env` out of git (it is).

---

## 8. Continuing on another Claude account

1. On the new account, connect GitHub (claude.ai → Settings → Connectors / connect-github) with access to `Udyanshgulati/RLlogistics`.
   If the new account belongs to a different GitHub user, add them as a collaborator on the repo (or transfer the repo).
2. Start a Claude Code session on that repo, branch `claude/modest-ptolemy-9yf78k`. First message:
   > Read `HANDOVER.md` and `commerce/storefront/HANDOFF.md`. Continue the Schermo storefront. Deploys happen automatically when you push to this branch.
3. Pushing to the branch deploys — no server access needed for normal work.
4. Server access is only needed for emergencies (Hostinger panel → VPS → Terminal, or `claude remote-control` on the VPS from the new account; the old Remote Control link belongs to the old account).
5. Design references (Stitch projects "Schermo icon set", "Schermo homepage v3") live on the old account; the code already contains everything used.
6. Original files that are **not** in the repo (keep copies): `Schermo_Logo.ai`, `Milan_Icons_Merchandise.pdf` (the logo SVGs and product images extracted from them are in the repo).

## 9. Local development (optional)
`commerce/storefront/README.md` explains running the stack locally: `docker compose up` with an env file (see compose for the variables), site on 127.0.0.1:8095.
