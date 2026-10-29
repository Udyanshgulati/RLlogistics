# Commerce platform — Milan Icons · Significant Sports · Schermo

Central inventory, order management and operations for three independent WooCommerce storefronts shipping from one Delhi warehouse.

> **Why this lives in the RL Logistics repository.** It was the repository attached to the working session. `commerce/` is self-contained (its own PHP code, tests and docs, no shared build) and is excluded from the RL Logistics Docker image. Move it to its own repository before production; nothing in it depends on the RL Logistics site.

## What exists today

| Area | Status | Evidence |
|---|---|---|
| Central stock ledger (reservations, adjustments, returns, purchasing, undo, reconciliation) | **Built and tested** | 81 core tests, including multi-process race tests on MariaDB |
| Overselling protection across storefronts | **Tested** for the hub ledger | `ConcurrencyTest`: 2 stores racing for the last unit ×15 rounds, 40 buyers for 10 units, opposite-order multi-line carts, duplicate concurrent checkout, payment-vs-expiry race, and a negative control proving the harness catches a naive implementation |
| Order sync from WooCommerce (idempotent, out-of-order safe) | **Built and tested** against WooCommerce-shaped payloads | `OrderFlowTest`, `HttpTest` webhook test. **Not yet run against a live WooCommerce store** |
| Operations dashboard (overview, orders, inventory, products, shipments, returns, transfers, reports, team, settings, integration health) | **Built and tested** | 12 HTTP-level tests; browser checks at 1440 / 820 / 390 px; reduced-motion verified |
| Roles and per-brand permissions (server-side) | **Built and tested** | `PrincipalTest`, `HttpTest::testRestrictedActionsAreRefusedOnTheServer` |
| Market / geolocation / currency engine | **Built and tested as a library** | `MarketTest` (20-case matrix, see `docs/04-markets-and-geolocation.md`). Storefront side not built |
| Express shipping selection rules | **Built and tested with fixtures** | `ExpressSelectorTest`. **No carrier adapter exists** — needs an account and sandbox |
| WooCommerce REST client (stock push, cancel, refund, notes) | **Written, unit-tested with a fake transport** | Not run against a store |
| Backup and restore drill | **Tested locally** | `deploy/restore-check.sh`: restore, row counts, reconciliation, append-only triggers intact |
| WordPress connector plugin (checkout reservation, catalogue sync, market selector) | **Not built** | Design in `docs/01-architecture.md` §5; needs a staging WooCommerce install |
| Three storefront themes | **Not built** | Design direction in `docs/11-design.md`; needs brand assets and domains |
| Customer email / WhatsApp sending | **Not built** | Notifications are queued; `PendingNotificationHandler` fails visibly until a provider is chosen |
| Payment gateways | **Not configured** | Plugins are free; merchant accounts and verified currencies required |
| Production deployment, DNS | **Not done** (requires approval) | Plan in `docs/06-infrastructure-and-security.md` |

## Layout

```
commerce/
  core/        Framework-free PHP 8.2 library: ledger, catalogue, orders, market engine, access, integrations
    migrations/  Versioned SQL (MariaDB ≥ 10.6 / MySQL ≥ 8.0.16)
    tests/       Dependency-free runner (bin/test), PHPUnit-style assertions
  hub/         The operations dashboard + storefront API (plain PHP, no framework, no JS build)
    bin/hub      CLI: migrate, seed, create-owner, work, rates, reconcile, demo
    tests/       HTTP-level tests through the real router
  config/      markets.example.json — market, currency, gateway and brand rules (placeholders)
  deploy/      backup.sh, restore-check.sh
  docs/        Architecture and all deliverables (index below)
```

## Run it locally

Requires PHP 8.2+ (pdo_mysql, intl, curl, sodium) and MariaDB 10.6+.

```bash
cd commerce/hub
cp .env.example .env            # then set DB_* and DEMO_PASSWORD
php bin/hub migrate
php bin/hub seed
php bin/hub demo                # labelled demonstration data; refused when APP_ENV=production
php -S 127.0.0.1:8080 -t public public/router.php
```

Sign in as `owner@example.test` (or `warehouse@`, `brand-manager@`, `finance@`, `support@`, `ops-manager@example.test`) with `DEMO_PASSWORD`.

## Tests

```bash
commerce/core/bin/test          # 81 tests — needs a MariaDB the runner can reach as TEST_DB_* (default root@localhost)
commerce/hub/bin/test           # 12 tests
```

The runner refuses any database whose name does not start with `commerce_test`, so it cannot touch real data.

Last run (29 Oct 2026, MariaDB 10.11, PHP 8.3): **core 81 passed / 569 assertions; hub 12 passed / 114 assertions; 0 failed.**

## Documents

| # | Document | Covers |
|---|---|---|
| 01 | [Architecture](docs/01-architecture.md) | System and data-flow diagrams, components, inventory decision, lifecycles, storefront integration map, WooCommerce hook limitations |
| 02 | [Database](docs/02-database.md) | Every table and the invariants it enforces |
| 03 | [Integrations](docs/03-integrations.md) | Shipping, payments, multi-currency, email, WhatsApp, tax and customs |
| 04 | [Markets and geolocation](docs/04-markets-and-geolocation.md) | Country/currency table, selection logic, eligibility rules, privacy, test matrix |
| 05 | [Plugin register and build vs buy](docs/05-plugins-and-build-vs-buy.md) | Every capability classified A–F |
| 06 | [Infrastructure and security](docs/06-infrastructure-and-security.md) | Server plan, deployment, env vars, backups, recovery runbooks, troubleshooting |
| 07 | [Client information checklist](docs/07-client-checklist.md) | What we need, and when |
| 08 | [Costs](docs/08-costs.md) + [costs.csv](docs/costs.csv) | Budget A (software) and Budget B (implementation) |
| 09 | [Roadmap](docs/09-roadmap.md) | Phases, what is done, what is next |
| 10 | [Test and acceptance plan](docs/10-test-plan.md) | Every required test with its status; known limitations |
| 11 | [Design](docs/11-design.md) | Stitch process, dashboard design system, motion, storefront art direction |
| 12 | [Operations guide](docs/12-operations-guide.md) | Roles, daily workflows, troubleshooting for staff |
