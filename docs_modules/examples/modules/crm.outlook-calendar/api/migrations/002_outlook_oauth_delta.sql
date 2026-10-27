-- Workspace-level Entra app configuration, one-time OAuth state and resumable delta paging.
CREATE TABLE IF NOT EXISTS `module_outlook_calendar_settings` (
    `workspace_id` INT NOT NULL PRIMARY KEY,
    `tenant_id` VARCHAR(128) NOT NULL DEFAULT 'organizations',
    `client_id` CHAR(36) NOT NULL,
    `client_secret_encrypted` TEXT NOT NULL,
    `redirect_uri` VARCHAR(2048) NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_outlook_calendar_oauth_states` (
    `state_hash` CHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `code_verifier_encrypted` TEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_outlook_oauth_state_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `module_outlook_calendar_connections`
    ADD COLUMN `delta_next_link` LONGTEXT NULL,
    ADD COLUMN `sync_window_start` DATETIME NULL,
    ADD COLUMN `sync_window_end` DATETIME NULL,
    ADD COLUMN `sync_lock_token` CHAR(32) NULL,
    ADD COLUMN `sync_lock_expires_at` DATETIME NULL;

ALTER TABLE `module_outlook_calendar_events_map`
    DROP INDEX `uk_outlook_event_link`,
    ADD UNIQUE KEY `uk_outlook_connection_event` (`workspace_id`, `connection_id`, `outlook_event_id`);
