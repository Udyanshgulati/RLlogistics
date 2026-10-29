# 08 · Costs

Itemised in [`costs.csv`](costs.csv). Checked prices carry their source and date (29 Oct 2026); everything else is an estimate or a quote to obtain. **Nothing here is a quotation.**

## Budget A — software and infrastructure

| | One-time | Monthly | Usage-based | Notes |
|---|---|---|---|---|
| Production server (Hostinger KVM 4) | $311.76 for 24 months ✔ | then $28.99 ✔ | — | KVM 8 only if monitoring shows the need ($623.76 / 24 mo, then $49.99) |
| Staging server | — | verify | — | Required |
| Domains | — | — | annual renewals (client's registrar) | |
| Cloudflare | — | $0 (free plan) | — | |
| Off-site backups | — | small; quote | per GB | |
| Email (Amazon SES) | — | — | $0.10 / 1,000 emails ✔ | ~10 emails per order ⇒ ~$1 per 1,000 orders |
| WhatsApp (optional) | — | — | per delivered message, by country ✔ model | rate card per market |
| Payment gateways | $0 setup | $0 | Razorpay 2% domestic / up to 3% international + GST ✔; PayPal 4.4% + fixed ✔; Cashfree 2.99% intl ✔ | the largest running cost at scale |
| Carriers | — | — | contract rates (quote) | charged to customers through shipping prices |
| Paid plugins | **$0** | **$0** | — | none required (doc 05) |
| Monitoring | — | $0 on free tiers | — | |

**Avoided recurring costs through custom code:** cross-store inventory sync/OMS subscriptions, paid multi-currency display plugins at launch, paid shipping-rate plugins per store (×3), paid notification/email plugins per store (×3). These are avoided only because the hub now does those jobs; its maintenance is counted below.

## Budget B — implementation and operations (estimates)

| Work | Hours (range) | Status |
|---|---|---|
| Central ledger, OMS dashboard, market engine, tests | — | **Done** (this repository) |
| Discovery, SKU and shared-stock mapping workshop | 16–24 | |
| Storefront themes (one parent + three brand child themes) | 120–180 | Needs brand assets |
| WooCommerce connector plugin | 50–80 | Needs staging WooCommerce |
| First carrier adapter | 40–70 | Needs carrier account + sandbox |
| Notification engine, brand templates, SES + DNS | 40–60 | |
| WhatsApp adapter (optional) | 16–24 | |
| Payment gateway set-up and tests (per gateway ~16–24) | 32–48 | Needs merchant accounts |
| Remaining hub items (order-pull backstop, etc.) | 20–30 | |
| Catalogue migration | 0.1–0.25 per SKU | Needs SKU count |
| Deployment, monitoring, backups | 24–40 | |
| Testing and acceptance on staging | 40–60 | |
| Training and handover | 8–16 | |
| **Total (excluding catalogue)** | **≈ 406–632 h (midpoint ≈ 519 h)** | |
| Ongoing maintenance | 8–12 h per month | |

**Cash vs economic value.** If the work is done in-house, the cash cost is Budget A only. The economic value of the effort is *hours × your hourly rate* — e.g. at an assumed ₹1,500/h the midpoint is ≈ ₹7.8 lakh, plus ≈ ₹15,000/month maintenance. The rate is an **assumption**; replace it with your own.
