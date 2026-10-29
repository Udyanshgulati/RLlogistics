# 06 · Infrastructure, deployment, security and recovery

## 1. Server plan

Hostinger VPS prices checked 29 Oct 2026 ✔ (hostinger.com/vps-hosting; 24-month term, paid upfront):

| Plan | vCPU / RAM / NVMe / bandwidth | Intro price | Renewal |
|---|---|---|---|
| KVM 4 | 4 / 16 GB / 200 GB / 16 TB | $12.99/mo ($311.76 for 24 months) | $28.99/mo |
| KVM 8 | 8 / 32 GB / 400 GB / 32 TB | $25.99/mo ($623.76 for 24 months) | $49.99/mo |

**Recommendation:** start production on **one KVM 4** running the three WordPress sites, the hub and MariaDB, with Cloudflare in front, plus a **separate small VPS for staging** (needed from day one: connector, gateways and carriers are tested there, never on production; staging plan price: verify). Move to KVM 8 or split the database onto its own server when monitoring shows sustained CPU above ~60%, PHP-FPM queueing, or database memory pressure — not before. This sizing is an estimate: expected orders per month and peak traffic are still unknown (doc 07).

Trade-offs: one server is cheap and simple but is a single point of failure — if it goes down, all three stores and the hub go down together (by design the stores also refuse checkout without the hub, which avoids overselling). Higher availability (a second server, managed database, failover) roughly doubles infrastructure cost and is worth it only once revenue per hour of downtime justifies it. Customers abroad are served by Cloudflare's CDN for static assets; checkout and inventory calls always reach the origin and are never cached. Choose the data-centre location closest to the main customer markets (available locations: verify in the Hostinger panel).

### Stack
Ubuntu LTS · nginx · PHP-FPM 8.3 (one pool per site, `pm = ondemand`, separate Unix users) · MariaDB 10.11 (InnoDB, binary logs on, `binlog_format=ROW`) · optional Redis later · Let's Encrypt via certbot (or Cloudflare origin certificates with Full (strict) SSL) · ufw (22 restricted, 80/443) · fail2ban · unattended-upgrades for security updates · logrotate for nginx/PHP/hub logs.

### Caching rules (WooCommerce-safe)
Cache full pages only for anonymous visitors. **Never cache:** `/cart`, `/checkout`, `/my-account`, `?wc-ajax=`, `/wp-json/wc/store/*`, `/wp-admin`, `/wp-login.php`, or any request with `woocommerce_items_in_cart`, `woocommerce_cart_hash`, `wp_woocommerce_session_*` or `wordpress_logged_in_*` cookies. The hub is never cached (`Cache-Control: no-store` on every response). Displayed prices in non-base currencies are rendered from cached rate JSON so product pages stay cacheable.

### Cron
```
* * * * *   php /srv/hub/bin/hub work          # expiry checks + outbox (stock pushes, storefront requests, notifications)
15 2 * * *  php /srv/hub/bin/hub rates         # display exchange rates (ECB, working days)
30 2 * * *  php /srv/hub/bin/hub reconcile || <alert>
45 2 * * *  /srv/commerce/deploy/backup.sh      # with DB_NAME, BACKUP_DIR, AGE_RECIPIENT, RCLONE_REMOTE set
```

## 2. Deployment (staging first; production only with approval)

1. Create the database and a least-privilege user: `GRANT SELECT, INSERT, UPDATE, DELETE ON commerce_hub.* TO 'hub'@'localhost'`. Run migrations with a separate migration user that also has `CREATE, ALTER, INDEX, TRIGGER, REFERENCES`.
2. Copy `commerce/` to the server (git checkout of a tagged commit). Point the nginx vhost root at `commerce/hub/public`; deny everything outside it.
3. Create `hub/.env` from `.env.example` (permissions 600, owned by the PHP pool user). `APP_ENV=staging` or `production`, `FORCE_SECURE_COOKIES=1`.
4. `php bin/hub migrate && php bin/hub seed && php bin/hub create-owner you@company "Name"`; open the printed link to set the password.
5. Settings → enter the warehouse address and each storefront address; generate REST keys and secrets (below) and add them to `.env`.
6. Install the connector on each WooCommerce staging site (when built), set the WooCommerce webhooks to `https://<hub>/api/v1/webhooks/woocommerce/<store id>`, and run the staging checklist in doc 10.

Rollback: deploys are git tags; roll back by checking out the previous tag. Migrations only add tables/columns, so the previous code keeps working against a newer schema; a migration that removes data will be called out explicitly before it ships and preceded by a backup.

### Environment variables

| Variable | Purpose |
|---|---|
| `APP_ENV` | `production`, `staging`, `development`. Demo data and dev market override are refused in production. |
| `APP_URL` | Base URL used in set-up links |
| `DB_DSN`, `DB_USER`, `DB_PASSWORD` | Hub database |
| `DISPLAY_TZ` | Display timezone (default Asia/Kolkata) |
| `FORCE_SECURE_COOKIES` | `1` behind a TLS-terminating proxy |
| `MARKETS_CONFIG` | Path to the reviewed `markets.json` |
| `STORE_<CODE>_WC_KEY`, `STORE_<CODE>_WC_SECRET` | WooCommerce REST credentials (products + orders read/write only) |
| `STORE_<CODE>_HUB_SECRET` | Shared secret for the connector's signed checkout calls (≥ 32 random bytes) |
| `STORE_<CODE>_WEBHOOK_SECRET` | Secret configured on the WooCommerce webhooks |
| `DEMO_PASSWORD` | Only for `bin/hub demo` outside production |

## 3. Security plan

| Control | Implementation | Status |
|---|---|---|
| HTTPS everywhere | Cloudflare Full (strict) + HSTS at nginx | Deploy step |
| Secrets | Environment only; `.env` git-ignored; Settings shows presence, never values; error messages redact keys (`Outbox::safeMessage`, tested) | Built |
| Least privilege | DB user without DDL; WooCommerce keys scoped to a dedicated user; staff roles per brand | Built / deploy step |
| Staff authentication | argon2id, failed-attempt limits (5/email, 20/IP per 15 min) and 15-minute lockout after 10, session regeneration, idle expiry, `__Host-` cookie over HTTPS | Built, tested |
| CSRF / XSS / clickjacking | Token on every form; output escaping; CSP `default-src 'self'` with no inline script/style; `frame-ancestors 'none'` | Built, tested |
| Webhooks and API | HMAC per store, 5-min window, WooCommerce signature check, rate limits, fail closed | Built, tested |
| Input validation | Server-side on every action; ledger rules enforced again by DB constraints | Built |
| Audit | Mappings, imports, exports, roles, refunds, offline payments, customer-detail views, sign-ins | Built |
| Customer data | Only what fulfilment needs; PII behind a permission; webhook payloads stored without IP/user agent | Built; retention policy to agree (proposal: purge customer PII from the hub 24 months after delivery) |
| Card data | Never handled (hosted gateway pages / tokenisation) | By design |
| Dependencies | No Composer or npm dependencies in production code; PHP/WordPress/plugins updated monthly on staging first | Process |
| Backups | Nightly encrypted dump + binary logs off-site; restore drill monthly | Scripts built and drilled locally |

## 4. Backup and recovery

- `deploy/backup.sh`: consistent `mariadb-dump --single-transaction` (includes the append-only triggers), gzip, optional `age` encryption to a public key (private key kept off the server), checksum, copy off-site with rclone, 14 local copies. Do the same for each WordPress database and `wp-content/uploads`.
- `deploy/restore-check.sh`: restores into a throwaway `commerce_restore_*` database, runs the ledger reconciliation and prints row counts. **Drilled on 29 Oct 2026**: restore succeeded, counts matched, reconciliation passed, triggers intact, and a deliberately forged movement was detected.
- Point-in-time recovery: ship MariaDB binary logs off-site hourly.

### Runbooks

**Inventory looks wrong.** 1) Settings → mark storefronts *Paused* (checkouts are refused, nothing oversells). 2) Integration health → *Run stock reconciliation*; it lists any item whose balance disagrees with its movement history or whose reserved count disagrees with open orders. 3) Inspect that item's history. 4) Fix by recording a stocktake or adjustment with a reason — never by editing tables. If the history itself is damaged, restore the last good backup to staging, compare, and replay with binary logs. 5) Un-pause.

**Payment provider outage or webhook backlog.** Checkouts fail at the gateway; holds stay in place. The expiry worker asks each storefront for the real order status before releasing anything, so delayed webhooks do not free paid stock. Payments that arrive after a hold lapsed re-take stock or appear under *Payment and stock exceptions* for a person to refund or reinstate.

**Server outage.** Storefronts and hub are down together; no orders are lost because WooCommerce has not taken them. After restart: check `/api/v1/health`, run `bin/hub work` once, review Integration health (queued stock pushes resume automatically), run reconciliation. If the server is lost: provision, restore the latest backup plus binary logs, re-deploy the tagged code, verify with `restore-check.sh`.

**A storefront stops sending webhooks.** Integration health shows its last contact. Re-enable the webhook in WooCommerce (it is disabled after repeated failures), then re-send missed orders (scheduled order pull: planned).

## 5. Troubleshooting (technical)

| Symptom | Likely cause | Fix |
|---|---|---|
| Checkout says "temporarily unavailable" for one product | Storefront product not linked to a SKU | Products → *Storefront products not linked* → link it |
| "Some items have just sold out" but stock shows on the product page | Storefront quantity is a display copy; push queued or failed | Integration health → failed jobs → Retry; check REST credentials |
| Orders not appearing | Webhook disabled / wrong secret / store paused | Integration health → last contact; WooCommerce → Settings → Advanced → Webhooks |
| "This form expired" | Session expired or CSRF token stale | Refresh the page and resubmit |
| Notifications not sent | No email provider configured yet | Expected until the engine is built; jobs are listed, nothing is lost |
| `bin/hub reconcile` exits 1 | Ledger inconsistency | Runbook above |
| Sign-in refused | 5 failed attempts in 15 min, or account locked/disabled | Wait 15 minutes; owner can restore access in Team |
