-- Outlook Calendar Integration Schema
CREATE TABLE IF NOT EXISTS `module_outlook_calendar_connections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `user_id` INT NOT NULL,
    `tenant_id` VARCHAR(128) NULL,
    `outlook_account_email` VARCHAR(255) NOT NULL,
    `selected_calendar_id` VARCHAR(255) NULL,
    `access_token_encrypted` TEXT NOT NULL,
    `refresh_token_encrypted` TEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `sync_direction` VARCHAR(32) NOT NULL DEFAULT 'both',
    `delta_token` TEXT NULL,
    `webhook_subscription_id` VARCHAR(128) NULL,
    `webhook_expires_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_synced_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_outlook_ws_user` (`workspace_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_outlook_calendar_events_map` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `connection_id` INT NOT NULL,
    `crm_event_id` VARCHAR(64) NOT NULL,
    `outlook_event_id` VARCHAR(255) NOT NULL,
    `last_etag` VARCHAR(128) NULL,
    `last_sync_direction` VARCHAR(16) NOT NULL DEFAULT 'pull',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_outlook_event_link` (`workspace_id`, `outlook_event_id`),
    INDEX `idx_outlook_crm_event` (`workspace_id`, `crm_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
