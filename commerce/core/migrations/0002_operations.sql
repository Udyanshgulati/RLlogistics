-- Orders mirrored from the storefronts, fulfilment, returns, purchasing and team access.
-- Money is stored as integer minor units plus an ISO 4217 currency; amounts in different currencies are never summed.

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id SMALLINT UNSIGNED NOT NULL,
  wc_order_id BIGINT UNSIGNED NOT NULL,
  order_number VARCHAR(40) NOT NULL,
  placed_at DATETIME(6) NOT NULL,
  wc_status VARCHAR(32) NOT NULL,
  payment_status ENUM('pending','paid','failed','cancelled','refunded','partially_refunded') NOT NULL,
  fulfilment_status ENUM('unfulfilled','processing','picked','packed','partially_shipped','shipped','delivered','cancelled') NOT NULL DEFAULT 'unfulfilled',
  currency CHAR(3) NOT NULL,
  subtotal_minor BIGINT NOT NULL DEFAULT 0,
  discount_minor BIGINT NOT NULL DEFAULT 0,
  shipping_minor BIGINT NOT NULL DEFAULT 0,
  tax_minor BIGINT NOT NULL DEFAULT 0,
  total_minor BIGINT NOT NULL,
  refunded_minor BIGINT NOT NULL DEFAULT 0,
  payment_gateway VARCHAR(64) NULL,
  transaction_ref VARCHAR(128) NULL,
  display_currency CHAR(3) NULL,
  market_code VARCHAR(16) NULL,
  customer_name VARCHAR(200) NULL,
  customer_email VARCHAR(254) NULL,
  customer_phone VARCHAR(40) NULL,
  ship_country CHAR(2) NULL,
  shipping_address LONGTEXT NULL,
  billing_address LONGTEXT NULL,
  shipping_service_requested VARCHAR(120) NULL,
  reservation_id BIGINT UNSIGNED NULL,
  attention VARCHAR(48) NULL,
  source_modified_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_order_store (store_id, wc_order_id),
  KEY idx_order_placed (placed_at),
  KEY idx_order_pay (payment_status),
  KEY idx_order_ful (fulfilment_status),
  KEY idx_order_country (ship_country),
  KEY idx_order_attention (attention),
  CONSTRAINT fk_order_store FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  wc_line_id BIGINT UNSIGNED NOT NULL,
  listing_id INT UNSIGNED NULL,
  inventory_item_id INT UNSIGNED NULL,
  sku VARCHAR(100) NULL,
  name VARCHAR(200) NOT NULL,
  option_label VARCHAR(40) NULL,
  qty INT UNSIGNED NOT NULL,
  unit_price_minor BIGINT NOT NULL,
  line_total_minor BIGINT NOT NULL,
  qty_picked INT UNSIGNED NOT NULL DEFAULT 0,
  qty_shipped INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_line (order_id, wc_line_id),
  CONSTRAINT fk_line_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The order timeline shown on the order page.
CREATE TABLE order_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  message VARCHAR(500) NOT NULL,
  actor VARCHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  KEY idx_oe_order (order_id, created_at),
  CONSTRAINT fk_oe_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  carrier VARCHAR(40) NOT NULL,
  service VARCHAR(80) NOT NULL,
  tracking_number VARCHAR(80) NULL,
  label_ref VARCHAR(255) NULL,
  status ENUM('draft','label_created','dispatched','in_transit','exception','delivered','cancelled') NOT NULL DEFAULT 'draft',
  cost_minor BIGINT NULL,
  cost_currency CHAR(3) NULL,
  estimated_delivery DATE NULL,
  estimate_source VARCHAR(40) NULL,
  created_by VARCHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  dispatched_at DATETIME(6) NULL,
  delivered_at DATETIME(6) NULL,
  updated_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_tracking (carrier, tracking_number),
  KEY idx_ship_status (status),
  CONSTRAINT fk_ship_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipment_lines (
  shipment_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL,
  PRIMARY KEY (shipment_id, order_line_id),
  CONSTRAINT fk_sl_ship FOREIGN KEY (shipment_id) REFERENCES shipments(id),
  CONSTRAINT fk_sl_line FOREIGN KEY (order_line_id) REFERENCES order_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carrier tracking events; the unique key drops replays of the same carrier event.
CREATE TABLE tracking_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shipment_id BIGINT UNSIGNED NOT NULL,
  event_key VARCHAR(191) NOT NULL,
  status VARCHAR(32) NOT NULL,
  description VARCHAR(500) NULL,
  location VARCHAR(120) NULL,
  occurred_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_track (shipment_id, event_key),
  CONSTRAINT fk_te_ship FOREIGN KEY (shipment_id) REFERENCES shipments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE returns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  rma_number VARCHAR(32) NOT NULL UNIQUE,
  kind ENUM('return','exchange') NOT NULL DEFAULT 'return',
  status ENUM('requested','received','inspected','closed','cancelled') NOT NULL DEFAULT 'requested',
  refund_status ENUM('not_required','not_requested','requested','succeeded','failed') NOT NULL DEFAULT 'not_requested',
  refund_ref VARCHAR(128) NULL,
  reason VARCHAR(500) NOT NULL,
  created_by VARCHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_ret_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- received/inspected counters make receipt and inspection idempotent: stock moves only by the difference.
CREATE TABLE return_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL,
  qty_received INT UNSIGNED NOT NULL DEFAULT 0,
  qty_sellable INT UNSIGNED NOT NULL DEFAULT 0,
  qty_damaged INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT ck_rl CHECK (qty_received <= qty AND qty_sellable + qty_damaged <= qty_received),
  UNIQUE KEY uq_rl (return_id, order_line_id),
  CONSTRAINT fk_rl_ret FOREIGN KEY (return_id) REFERENCES returns(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  contact_email VARCHAR(254) NULL,
  contact_phone VARCHAR(40) NULL,
  notes VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(32) NOT NULL UNIQUE,
  supplier_id INT UNSIGNED NOT NULL,
  warehouse_id SMALLINT UNSIGNED NOT NULL,
  status ENUM('draft','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  expected_at DATE NULL,
  created_by VARCHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incoming = qty_ordered - qty_received - qty_missing on lines of 'ordered'/'partially_received' POs. Never counted as available.
CREATE TABLE purchase_order_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id INT UNSIGNED NOT NULL,
  inventory_item_id INT UNSIGNED NOT NULL,
  qty_ordered INT UNSIGNED NOT NULL,
  qty_received INT UNSIGNED NOT NULL DEFAULT 0,
  qty_damaged INT UNSIGNED NOT NULL DEFAULT 0,
  qty_missing INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT ck_pol CHECK (qty_ordered > 0 AND qty_received + qty_missing <= qty_ordered AND qty_damaged <= qty_received),
  UNIQUE KEY uq_pol (purchase_order_id, inventory_item_id),
  CONSTRAINT fk_pol_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
  CONSTRAINT fk_pol_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  failed_logins INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME(6) NULL,
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- brand_id NULL = the role applies to all brands.
CREATE TABLE user_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  role VARCHAR(32) NOT NULL,
  brand_id SMALLINT UNSIGNED NULL,
  UNIQUE KEY uq_user_role (user_id, role, brand_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_ur_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window counters for login and API rate limits.
CREATE TABLE rate_limits (
  bucket VARCHAR(191) NOT NULL PRIMARY KEY,
  window_start DATETIME(6) NOT NULL,
  hits INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
