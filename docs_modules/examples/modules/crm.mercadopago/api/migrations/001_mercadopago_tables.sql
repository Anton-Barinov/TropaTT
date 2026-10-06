-- Mercado Pago Integration Schema
CREATE TABLE IF NOT EXISTS `module_mercadopago_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1 UNIQUE,
    `public_key` VARCHAR(255) NULL,
    `access_token_encrypted` TEXT NOT NULL,
    `webhook_secret_encrypted` TEXT NULL,
    `sandbox_mode` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_mercadopago_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `external_reference` VARCHAR(128) NOT NULL,
    `mp_preference_id` VARCHAR(128) NULL,
    `mp_payment_id` VARCHAR(128) NULL,
    `init_point_url` TEXT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `currency_id` VARCHAR(8) NOT NULL DEFAULT 'BRL',
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `status_detail` VARCHAR(128) NULL,
    `client_id` INT NULL,
    `project_id` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_mp_ws_ref` (`workspace_id`, `external_reference`),
    INDEX `idx_mp_payment_id` (`workspace_id`, `mp_payment_id`),
    INDEX `idx_mp_status` (`workspace_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_mercadopago_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_public_id` VARCHAR(64) NOT NULL,
    `from_status` VARCHAR(32) NULL,
    `to_status` VARCHAR(32) NOT NULL,
    `raw_webhook_payload` MEDIUMTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mp_audit_payment` (`payment_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
