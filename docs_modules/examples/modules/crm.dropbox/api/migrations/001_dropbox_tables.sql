-- Dropbox Integration Schema
CREATE TABLE IF NOT EXISTS `module_dropbox_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1 UNIQUE,
    `account_id` VARCHAR(128) NULL,
    `account_email` VARCHAR(255) NULL,
    `app_key` VARCHAR(128) NULL,
    `app_secret_encrypted` TEXT NULL,
    `refresh_token_encrypted` TEXT NOT NULL,
    `access_token_encrypted` TEXT NULL,
    `token_expires_at` DATETIME NULL,
    `root_namespace_id` VARCHAR(64) NULL,
    `base_folder_path` VARCHAR(255) NOT NULL DEFAULT '/TropaTT_CRM',
    `auto_sync_project_folders` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_dropbox_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `crm_file_public_id` VARCHAR(64) NOT NULL,
    `crm_project_public_id` VARCHAR(64) NULL,
    `dropbox_file_id` VARCHAR(128) NOT NULL,
    `dropbox_path_lower` VARCHAR(500) NOT NULL,
    `dropbox_path_display` VARCHAR(500) NOT NULL,
    `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
    `shared_link_url` VARCHAR(500) NULL,
    `content_hash` VARCHAR(128) NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'synced',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_dropbox_crm_file` (`workspace_id`, `crm_file_public_id`),
    INDEX `idx_dropbox_project` (`workspace_id`, `crm_project_public_id`),
    INDEX `idx_dropbox_status` (`workspace_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
