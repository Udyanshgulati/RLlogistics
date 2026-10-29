# 05 · Plugin and service register, build vs buy

Classes: **A** native WooCommerce · **B** free plugin · **C** paid plugin · **D** custom code · **E** external paid service · **F** optional later.
Licences counted for three storefronts. Plugin prices were **not verified** in this pass; none of the recommendations below requires a paid plugin.

| Capability | Class | Choice | Why / free alternative / limitation |
|---|---|---|---|
| Inventory sync across stores | **D** (built) | Hub ledger + connector | Paid sync plugins copy quantities between stores after the fact; none we found gives atomic cross-store reservations with an audit trail. Maintenance: owned by us. |
| Order management | **D** (built) | Hub dashboard | Requirement is one view of three stores with per-brand roles. |
| Products, variations, sizes | **A** | WooCommerce variable products | Hub generates SKUs; storefront holds content. |
| Checkout | **A** | WooCommerce checkout (classic or blocks) | Never rebuilt. Connector adds the reservation call. |
| Payments | **B** | Razorpay for WooCommerce; WooCommerce PayPal Payments; Cashfree for WooCommerce (official, free) | Gateway fees are the cost (doc 03). |
| Multi-currency display | **D** (built in hub; storefront part to build) | Market engine + ECB rates | Display only; no plugin needed at INR-only launch. |
| Multi-currency checkout (phase 2) | **B** | WooCommerce Multilingual & Multicurrency (free, works without WPML) | Avoids custom checkout-currency code; must be tested with each gateway plugin. |
| Shipping rates at launch | **A** | WooCommerce shipping zones + flat/table rates from the carrier contract | No paid shipping plugin: commercial carrier plugins exist but must be checked for India origin and INR; not needed if the hub books shipments. |
| Shipment booking, labels, tracking | **D** + **E** | Hub carrier adapter + carrier account | One adapter per chosen carrier. |
| Transactional email | **D** + **E** | Hub notification engine + Amazon SES | Engine to build. |
| WordPress system email (password resets) | **B** + **E** | FluentSMTP (free) pointed at SES — verify current feature list | |
| WhatsApp | **D** + **E**, **F** | Hub adapter + Meta Cloud API | Optional. |
| Security (WordPress) | **B** / config | Cloudflare WAF (free plan), fail2ban, file permissions, disabled XML-RPC, limited admin accounts, 2FA plugin (free tier) | Avoid stacking security plugins. |
| Backups | **D** + **E** | `deploy/backup.sh` + object storage (Backblaze B2 or similar) | Same scripts for WordPress databases and `wp-content/uploads`. |
| Page caching | config | nginx FastCGI cache with WooCommerce exclusions (doc 06) | No caching plugin required; or a free one if staff need a purge button. |
| Object cache | **F** | Redis + free object-cache drop-in | Add when database load justifies it. |
| CDN | **E** (free tier) | Cloudflare | |
| SEO | **B** | One SEO plugin with a free tier, per store (e.g. Rank Math or Yoast free — choose one) + WooCommerce structured data | Per-brand titles, sitemaps, canonicals. |
| Analytics | **E** (free) | GA4 + Search Console per brand; events via the theme (view_item, add_to_cart, begin_checkout, purchase) | Consent banner where required. |
| Returns | **D** (built) | Hub returns workflow | Customer-facing return request form: **F**. |
| Fraud prevention | gateway | Razorpay/PayPal built-in risk tools | Paid fraud services: **F**. |
| Tax calculation | **A** + adviser | WooCommerce tax tables per approved rules | Automated tax services: **F** (only if markets need it). |
| Monitoring | **E** (free tiers) | Uptime check on `/api/v1/health`; error log shipping; `bin/hub reconcile` exit code in cron | |
| Reporting | **D** (built) | Hub reports + CSV | |

## Build vs buy, in short

| Build (custom) | Buy / reuse |
|---|---|
| Stock ledger and reservations, product mapping, OMS dashboard, reconciliation, alerts, reports, notification routing and brand templates, carrier adapter, connector plugin, storefront themes | Payments (gateways), carrier networks, email delivery (SES), WhatsApp (Meta), CDN/WAF (Cloudflare), domains, server, object storage for backups, WooCommerce itself, multi-currency checkout plugin (phase 2) |

Custom code is not free: doc 08 counts its build and maintenance hours. The rule applied: build only where no product meets a hard requirement (atomic shared stock, one OMS across stores) or where the custom part is small and stable (routing, templates); never rebuild payments, carriers or email delivery.
