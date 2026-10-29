# 09 · Phased roadmap

| Phase | Scope | Status | Blocked on |
|---|---|---|---|
| 1. Audit | Repositories, assets, domains | **Done** (doc 01 §1) | — |
| 2. Architecture | All documents in `docs/` | **Done**, to review with the client | Client review |
| 3. Inventory core | Ledger, reservations, mapping, SKUs, adjustments, returns, purchasing, reconciliation, concurrency tests | **Done** | — |
| 4. Operations dashboard | Overview, orders, inventory, products, shipments, returns, reports, team, settings, health; Stitch-referenced design; motion | **Done** (demo data) | — |
| 5. Market engine | Country resolution, currency policy, rates, availability, express selector | **Done** as a library | — |
| 6. Staging environment | VPS, three WordPress + WooCommerce installs, hub, Cloudflare, backups | Next | Hosting access, domains |
| 7. Connector plugin | Checkout reservation (classic + blocks), stock-reduction override, catalogue sync, webhooks, market selector, order-pull backstop | Next | Phase 6 |
| 8. Storefront themes | Parent theme + Milan Icons / Significant Sports / Schermo child themes, per-brand SEO and analytics | After assets | Brand assets, catalogue |
| 9. Payments | Razorpay, PayPal (and/or Cashfree) on staging in test mode; refunds; verified currencies into `markets.json` | After accounts | Merchant accounts |
| 10. Shipping | First carrier adapter (rates, booking, label, tracking), zone tiers in WooCommerce | After account | Carrier account, warehouse address, HS codes |
| 11. Notifications | SES, per-brand templates, bounce handling; optional WhatsApp | After domains | DNS access, sender addresses |
| 12. Acceptance | Full doc 10 checklist on staging; load test; restore drill on staging | — | Phases 6–11 |
| 13. Launch | Production deploy and DNS — **only with explicit approval** | — | Approval |

Review checkpoints: end of phases 6, 7, 9, 10 and 12 — a short demo plus the test results.
