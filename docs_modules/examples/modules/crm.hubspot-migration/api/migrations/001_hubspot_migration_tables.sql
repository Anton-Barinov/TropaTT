-- Migration for HubSpot Migration module
CREATE TABLE IF NOT EXISTS `module_hubspot_migration_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
    `mode` VARCHAR(32) NOT NULL DEFAULT 'dry_run',
    `total_records` INT NOT NULL DEFAULT 0,
    `processed_records` INT NOT NULL DEFAULT 0,
    `imported_records` INT NOT NULL DEFAULT 0,
    `skipped_records` INT NOT NULL DEFAULT 0,
    `error_records` INT NOT NULL DEFAULT 0,
    `source_type` VARCHAR(32) NOT NULL DEFAULT 'json_export',
    `options_json` TEXT NULL,
    `checkpoint_cursor` VARCHAR(128) NULL,
    `created_by_user_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_hubspot_ws_status` (`workspace_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_hubspot_migration_staging` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_public_id` VARCHAR(64) NOT NULL,
    `object_type` VARCHAR(32) NOT NULL,
    `hubspot_id` VARCHAR(128) NOT NULL,
    `raw_payload_json` MEDIUMTEXT NOT NULL,
    `normalized_payload_json` MEDIUMTEXT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `crm_target_entity` VARCHAR(32) NULL,
    `crm_target_id` VARCHAR(64) NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL,
    INDEX `idx_hubspot_session_obj` (`session_public_id`, `object_type`),
    INDEX `idx_hubspot_id_unique` (`session_public_id`, `object_type`, `hubspot_id`),
    INDEX `idx_hubspot_status` (`session_public_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_hubspot_migration_id_map` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `object_type` VARCHAR(32) NOT NULL,
    `hubspot_id` VARCHAR(128) NOT NULL,
    `crm_entity_type` VARCHAR(32) NOT NULL,
    `crm_entity_id` VARCHAR(64) NOT NULL,
    `imported_session_id` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_hubspot_obj_map` (`workspace_id`, `object_type`, `hubspot_id`),
    INDEX `idx_hubspot_crm_ent` (`workspace_id`, `crm_entity_type`, `crm_entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
