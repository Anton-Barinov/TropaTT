-- Paystack Africa Integration Schema
CREATE TABLE IF NOT EXISTS `module_paystack_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL DEFAULT 1 UNIQUE,
    `public_key` VARCHAR(255) NULL,
    `secret_key_encrypted` TEXT NOT NULL,
    `is_live` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_paystack_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `public_id` VARCHAR(64) NOT NULL UNIQUE,
    `workspace_id` INT NOT NULL DEFAULT 1,
    `reference` VARCHAR(128) NOT NULL,
    `paystack_access_code` VARCHAR(128) NULL,
    `authorization_url` TEXT NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `amount_major` DECIMAL(15,2) NOT NULL,
    `currency` VARCHAR(8) NOT NULL DEFAULT 'NGN',
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `channel` VARCHAR(64) NULL,
    `gateway_response` VARCHAR(255) NULL,
    `paid_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_paystack_ref` (`workspace_id`, `reference`),
    INDEX `idx_paystack_status` (`workspace_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_paystack_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_reference` VARCHAR(128) NOT NULL,
    `event_name` VARCHAR(64) NOT NULL,
    `payload_json` MEDIUMTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_paystack_audit_ref` (`transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
