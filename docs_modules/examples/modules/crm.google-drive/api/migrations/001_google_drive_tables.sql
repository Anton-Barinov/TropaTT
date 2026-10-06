-- Google Drive Integration Schema
CREATE TABLE IF NOT EXISTS `module_google_drive_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1 UNIQUE,
    `google_account_email` VARCHAR(255) NULL,
    `client_id` VARCHAR(255) NULL,
    `client_secret_encrypted` TEXT NULL,
    `refresh_token_encrypted` TEXT NOT NULL,
    `access_token_encrypted` TEXT NULL,
    `token_expires_at` DATETIME NULL,
    `root_folder_id` VARCHAR(128) NULL,
    `root_folder_name` VARCHAR(255) NOT NULL DEFAULT 'TropaTT CRM',
    `auto_create_project_folders` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_google_drive_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `crm_file_public_id` VARCHAR(64) NOT NULL,
    `crm_project_public_id` VARCHAR(64) NULL,
    `crm_client_public_id` VARCHAR(64) NULL,
    `drive_file_id` VARCHAR(128) NOT NULL,
    `drive_folder_id` VARCHAR(128) NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(128) NOT NULL,
    `web_view_link` VARCHAR(500) NOT NULL,
    `web_content_link` VARCHAR(500) NULL,
    `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
    `share_role` VARCHAR(32) NOT NULL DEFAULT 'reader',
    `status` VARCHAR(32) NOT NULL DEFAULT 'linked',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_gdrive_crm_file` (`workspace_id`, `crm_file_public_id`),
    INDEX `idx_gdrive_project` (`workspace_id`, `crm_project_public_id`),
    INDEX `idx_gdrive_client` (`workspace_id`, `crm_client_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
