# 10 · Test and acceptance plan

**Passed** = automated test or recorded check passing on 29 Oct 2026 (MariaDB 10.11, PHP 8.3, Chromium). **Pending** = needs staging, credentials or unbuilt parts. A requirement is complete only when its row says Passed **on staging** — local passes are necessary, not sufficient.

Run: `core/bin/test` (81 tests) and `hub/bin/test` (12 tests). Test databases must be named `commerce_test*`; real notifications cannot be sent from tests (no provider is wired, and the fake store client records calls in memory).

## Master brief §19

| # | Requirement | Status | Evidence |
|---|---|---|---|
| 1 | Simultaneous last-unit purchases across two storefronts | **Passed** (ledger + API) | `ConcurrencyTest::testTwoStorefrontsRacingForTheLastUnit` (15 rounds, separate processes); `HttpTest::testCheckoutReservationApiPreventsSellingTheLastUnitTwice`; negative control `testHarnessDetectsOversellInANaiveImplementation` |
| 2 | Size-specific inventory | **Passed** | `CatalogTest::testSizeRunGeneratesSixSkusEachWithItsOwnStockRecord` |
| 3 | Stock restored after eligible cancellation | **Passed** | `OrderFlowTest::testCancellationReleasesOnceAndIsRequestedThroughTheStorefront`, `LedgerTest::testPaymentFailureReleasesOnce` |
| 4 | Delayed payment confirmation | **Passed** | `ExpiryAndSignatureTest::testDueHoldsAreCheckedWithTheStorefrontBeforeRelease`, `LedgerTest::testLatePayment*` |
| 5 | Duplicate payment webhook | **Passed** | `LedgerTest::testDuplicatePaymentWebhookConfirmsOnce`, `OrderFlowTest::testDuplicateAndOutOfOrderWebhooksAreHarmless`, `HttpTest::testWooWebhookIsVerifiedAndDuplicatesAreIgnored` |
| 6 | Refund reconciliation | **Passed** (hub side) / Pending (gateway) | `OutboxTest::testLostRefundResponseNeverRefundsTwice`, `OrderFlowTest::testReturned…RefundRequestedOnce` |
| 7 | Shipping service availability | **Passed** with fixtures / Pending with a carrier | `ExpressSelectorTest` |
| 8 | Shipping label creation | Pending | Carrier adapter not built |
| 9 | Tracking synchronisation | **Passed** (event handling) / Pending (carrier feed) | `OrderFlowTest::testTrackingEventsAreDedupedAndDeliveryCompletesTheOrder` |
| 10 | Currency handling | **Passed** | `MarketTest` (minor units incl. JPY/KWD, conversion, fallback) |
| 11 | API outage recovery | **Passed** | `OutboxTest::testStorefrontOutageIsRetriedWithBackoffThenSucceeds`, `testWorkerThatDiesMidJobIsRecoveredAfterTheLease`, `ExpiryAndSignatureTest::testUnreachableStorefront…` |
| 12 | Failed email delivery | Pending | Engine not built; failures already surface on Integration health |
| 13 | Role-based permissions | **Passed** | `PrincipalTest`, `HttpTest::testRestrictedActionsAreRefusedOnTheServer`, `testBrandManagerSeesOnlyTheirBrandAndNoCustomerDetails` |
| 14 | Backup restoration | **Passed locally** / Pending on staging | `deploy/restore-check.sh` drill (doc 06 §4) |
| 15 | Mobile checkout | Pending | Storefronts not built (dashboard checked at 390 px: no overflow) |
| 16 | Performance under expected load | Pending | Needs staging and expected volumes |

## OMS brief §21

| # | Workflow | Status | Evidence |
|---|---|---|---|
| 1–3 | Orders from Milan Icons, Significant Sports, Schermo | **Passed** | `OrderFlowTest::testAnOrderFromEachBrandFlowsThroughToDispatch` |
| 4 | Shared SKU bought on two storefronts | **Passed** | `LedgerTest::testSharedSkuAcrossThreeStoresDeductsFromOneRecord` (100 → 94), `OrderFlowTest` |
| 5 | Final unit, simultaneous | **Passed** | see master #1 |
| 6 | Payment fails after order created | **Passed** | `OrderFlowTest::testPaymentFailureAfterOrderCreationReturnsStock` |
| 7 | Order cancelled | **Passed** | `OrderFlowTest::testCancellation…` |
| 8 | Duplicate webhook | **Passed** | see master #5 |
| 9 | Storefront connection fails temporarily | **Passed** | `OutboxTest::testStorefrontOutage…` |
| 10 | Stock adjustment recorded | **Passed** | `LedgerTest::testAdjustmentsRecordPreviousAndResultingQuantities`, `HttpTest::testStockAdjustmentThroughTheDashboardIsRecordedAndUndoable`, browser drawer check |
| 11 | CSV import with invalid rows | **Passed** | `CatalogTest::testCsvImportValidatesEveryRowBeforeWriting`, `HttpTest::testInventoryCsvImportPreviewsBeforeChangingStock` |
| 12 | Returned item marked damaged | **Passed** | `OrderFlowTest::testReturnedItemsDamagedOrApprovedForResale…` |
| 13 | Returned item approved for resale | **Passed** | same |
| 14 | Shipment created and tracked | **Passed** (manual tracking + events) | `OrderFlowTest`, `HttpTest::testOrderWorkflowThroughTheDashboard` |
| 15 | Restricted action without permission | **Passed** | `HttpTest::testRestrictedActionsAreRefusedOnTheServer` (403, stock unchanged) |
| 16 | Missing inventory mapping | **Passed** | `OrderFlowTest::testUnmappedItemIsFlaggedAndBlocksFulfilment` (incl. link + restock + recheck), `HttpTest::testApiRejectsBadSignaturesAndUnlinkedProducts` |
| 17 | Sync job fails and is retried | **Passed** | `OutboxTest::testPersistentFailureBecomesDeadAndCanBeRetriedManually`, `OrderFlowTest::testFailedEventIsRecordedAndRetried` |
| 18 | Backup restored in a test environment | **Passed locally** | restore drill |

## Dashboard / design brief

| Check | Status |
|---|---|
| Product search and filtering, size-level inventory, expand sizes, side-panel adjustment with preview | **Passed** (browser script: expand, open drawer, preview 21→26, confirm, flash with before/after) |
| Order transitions only when valid; skipping a step refused | **Passed** (`HttpTest::testOrderWorkflowThroughTheDashboard`) |
| Order journey shows nothing beyond the server's state | **Passed** (`data-stage` asserted) |
| No horizontal overflow at 1440 / 820 / 390 px, every screen | **Passed** after fixes (sr-only labels escaping table scrollers; grid min-width) |
| Reduced motion | **Passed** (route marker hidden, no animations with `prefers-reduced-motion: reduce`) |
| No console errors | **Passed** on all screens |

Geolocation brief matrix: doc 04 §7 (17 of 20 passed; 3 need the storefront connector or email engine).

## Staging acceptance (to run before launch)
1. Two real browsers on two storefronts buy the last unit at the same moment (both checkout types).
2. Each gateway in test mode: success, failure, delayed (on-hold), duplicate webhook replay, full and partial refund.
3. Carrier sandbox: rate, book, label PDF, tracking events, cancellation.
4. Email: each event per brand; SPF/DKIM/DMARC pass; bounce handled.
5. Webhook disabled in WooCommerce → order-pull backstop recovers orders.
6. Load: expected peak checkouts per minute × 3 with p95 checkout reservation under 300 ms on the chosen server.
7. Restore drill on staging from the off-site copy.

## Known limitations
- WooCommerce hooks, REST client and webhook payloads are tested against WooCommerce-shaped data, not a live store.
- No carrier adapter; shipments are recorded with tracking numbers typed from the carrier portal.
- No notification sending; messages queue and fail visibly until a provider is configured.
- Single warehouse in the UI (the ledger already stores warehouse per row); stock transfers not built.
- Storage locations on pick lists not configured.
- The Stitch reference exists only for the Overview screen (see doc 11).
- Customer-facing return requests are staff-entered; no self-service portal.
