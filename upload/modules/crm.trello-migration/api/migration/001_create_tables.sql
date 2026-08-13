-- crm.trello-migration initial schema. Module-owned tables only.
CREATE TABLE IF NOT EXISTS `module_trello_connections` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `public_id` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `api_key_encrypted` TEXT NOT NULL,
  `token_encrypted` TEXT NOT NULL,
  `api_secret_encrypted` TEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `last_checked_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_trello_connections_owner` (`created_by_user_id`),
  INDEX `idx_trello_connections_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `public_id` VARCHAR(64) NOT NULL UNIQUE,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `mode` VARCHAR(32) NOT NULL DEFAULT 'import',
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `source_scope_json` JSON NOT NULL,
  `target_options_json` JSON NOT NULL,
  `progress_json` JSON NULL,
  `summary_json` JSON NULL,
  `current_step` VARCHAR(64) NULL,
  `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `lease_token` VARCHAR(64) NULL,
  `lease_until` DATETIME NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_trello_jobs_queue` (`status`, `lease_until`, `created_at`),
  INDEX `idx_trello_jobs_connection` (`connection_id`),
  INDEX `idx_trello_jobs_owner` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_job_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(191) NOT NULL,
  `source_parent_id` VARCHAR(191) NULL,
  `target_type` VARCHAR(64) NULL,
  `target_public_id` VARCHAR(64) NULL,
  `created_by_job` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `checksum` CHAR(64) NULL,
  `source_updated_at` DATETIME NULL,
  `payload_json` JSON NULL,
  `error_code` VARCHAR(64) NULL,
  `error_message` TEXT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_trello_job_item_source` (`job_id`, `source_type`, `source_id`),
  INDEX `idx_trello_job_items_status` (`job_id`, `status`, `id`),
  INDEX `idx_trello_job_items_source` (`source_type`, `source_id`),
  INDEX `idx_trello_job_items_target` (`target_type`, `target_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_source_mappings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `public_id` VARCHAR(64) NOT NULL UNIQUE,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(191) NOT NULL,
  `source_parent_id` VARCHAR(191) NULL,
  `target_type` VARCHAR(64) NULL,
  `target_public_id` VARCHAR(64) NULL,
  `source_checksum` CHAR(64) NULL,
  `target_checksum` CHAR(64) NULL,
  `source_updated_at` DATETIME NULL,
  `state` VARCHAR(32) NOT NULL DEFAULT 'active',
  `created_by_job_id` BIGINT UNSIGNED NULL,
  `last_seen_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_trello_mapping` (`connection_id`, `source_type`, `source_id`),
  INDEX `idx_trello_mapping_target` (`target_type`, `target_public_id`),
  INDEX `idx_trello_mapping_state` (`connection_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_board_configs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `board_id` VARCHAR(191) NOT NULL,
  `board_name` VARCHAR(255) NULL,
  `target_project_public_id` VARCHAR(64) NULL,
  `list_mapping_json` JSON NOT NULL,
  `options_json` JSON NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_trello_board_config` (`connection_id`, `board_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_user_mappings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `trello_member_id` VARCHAR(191) NOT NULL,
  `display_name` VARCHAR(255) NULL,
  `username` VARCHAR(255) NULL,
  `crm_user_public_id` VARCHAR(64) NULL,
  `mapping_status` VARCHAR(32) NOT NULL DEFAULT 'unmapped',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_trello_user_mapping` (`connection_id`, `trello_member_id`),
  INDEX `idx_trello_user_mapping_crm` (`crm_user_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_rate_limits` (
  `connection_id` BIGINT UNSIGNED PRIMARY KEY,
  `requests_made` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_started_at` DATETIME NULL,
  `token_remaining` INT NULL,
  `key_remaining` INT NULL,
  `retry_after_until` DATETIME NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_webhooks` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `public_id` VARCHAR(64) NOT NULL UNIQUE,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `trello_webhook_id` VARCHAR(191) NULL UNIQUE,
  `model_id` VARCHAR(191) NOT NULL,
  `callback_url` VARCHAR(2048) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_event_id` VARCHAR(191) NULL,
  `last_received_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_trello_webhook_model` (`connection_id`, `model_id`),
  INDEX `idx_trello_webhooks_connection` (`connection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_sync_states` (
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `board_id` VARCHAR(191) NOT NULL,
  `last_action_id` VARCHAR(191) NULL,
  `last_action_at` DATETIME NULL,
  `last_full_snapshot_at` DATETIME NULL,
  `next_poll_at` DATETIME NULL,
  `state_json` JSON NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`connection_id`, `board_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_trello_job_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `level` VARCHAR(16) NOT NULL,
  `step` VARCHAR(64) NULL,
  `message` TEXT NOT NULL,
  `context_json` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_trello_job_logs` (`job_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
