# 02 · Database

MariaDB ≥ 10.6 or MySQL ≥ 8.0.16, InnoDB, utf8mb4, `READ COMMITTED` sessions (needs ROW or MIXED binary logging). Migrations: `core/migrations/NNNN_*.sql`, applied by `bin/hub migrate`, each recorded in `schema_migrations` only after it fully succeeds.

Generated from the migrated schema on 29 Oct 2026.

## `audit_log`

Who did what: mappings, imports, refunds, role changes, customer-detail views, sign-ins.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `actor` | varchar(64) |  |
| `action` | varchar(64) |  |
| `entity_type` | varchar(32) |  |
| `entity_id` | varchar(64) |  |
| `details` | longtext | yes |
| `ip` | varchar(45) | yes |
| `created_at` | datetime(6) |  |

## `brands`

The three brands. `sku_prefix` drives SKU validation (MI / SS / SCH are working values; confirm with the client).

| Column | Type | Null |
|---|---|---|
| `id` | smallint(5) unsigned |  |
| `code` | varchar(32) |  |
| `name` | varchar(120) |  |
| `sku_prefix` | varchar(8) |  |
| `created_at` | datetime(6) |  |

## `fx_rates`

Display-only exchange rates (ECB), overwritten daily.

| Column | Type | Null |
|---|---|---|
| `currency` | char(3) |  |
| `base` | char(3) |  |
| `rate` | decimal(24,10) |  |
| `source` | varchar(40) |  |
| `as_of` | date |  |
| `fetched_at` | datetime(6) |  |

## `inbox_events`

Every inbound event, unique on (source, event_key): duplicates are dropped; failures are retried from Integration health.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `source` | varchar(64) |  |
| `event_key` | varchar(191) |  |
| `topic` | varchar(64) |  |
| `payload` | longtext |  |
| `status` | enum('received','processed','failed','ignored') |  |
| `attempts` | int(10) unsigned |  |
| `last_error` | varchar(1000) | yes |
| `received_at` | datetime(6) |  |
| `processed_at` | datetime(6) | yes |

## `inventory_items`

One row per stock-keeping unit (one per size). Weight/dimensions feed shipping quotes; `reorder_level` drives Low stock.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `product_id` | int(10) unsigned |  |
| `sku` | varchar(64) |  |
| `option_label` | varchar(40) | yes |
| `barcode` | varchar(64) | yes |
| `weight_g` | int(10) unsigned | yes |
| `length_mm` | int(10) unsigned | yes |
| `width_mm` | int(10) unsigned | yes |
| `height_mm` | int(10) unsigned | yes |
| `declared_value_minor` | bigint(20) unsigned | yes |
| `declared_currency` | char(3) | yes |
| `reorder_level` | int(10) unsigned |  |
| `status` | enum('active','archived') |  |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `listings`

Storefront product/variation → central item. `inventory_item_id` NULL = seen but not linked. Unique per store+WC ids and per store+item.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `store_id` | smallint(5) unsigned |  |
| `inventory_item_id` | int(10) unsigned | yes |
| `wc_product_id` | bigint(20) unsigned |  |
| `wc_variation_id` | bigint(20) unsigned |  |
| `wc_sku` | varchar(100) | yes |
| `wc_name` | varchar(200) | yes |
| `status` | enum('unmapped','mapped','disabled') |  |
| `mapped_by` | varchar(64) | yes |
| `mapped_at` | datetime(6) | yes |

## `low_stock_alerts`

At most one open alert per item (generated-column unique key), so an unresolved condition alerts once.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `inventory_item_id` | int(10) unsigned |  |
| `kind` | enum('low','out') |  |
| `available_at_open` | int(11) |  |
| `opened_at` | datetime(6) |  |
| `resolved_at` | datetime(6) | yes |
| `open_marker` | tinyint(4) | yes |

## `market_prices`

Prices a brand sets for a market currency; win over conversion.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `store_id` | smallint(5) unsigned |  |
| `wc_product_id` | bigint(20) unsigned |  |
| `wc_variation_id` | bigint(20) unsigned |  |
| `currency` | char(3) |  |
| `amount_minor` | bigint(20) unsigned |  |
| `updated_by` | varchar(64) |  |
| `updated_at` | datetime(6) |  |

## `orders`

Mirror of storefront orders. Money in integer minor units with the order currency; never summed across currencies.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `store_id` | smallint(5) unsigned |  |
| `wc_order_id` | bigint(20) unsigned |  |
| `order_number` | varchar(40) |  |
| `placed_at` | datetime(6) |  |
| `wc_status` | varchar(32) |  |
| `payment_status` | enum('pending','paid','failed','cancelled','refunded','partially_refunded') |  |
| `fulfilment_status` | enum('unfulfilled','processing','picked','packed','partially_shipped','shipped','delivered','cancelled') |  |
| `currency` | char(3) |  |
| `subtotal_minor` | bigint(20) |  |
| `discount_minor` | bigint(20) |  |
| `shipping_minor` | bigint(20) |  |
| `tax_minor` | bigint(20) |  |
| `total_minor` | bigint(20) |  |
| `refunded_minor` | bigint(20) |  |
| `payment_gateway` | varchar(64) | yes |
| `transaction_ref` | varchar(128) | yes |
| `display_currency` | char(3) | yes |
| `display_fx_rate` | decimal(24,10) | yes |
| `display_fx_as_of` | date | yes |
| `market_code` | varchar(16) | yes |
| `customer_name` | varchar(200) | yes |
| `customer_email` | varchar(254) | yes |
| `customer_phone` | varchar(40) | yes |
| `ship_country` | char(2) | yes |
| `shipping_address` | longtext | yes |
| `billing_address` | longtext | yes |
| `shipping_service_requested` | varchar(120) | yes |
| `reservation_id` | bigint(20) unsigned | yes |
| `attention` | varchar(48) | yes |
| `source_modified_at` | datetime(6) |  |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `order_events`

The order timeline.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `order_id` | bigint(20) unsigned |  |
| `type` | varchar(40) |  |
| `message` | varchar(500) |  |
| `actor` | varchar(64) |  |
| `created_at` | datetime(6) |  |

## `order_lines`

Lines with storefront product ids, so a line can be linked to stock after the order arrived.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `order_id` | bigint(20) unsigned |  |
| `wc_line_id` | bigint(20) unsigned |  |
| `wc_product_id` | bigint(20) unsigned |  |
| `wc_variation_id` | bigint(20) unsigned |  |
| `listing_id` | int(10) unsigned | yes |
| `inventory_item_id` | int(10) unsigned | yes |
| `sku` | varchar(100) | yes |
| `name` | varchar(200) |  |
| `option_label` | varchar(40) | yes |
| `qty` | int(10) unsigned |  |
| `unit_price_minor` | bigint(20) |  |
| `line_total_minor` | bigint(20) |  |
| `qty_picked` | int(10) unsigned |  |
| `qty_shipped` | int(10) unsigned |  |

## `outbox`

Outbound work written in the same transaction as the change. `dedupe_key` coalesces stock pushes and prevents duplicate notifications.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `topic` | varchar(64) |  |
| `dedupe_key` | varchar(191) | yes |
| `payload` | longtext |  |
| `status` | enum('pending','processing','done','dead') |  |
| `attempts` | int(10) unsigned |  |
| `next_attempt_at` | datetime(6) |  |
| `locked_until` | datetime(6) | yes |
| `last_error` | varchar(1000) | yes |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `products`

Central product. `code` is the SKU root (e.g. MI-JER-001); `owner_brand_id` owns the physical stock; HS code and origin for customs.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `code` | varchar(40) |  |
| `name` | varchar(200) |  |
| `category` | varchar(80) | yes |
| `owner_brand_id` | smallint(5) unsigned |  |
| `variant_scheme` | enum('none','apparel_size','custom') |  |
| `status` | enum('draft','active','archived') |  |
| `image_url` | varchar(500) | yes |
| `hs_code` | varchar(12) | yes |
| `origin_country` | char(2) | yes |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `purchase_orders`

Draft → ordered → partially received → received.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `po_number` | varchar(32) |  |
| `supplier_id` | int(10) unsigned |  |
| `warehouse_id` | smallint(5) unsigned |  |
| `status` | enum('draft','ordered','partially_received','received','cancelled') |  |
| `expected_at` | date | yes |
| `created_by` | varchar(64) |  |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `purchase_order_lines`

Ordered / received / damaged / missing; incoming = ordered − received − missing.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `purchase_order_id` | int(10) unsigned |  |
| `inventory_item_id` | int(10) unsigned |  |
| `qty_ordered` | int(10) unsigned |  |
| `qty_received` | int(10) unsigned |  |
| `qty_damaged` | int(10) unsigned |  |
| `qty_missing` | int(10) unsigned |  |

## `rate_limits`

Fixed-window counters (failed sign-ins, API calls).

| Column | Type | Null |
|---|---|---|
| `bucket` | varchar(191) |  |
| `window_start` | datetime(6) |  |
| `hits` | int(10) unsigned |  |

## `reservations`

One per storefront order; `idempotency_key` makes retried checkouts safe. `exception` flags payments that arrived after the hold ended.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `store_id` | smallint(5) unsigned |  |
| `order_ref` | varchar(64) |  |
| `idempotency_key` | varchar(128) |  |
| `state` | enum('held','confirmed','partially_fulfilled','fulfilled','released','expired') |  |
| `expires_at` | datetime(6) | yes |
| `hard_expires_at` | datetime(6) | yes |
| `payment_ref` | varchar(128) | yes |
| `exception` | varchar(48) | yes |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `reservation_lines`

Units per item; fulfilled and released counters make partial shipping and partial cancellation exact.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `reservation_id` | bigint(20) unsigned |  |
| `inventory_item_id` | int(10) unsigned |  |
| `warehouse_id` | smallint(5) unsigned |  |
| `qty` | int(10) unsigned |  |
| `fulfilled_qty` | int(10) unsigned |  |
| `released_qty` | int(10) unsigned |  |

## `returns`

RMA, reason, status, refund status (requested → succeeded only when the storefront confirms).

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `order_id` | bigint(20) unsigned |  |
| `rma_number` | varchar(32) |  |
| `kind` | enum('return','exchange') |  |
| `status` | enum('requested','received','inspected','closed','cancelled') |  |
| `refund_status` | enum('not_required','not_requested','requested','succeeded','failed') |  |
| `refund_ref` | varchar(128) | yes |
| `reason` | varchar(500) |  |
| `created_by` | varchar(64) |  |
| `created_at` | datetime(6) |  |
| `updated_at` | datetime(6) |  |

## `return_lines`

Running totals received / sellable / damaged; stock moves only by the difference.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `return_id` | bigint(20) unsigned |  |
| `order_line_id` | bigint(20) unsigned |  |
| `inventory_item_id` | int(10) unsigned |  |
| `qty` | int(10) unsigned |  |
| `qty_received` | int(10) unsigned |  |
| `qty_sellable` | int(10) unsigned |  |
| `qty_damaged` | int(10) unsigned |  |

## `saved_views`

Saved filters per user.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `user_id` | int(10) unsigned |  |
| `page` | varchar(32) |  |
| `name` | varchar(60) |  |
| `query` | varchar(1000) |  |
| `created_at` | datetime(6) |  |

## `schema_migrations`

Applied migration versions.

| Column | Type | Null |
|---|---|---|
| `version` | varchar(64) |  |
| `applied_at` | datetime(6) |  |

## `settings`

Small key/value settings (e.g. demo-data flag).

| Column | Type | Null |
|---|---|---|
| `name` | varchar(64) |  |
| `value` | longtext |  |
| `updated_by` | varchar(64) |  |
| `updated_at` | datetime(6) |  |

## `shipments`

Carrier, service, tracking, label reference, status; estimated delivery only with its source.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `order_id` | bigint(20) unsigned |  |
| `carrier` | varchar(40) |  |
| `service` | varchar(80) |  |
| `tracking_number` | varchar(80) | yes |
| `label_ref` | varchar(255) | yes |
| `status` | enum('draft','label_created','dispatched','in_transit','exception','delivered','cancelled') |  |
| `cost_minor` | bigint(20) | yes |
| `cost_currency` | char(3) | yes |
| `estimated_delivery` | date | yes |
| `estimate_source` | varchar(40) | yes |
| `created_by` | varchar(64) |  |
| `created_at` | datetime(6) |  |
| `dispatched_at` | datetime(6) | yes |
| `delivered_at` | datetime(6) | yes |
| `updated_at` | datetime(6) |  |

## `shipment_lines`

Units of each order line in a shipment (partial fulfilment).

| Column | Type | Null |
|---|---|---|
| `shipment_id` | bigint(20) unsigned |  |
| `order_line_id` | bigint(20) unsigned |  |
| `qty` | int(10) unsigned |  |

## `stock_levels`

Current balances per item and warehouse. CHECK: all buckets ≥ 0 and reserved + damaged + quarantine ≤ on_hand. Only the Ledger writes here.

| Column | Type | Null |
|---|---|---|
| `inventory_item_id` | int(10) unsigned |  |
| `warehouse_id` | smallint(5) unsigned |  |
| `on_hand` | int(11) |  |
| `reserved` | int(11) |  |
| `damaged` | int(11) |  |
| `quarantine` | int(11) |  |
| `updated_at` | datetime(6) |  |

## `stock_movements`

Append-only history (triggers block UPDATE/DELETE). Deltas plus resulting balances, actor, reason, reference.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `inventory_item_id` | int(10) unsigned |  |
| `warehouse_id` | smallint(5) unsigned |  |
| `type` | varchar(32) |  |
| `d_on_hand` | int(11) |  |
| `d_reserved` | int(11) |  |
| `d_damaged` | int(11) |  |
| `d_quarantine` | int(11) |  |
| `on_hand_after` | int(11) |  |
| `reserved_after` | int(11) |  |
| `damaged_after` | int(11) |  |
| `quarantine_after` | int(11) |  |
| `reason` | varchar(500) | yes |
| `ref_type` | varchar(32) | yes |
| `ref_id` | varchar(64) | yes |
| `actor` | varchar(64) |  |
| `created_at` | datetime(6) |  |

## `stores`

One WooCommerce storefront per row. `base_url` is NULL until the client confirms the domain; secrets are never stored here.

| Column | Type | Null |
|---|---|---|
| `id` | smallint(5) unsigned |  |
| `brand_id` | smallint(5) unsigned |  |
| `code` | varchar(32) |  |
| `name` | varchar(120) |  |
| `base_url` | varchar(255) | yes |
| `status` | enum('not_connected','connected','paused') |  |
| `last_seen_at` | datetime(6) | yes |

## `suppliers`

Lightweight supplier records.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `name` | varchar(160) |  |
| `contact_email` | varchar(254) | yes |
| `contact_phone` | varchar(40) | yes |
| `notes` | varchar(1000) | yes |
| `created_at` | datetime(6) |  |

## `tracking_events`

Carrier scans, unique per shipment+event key.

| Column | Type | Null |
|---|---|---|
| `id` | bigint(20) unsigned |  |
| `shipment_id` | bigint(20) unsigned |  |
| `event_key` | varchar(191) |  |
| `status` | varchar(32) |  |
| `description` | varchar(500) | yes |
| `location` | varchar(120) | yes |
| `occurred_at` | datetime(6) |  |

## `users`

Staff accounts. argon2id hashes; lockout fields; one-time set-up link stored as a hash.

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `email` | varchar(254) |  |
| `name` | varchar(120) |  |
| `password_hash` | varchar(255) |  |
| `status` | enum('active','disabled') |  |
| `failed_logins` | int(10) unsigned |  |
| `locked_until` | datetime(6) | yes |
| `last_login_at` | datetime(6) | yes |
| `created_at` | datetime(6) |  |
| `setup_token_hash` | char(64) | yes |
| `setup_expires_at` | datetime(6) | yes |

## `user_roles`

Role per user, optionally limited to one brand (`brand_id` NULL = all brands).

| Column | Type | Null |
|---|---|---|
| `id` | int(10) unsigned |  |
| `user_id` | int(10) unsigned |  |
| `role` | varchar(32) |  |
| `brand_id` | smallint(5) unsigned | yes |

## `warehouses`

Delhi warehouse today; more rows later need no schema change. Address NULL until supplied.

| Column | Type | Null |
|---|---|---|
| `id` | smallint(5) unsigned |  |
| `code` | varchar(16) |  |
| `name` | varchar(120) |  |
| `country_code` | char(2) |  |
| `city` | varchar(80) | yes |
| `address_json` | longtext | yes |
| `is_active` | tinyint(1) |  |
