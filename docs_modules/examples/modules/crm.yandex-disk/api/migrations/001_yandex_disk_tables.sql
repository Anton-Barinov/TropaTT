-- Yandex Disk Integration Schema
CREATE TABLE IF NOT EXISTS `module_yandex_disk_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1 UNIQUE,
    `oauth_token_encrypted` TEXT NOT NULL,
    `root_folder` VARCHAR(255) NOT NULL DEFAULT '/TropaTT_CRM',
    `auto_upload_task_files` TINYINT(1) NOT NULL DEFAULT 0,
    `auto_upload_backups` TINYINT(1) NOT NULL DEFAULT 1,
    `use_txt_proxy_bypass` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_yandex_disk_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `crm_file_public_id` VARCHAR(64) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `original_extension` VARCHAR(32) NOT NULL,
    `yandex_path` VARCHAR(500) NOT NULL,
    `file_size_bytes` BIGINT NOT NULL DEFAULT 0,
    `public_url` VARCHAR(500) NULL,
    `used_bypass` TINYINT(1) NOT NULL DEFAULT 0,
    `status` VARCHAR(32) NOT NULL DEFAULT 'uploaded',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_ydisk_crm_file` (`workspace_id`, `crm_file_public_id`),
    INDEX `idx_ydisk_status` (`workspace_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
