-- Workspace webhook routes and idempotent event receipts. Existing payment
-- rows and credentials are preserved when upgrading from the previous version.
CREATE TABLE IF NOT EXISTS `module_paystack_webhook_routes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL,
    `webhook_id` CHAR(32) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_paystack_webhook_workspace` (`workspace_id`),
    UNIQUE KEY `uk_paystack_webhook_id` (`webhook_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `module_paystack_webhook_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL,
    `event_hash` CHAR(64) NOT NULL,
    `event_name` VARCHAR(64) NOT NULL,
    `transaction_reference` VARCHAR(128) NOT NULL,
    `outcome` VARCHAR(32) NOT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_paystack_event_workspace_hash` (`workspace_id`, `event_hash`),
    KEY `idx_paystack_event_reference` (`workspace_id`, `transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
