CREATE TABLE IF NOT EXISTS `module_hubspot_migration_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL,
    `session_public_id` VARCHAR(64) NOT NULL,
    `object_type` VARCHAR(32) NOT NULL,
    `hubspot_id` VARCHAR(128) NOT NULL,
    `contact_label` VARCHAR(255) NULL,
    `raw_payload_json` MEDIUMTEXT NOT NULL,
    `normalized_payload_json` MEDIUMTEXT NULL,
    `contact_fingerprint` CHAR(64) NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `crm_target_entity` VARCHAR(32) NULL,
    `crm_target_id` VARCHAR(64) NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL,
    UNIQUE KEY `uk_hubspot_record` (`workspace_id`, `session_public_id`, `object_type`, `hubspot_id`),
    INDEX `idx_hubspot_ws_session_status` (`workspace_id`, `session_public_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
