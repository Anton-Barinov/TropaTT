CREATE TABLE IF NOT EXISTS `module_todoist_connections` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `public_id` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `auth_type` VARCHAR(32) NOT NULL DEFAULT 'pat',
  `access_token_encrypted` TEXT NULL,
  `refresh_token_encrypted` TEXT NULL,
  `client_id_encrypted` TEXT NULL,
  `client_secret_encrypted` TEXT NULL,
  `account_id` VARCHAR(191) NULL,
  `account_name` VARCHAR(255) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `last_checked_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_todoist_connection_owner` (`created_by_user_id`),
  INDEX `idx_todoist_connection_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_jobs` (
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
  `last_source_cursor` TEXT NULL,
  `lease_token` VARCHAR(64) NULL,
  `lease_until` DATETIME NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_todoist_job_queue` (`status`,`lease_until`,`created_at`),
  INDEX `idx_todoist_job_connection` (`connection_id`,`status`),
  INDEX `idx_todoist_job_owner` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_job_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(191) NOT NULL,
  `source_parent_id` VARCHAR(191) NULL,
  `source_project_id` VARCHAR(191) NULL,
  `target_type` VARCHAR(64) NULL,
  `target_public_id` VARCHAR(64) NULL,
  `created_by_job` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `checksum` CHAR(64) NULL,
  `source_updated_at` DATETIME NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_code` VARCHAR(64) NULL,
  `error_message` TEXT NULL,
  `payload_json` JSON NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_todoist_job_item_source` (`job_id`,`source_type`,`source_id`),
  INDEX `idx_todoist_job_item_status` (`job_id`,`status`,`id`),
  INDEX `idx_todoist_job_item_source` (`source_type`,`source_id`),
  INDEX `idx_todoist_job_item_parent` (`source_parent_id`),
  INDEX `idx_todoist_job_item_target` (`target_type`,`target_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_source_mappings` (
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
  `state` VARCHAR(32) NOT NULL DEFAULT 'active',
  `created_by_job_id` BIGINT UNSIGNED NULL,
  `last_seen_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_todoist_source_mapping` (`connection_id`,`source_type`,`source_id`),
  INDEX `idx_todoist_mapping_target` (`target_type`,`target_public_id`),
  INDEX `idx_todoist_mapping_state` (`connection_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_user_mappings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `connection_id` BIGINT UNSIGNED NOT NULL,
  `todoist_user_id` VARCHAR(191) NOT NULL,
  `display_name` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `crm_user_public_id` VARCHAR(64) NULL,
  `mapping_status` VARCHAR(32) NOT NULL DEFAULT 'unmapped',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_todoist_user_mapping` (`connection_id`,`todoist_user_id`),
  INDEX `idx_todoist_user_mapping_crm` (`crm_user_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_rate_limits` (
  `connection_id` BIGINT UNSIGNED PRIMARY KEY,
  `requests_made` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_started_at` DATETIME NULL,
  `retry_after_until` DATETIME NULL,
  `last_http_status` SMALLINT NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_job_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `level` VARCHAR(16) NOT NULL DEFAULT 'info',
  `step` VARCHAR(64) NULL,
  `message` TEXT NOT NULL,
  `context_json` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_todoist_job_log` (`job_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_todoist_unresolved_entities` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(191) NOT NULL,
  `reason_code` VARCHAR(64) NOT NULL,
  `reason_text` TEXT NOT NULL,
  `payload_json` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_todoist_unresolved_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
