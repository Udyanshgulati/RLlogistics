-- Dashboard support: settings, saved views, account setup links.

CREATE TABLE settings (
  name VARCHAR(64) NOT NULL PRIMARY KEY,
  value LONGTEXT NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  updated_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_views (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  page VARCHAR(32) NOT NULL,
  name VARCHAR(60) NOT NULL,
  query VARCHAR(1000) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_view (user_id, page, name),
  CONSTRAINT fk_view_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A one-time link an owner hands to a new team member to set their password. Only the hash is stored.
ALTER TABLE users ADD COLUMN setup_token_hash CHAR(64) NULL, ADD COLUMN setup_expires_at DATETIME(6) NULL;

-- Storefront product ids on each order line, so a line can be linked to stock after the order arrived.
ALTER TABLE order_lines ADD COLUMN wc_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER wc_line_id, ADD COLUMN wc_variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER wc_product_id;
