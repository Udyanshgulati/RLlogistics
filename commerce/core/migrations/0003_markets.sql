-- Exchange rates (display conversion only) and explicit market prices.

-- One row per currency, rate expressed per 1 unit of the source's base currency (EUR for the ECB feed).
-- Overwritten on each successful fetch; the order record keeps the rate actually used, so history never changes.
CREATE TABLE fx_rates (
  currency CHAR(3) NOT NULL PRIMARY KEY,
  base CHAR(3) NOT NULL,
  rate DECIMAL(24,10) NOT NULL,
  source VARCHAR(40) NOT NULL,
  as_of DATE NOT NULL,
  fetched_at DATETIME(6) NOT NULL,
  CONSTRAINT ck_fx_positive CHECK (rate > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A price a brand has set for a market currency. When present it wins over conversion and does not move with exchange rates.
CREATE TABLE market_prices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id SMALLINT UNSIGNED NOT NULL,
  wc_product_id BIGINT UNSIGNED NOT NULL,
  wc_variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_market_price (store_id, wc_product_id, wc_variation_id, currency),
  CONSTRAINT fk_mp_store FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders keep the conversion used for any displayed price, for reporting.
ALTER TABLE orders ADD COLUMN display_fx_rate DECIMAL(24,10) NULL AFTER display_currency, ADD COLUMN display_fx_as_of DATE NULL AFTER display_fx_rate;
