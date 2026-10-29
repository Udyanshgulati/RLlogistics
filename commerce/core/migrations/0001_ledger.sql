-- Central inventory ledger, catalogue mapping and integration plumbing.
-- MariaDB >= 10.6 or MySQL >= 8.0.16 (CHECK constraints enforced, SKIP LOCKED available).
-- Statements are separated by a semicolon at the end of a line; trigger bodies are single statements.

CREATE TABLE brands (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  sku_prefix VARCHAR(8) NOT NULL UNIQUE,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One WooCommerce storefront per row. base_url stays NULL until the client confirms the domain.
CREATE TABLE stores (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand_id SMALLINT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  base_url VARCHAR(255) NULL,
  status ENUM('not_connected','connected','paused') NOT NULL DEFAULT 'not_connected',
  last_seen_at DATETIME(6) NULL,
  CONSTRAINT fk_store_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Address stays NULL until the client supplies it; live shipping is blocked without it.
CREATE TABLE warehouses (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  country_code CHAR(2) NOT NULL,
  city VARCHAR(80) NULL,
  address_json LONGTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A central product. code is the SKU root (e.g. MI-JER-001); owner_brand_id owns the physical stock.
CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  category VARCHAR(80) NULL,
  owner_brand_id SMALLINT UNSIGNED NOT NULL,
  variant_scheme ENUM('none','apparel_size','custom') NOT NULL DEFAULT 'none',
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  image_url VARCHAR(500) NULL,
  hs_code VARCHAR(12) NULL,
  origin_country CHAR(2) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_product_brand FOREIGN KEY (owner_brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per physical stock-keeping unit (one per size for apparel).
CREATE TABLE inventory_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(64) NOT NULL UNIQUE,
  option_label VARCHAR(40) NULL,
  barcode VARCHAR(64) NULL UNIQUE,
  weight_g INT UNSIGNED NULL,
  length_mm INT UNSIGNED NULL,
  width_mm INT UNSIGNED NULL,
  height_mm INT UNSIGNED NULL,
  declared_value_minor BIGINT UNSIGNED NULL,
  declared_currency CHAR(3) NULL,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  KEY idx_item_product (product_id),
  CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A storefront product/variation and the central item it sells. inventory_item_id NULL = seen but not mapped.
-- wc_variation_id is 0 for simple products so the unique key works (NULLs never collide in MySQL).
CREATE TABLE listings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id SMALLINT UNSIGNED NOT NULL,
  inventory_item_id INT UNSIGNED NULL,
  wc_product_id BIGINT UNSIGNED NOT NULL,
  wc_variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  wc_sku VARCHAR(100) NULL,
  wc_name VARCHAR(200) NULL,
  status ENUM('unmapped','mapped','disabled') NOT NULL DEFAULT 'unmapped',
  mapped_by VARCHAR(64) NULL,
  mapped_at DATETIME(6) NULL,
  UNIQUE KEY uq_listing_wc (store_id, wc_product_id, wc_variation_id),
  UNIQUE KEY uq_listing_item (store_id, inventory_item_id),
  KEY idx_listing_item (inventory_item_id),
  CONSTRAINT fk_listing_store FOREIGN KEY (store_id) REFERENCES stores(id),
  CONSTRAINT fk_listing_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Current balances. Every change goes through Ledger and writes a stock_movements row in the same transaction.
-- on_hand counts every physical unit; damaged and quarantine (awaiting inspection) are holds inside on_hand.
-- available = on_hand - reserved - damaged - quarantine, and the CHECK below makes negative availability impossible.
CREATE TABLE stock_levels (
  inventory_item_id INT UNSIGNED NOT NULL,
  warehouse_id SMALLINT UNSIGNED NOT NULL,
  on_hand INT NOT NULL DEFAULT 0,
  reserved INT NOT NULL DEFAULT 0,
  damaged INT NOT NULL DEFAULT 0,
  quarantine INT NOT NULL DEFAULT 0,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (inventory_item_id, warehouse_id),
  CONSTRAINT ck_levels_nonneg CHECK (on_hand >= 0 AND reserved >= 0 AND damaged >= 0 AND quarantine >= 0),
  CONSTRAINT ck_levels_available CHECK (reserved + damaged + quarantine <= on_hand),
  CONSTRAINT fk_level_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id),
  CONSTRAINT fk_level_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only ledger. The *_after columns give previous/resulting quantities for the history screen.
CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_item_id INT UNSIGNED NOT NULL,
  warehouse_id SMALLINT UNSIGNED NOT NULL,
  type VARCHAR(32) NOT NULL,
  d_on_hand INT NOT NULL DEFAULT 0,
  d_reserved INT NOT NULL DEFAULT 0,
  d_damaged INT NOT NULL DEFAULT 0,
  d_quarantine INT NOT NULL DEFAULT 0,
  on_hand_after INT NOT NULL,
  reserved_after INT NOT NULL,
  damaged_after INT NOT NULL,
  quarantine_after INT NOT NULL,
  reason VARCHAR(500) NULL,
  ref_type VARCHAR(32) NULL,
  ref_id VARCHAR(64) NULL,
  actor VARCHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  KEY idx_mov_item (inventory_item_id, created_at),
  KEY idx_mov_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER stock_movements_no_update BEFORE UPDATE ON stock_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_movements is append-only';

CREATE TRIGGER stock_movements_no_delete BEFORE DELETE ON stock_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_movements is append-only';

-- One reservation per storefront order. idempotency_key makes retried checkout calls safe.
CREATE TABLE reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id SMALLINT UNSIGNED NOT NULL,
  order_ref VARCHAR(64) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL UNIQUE,
  state ENUM('held','confirmed','partially_fulfilled','fulfilled','released','expired') NOT NULL,
  expires_at DATETIME(6) NULL,
  hard_expires_at DATETIME(6) NULL,
  payment_ref VARCHAR(128) NULL,
  exception VARCHAR(48) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  KEY idx_res_expiry (state, expires_at),
  KEY idx_res_order (store_id, order_ref),
  CONSTRAINT fk_res_store FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservation_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id INT UNSIGNED NOT NULL,
  warehouse_id SMALLINT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL,
  fulfilled_qty INT UNSIGNED NOT NULL DEFAULT 0,
  released_qty INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_resline (reservation_id, inventory_item_id, warehouse_id),
  CONSTRAINT ck_resline_qty CHECK (qty > 0 AND fulfilled_qty + released_qty <= qty),
  CONSTRAINT fk_resline_res FOREIGN KEY (reservation_id) REFERENCES reservations(id),
  CONSTRAINT fk_resline_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One open row per unresolved low-stock condition, so alerts are sent once until stock recovers.
CREATE TABLE low_stock_alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventory_item_id INT UNSIGNED NOT NULL,
  kind ENUM('low','out') NOT NULL,
  available_at_open INT NOT NULL,
  opened_at DATETIME(6) NOT NULL,
  resolved_at DATETIME(6) NULL,
  open_marker TINYINT GENERATED ALWAYS AS (IF(resolved_at IS NULL, 1, NULL)) STORED,
  UNIQUE KEY uq_open_alert (inventory_item_id, open_marker),
  CONSTRAINT fk_alert_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inbound events from storefronts and carriers. (source, event_key) makes duplicates harmless.
CREATE TABLE inbox_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(64) NOT NULL,
  event_key VARCHAR(191) NOT NULL,
  topic VARCHAR(64) NOT NULL,
  payload LONGTEXT NOT NULL,
  status ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(1000) NULL,
  received_at DATETIME(6) NOT NULL,
  processed_at DATETIME(6) NULL,
  UNIQUE KEY uq_inbox (source, event_key),
  KEY idx_inbox_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbound work (stock pushes, notifications, carrier calls) written in the same transaction as the change.
-- dedupe_key coalesces pending work, e.g. one pending stock push per listing; it is cleared when a worker claims the row.
CREATE TABLE outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic VARCHAR(64) NOT NULL,
  dedupe_key VARCHAR(191) NULL UNIQUE,
  payload LONGTEXT NOT NULL,
  status ENUM('pending','processing','done','dead') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME(6) NOT NULL,
  locked_until DATETIME(6) NULL,
  last_error VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  KEY idx_outbox_due (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor VARCHAR(64) NOT NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(32) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  details LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME(6) NOT NULL,
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
