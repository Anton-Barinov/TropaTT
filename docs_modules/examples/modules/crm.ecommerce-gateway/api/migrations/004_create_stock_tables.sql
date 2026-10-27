-- crm.ecommerce-gateway: stock intake (the CRM side of the connector syncStock).
--
-- The OpenCart 2.3/3.0/4.0 connectors expose a signed `syncStock` route
-- (`mode=pull` — paged catalogue snapshot, `mode=push` — apply a stock batch),
-- but the CRM had no counterpart: a store could compute stock and had nowhere
-- to send it. This table is the receiving side: one row per store+SKU, updated
-- idempotently, plus a journal of sync packets for diagnostics/reconciliation.

CREATE TABLE IF NOT EXISTS `ecommerce_stock` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(191) NOT NULL,
  `title` VARCHAR(255) NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `price_minor` BIGINT NULL,
  `currency` VARCHAR(8) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `source` VARCHAR(16) NOT NULL DEFAULT 'push',
  `synced_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_stock_store_sku` (`store_id`, `sku`),
  KEY `idx_ecommerce_stock_store_synced` (`store_id`, `synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_stock_sync_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `direction` VARCHAR(8) NOT NULL DEFAULT 'push',
  `items_total` INT NOT NULL DEFAULT 0,
  `items_applied` INT NOT NULL DEFAULT 0,
  `items_skipped` INT NOT NULL DEFAULT 0,
  `items_failed` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(16) NOT NULL DEFAULT 'ok',
  `error` TEXT NULL,
  `request_id` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ecommerce_stock_sync_store_created` (`store_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
