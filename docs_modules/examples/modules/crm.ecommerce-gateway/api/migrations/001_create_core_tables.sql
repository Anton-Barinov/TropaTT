-- crm.ecommerce-gateway: core schema. MySQL/InnoDB only.
--
-- Secret storage note: the ingestion protocol (E-COM-01 §4) signs requests with
-- HMAC-SHA256, so the server must be able to reproduce the shared secret.
-- A one-way hash (Argon2/HMAC) would make verification impossible, therefore the
-- secret is stored reversibly encrypted (AES-256-GCM, key derived from
-- APP_SECRET) and only a short hint is kept in clear text for the UI.

CREATE TABLE IF NOT EXISTS `ecommerce_stores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,

  `name` VARCHAR(255) NOT NULL,
  `cms_type` VARCHAR(32) NOT NULL DEFAULT 'custom',
  `store_url` VARCHAR(512) NULL,
  `description` TEXT NULL,

  `api_key` VARCHAR(64) NOT NULL,
  `api_secret_encrypted` TEXT NOT NULL,
  `api_secret_hint` VARCHAR(12) NULL,
  `secondary_api_secret_encrypted` TEXT NULL,
  `secondary_secret_expires_at` DATETIME NULL,
  `secret_rotated_at` DATETIME NULL,

  `ip_whitelist` TEXT NULL,
  `rate_limit_per_minute` INT UNSIGNED NOT NULL DEFAULT 120,

  `webhook_url` VARCHAR(512) NULL,
  `webhook_secret_encrypted` TEXT NULL,

  `status` ENUM('active','paused','disabled') NOT NULL DEFAULT 'active',
  `locale` VARCHAR(16) NOT NULL DEFAULT 'ru-ru',
  `settings_json` TEXT NULL,

  `created_by_user_id` INT UNSIGNED NULL,
  `last_ingest_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `row_version` INT UNSIGNED NOT NULL DEFAULT 1,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_stores_public_id` (`public_id`),
  UNIQUE KEY `uq_ecommerce_stores_api_key` (`api_key`),
  KEY `idx_ecommerce_stores_status` (`status`, `deleted_at`),
  KEY `idx_ecommerce_stores_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_store_forms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,

  `form_id` VARCHAR(128) NOT NULL,
  `title` VARCHAR(255) NULL,
  `ingest_type` VARCHAR(32) NOT NULL DEFAULT 'form',

  `field_map_json` TEXT NULL,
  `defaults_json` TEXT NULL,

  `is_lead` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_store_forms_public_id` (`public_id`),
  UNIQUE KEY `uq_ecommerce_store_forms_store_form` (`store_id`, `form_id`),
  KEY `idx_ecommerce_store_forms_active` (`store_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_status_mappings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,

  `entity_scope` VARCHAR(32) NOT NULL DEFAULT 'intake',
  `external_status` VARCHAR(128) NOT NULL,
  `crm_status_code` VARCHAR(64) NOT NULL,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_status_mappings_public_id` (`public_id`),
  UNIQUE KEY `uq_ecommerce_status_mappings_scope_external` (`store_id`, `entity_scope`, `external_status`),
  KEY `idx_ecommerce_status_mappings_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_idempotency` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` INT UNSIGNED NOT NULL,

  `idempotency_key` VARCHAR(191) NOT NULL,
  `ingest_type` VARCHAR(32) NOT NULL,
  `external_id` VARCHAR(191) NOT NULL,
  `request_hash` CHAR(64) NULL,

  `entity_type` VARCHAR(64) NULL,
  `entity_public_id` VARCHAR(64) NULL,

  `created_at` DATETIME NOT NULL,
  `expires_at` DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_idempotency_store_key` (`store_id`, `idempotency_key`),
  KEY `idx_ecommerce_idempotency_entity` (`entity_type`, `entity_public_id`),
  KEY `idx_ecommerce_idempotency_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_ingest_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,
  `request_id` VARCHAR(64) NOT NULL,

  `store_id` INT UNSIGNED NOT NULL,
  `ingest_type` VARCHAR(32) NOT NULL,
  `external_id` VARCHAR(191) NULL,
  `external_id_synthetic` TINYINT(1) NOT NULL DEFAULT 0,

  `status` VARCHAR(32) NOT NULL DEFAULT 'received',
  `http_status` SMALLINT UNSIGNED NULL,
  `error_code` VARCHAR(64) NULL,

  `entity_type` VARCHAR(64) NULL,
  `entity_public_id` VARCHAR(64) NULL,

  `payload_json` MEDIUMTEXT NULL,
  `payload_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms` INT UNSIGNED NULL,

  `ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(512) NULL,

  `created_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_ingest_events_public_id` (`public_id`),
  KEY `idx_ecommerce_ingest_events_store_created` (`store_id`, `created_at`),
  KEY `idx_ecommerce_ingest_events_store_type_external` (`store_id`, `ingest_type`, `external_id`),
  KEY `idx_ecommerce_ingest_events_request_id` (`request_id`),
  KEY `idx_ecommerce_ingest_events_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_order_sync_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,

  `store_id` INT UNSIGNED NOT NULL,
  `external_order_id` VARCHAR(191) NULL,
  `crm_task_id` BIGINT UNSIGNED NULL,
  `intake_item_id` BIGINT UNSIGNED NULL,

  `direction` VARCHAR(16) NOT NULL DEFAULT 'inbound',
  `status` VARCHAR(32) NOT NULL DEFAULT 'received',
  `http_code` SMALLINT UNSIGNED NULL,

  `request_payload` MEDIUMTEXT NULL,
  `response_payload` MEDIUMTEXT NULL,
  `error_message` TEXT NULL,
  `ip_address` VARCHAR(64) NULL,

  `created_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_order_sync_log_public_id` (`public_id`),
  KEY `idx_ecommerce_order_sync_log_store_created` (`store_id`, `created_at`),
  KEY `idx_ecommerce_order_sync_log_external_order` (`store_id`, `external_order_id`),
  KEY `idx_ecommerce_order_sync_log_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_security_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,

  `store_id` INT UNSIGNED NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `severity` VARCHAR(16) NOT NULL DEFAULT 'warning',

  `ip_address` VARCHAR(64) NULL,
  `user_agent` VARCHAR(512) NULL,
  `details_json` TEXT NULL,

  `created_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_security_log_public_id` (`public_id`),
  KEY `idx_ecommerce_security_log_event_created` (`event_type`, `created_at`),
  KEY `idx_ecommerce_security_log_store_created` (`store_id`, `created_at`),
  KEY `idx_ecommerce_security_log_ip_created` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_nonces` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` INT UNSIGNED NOT NULL,
  `nonce` VARCHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_nonces_store_nonce` (`store_id`, `nonce`),
  KEY `idx_ecommerce_nonces_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecommerce_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,

  `store_id` INT UNSIGNED NULL,
  `actor_type` VARCHAR(16) NOT NULL DEFAULT 'store',
  `actor_id` VARCHAR(64) NULL,

  `action` VARCHAR(128) NOT NULL,
  `ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(512) NULL,
  `details_json` TEXT NULL,

  `created_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_audit_log_public_id` (`public_id`),
  KEY `idx_ecommerce_audit_log_store_created` (`store_id`, `created_at`),
  KEY `idx_ecommerce_audit_log_action_created` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
