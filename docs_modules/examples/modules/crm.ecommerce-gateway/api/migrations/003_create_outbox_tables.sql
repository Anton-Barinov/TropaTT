-- crm.ecommerce-gateway: outbox events for asynchronous status synchronization (E-COM-04)
--
-- Guarantees reliable at-least-once delivery of outbound webhooks (CRM Events -> CMS Webhooks).
-- Events are recorded synchronously when CRM tasks/entities transition, then asynchronously dispatched
-- by EcommerceWebhookJob with exponential backoff retry.

CREATE TABLE IF NOT EXISTS `ecommerce_outbox_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(64) NOT NULL,

  `store_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(64) NOT NULL DEFAULT 'order.status_changed',
  `external_order_id` VARCHAR(191) NOT NULL,

  `crm_task_id` BIGINT UNSIGNED NULL,
  `crm_task_public_id` VARCHAR(64) NULL,

  `old_status` VARCHAR(64) NULL,
  `new_status` VARCHAR(64) NOT NULL,
  `external_status` VARCHAR(128) NOT NULL,

  `payload_json` MEDIUMTEXT NOT NULL,
  `status` ENUM('pending', 'delivering', 'delivered', 'failed', 'dead') NOT NULL DEFAULT 'pending',

  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 8,
  `next_attempt_at` DATETIME NOT NULL,
  `last_attempt_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,

  `last_http_code` SMALLINT UNSIGNED NULL,
  `last_error` TEXT NULL,
  `last_response_body` TEXT NULL,

  `sync_initiator` VARCHAR(32) NOT NULL DEFAULT 'crm',

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecommerce_outbox_public_id` (`public_id`),
  KEY `idx_ecommerce_outbox_pending` (`status`, `next_attempt_at`),
  KEY `idx_ecommerce_outbox_store_created` (`store_id`, `created_at`),
  KEY `idx_ecommerce_outbox_order` (`store_id`, `external_order_id`),
  KEY `idx_ecommerce_outbox_task` (`crm_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
