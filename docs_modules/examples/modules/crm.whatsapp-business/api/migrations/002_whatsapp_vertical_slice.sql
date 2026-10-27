-- Complete Meta webhook verification, inbound tracking and idempotent replies.
ALTER TABLE `crm_whatsapp_configs`
    ADD COLUMN `configured_by_user_id` INT UNSIGNED NULL AFTER `app_secret_encrypted`;

ALTER TABLE `crm_whatsapp_conversations`
    ADD COLUMN `last_inbound_at` DATETIME NULL AFTER `last_message_at`;

CREATE TABLE IF NOT EXISTS `crm_whatsapp_send_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization_id` INT UNSIGNED NOT NULL,
    `request_key_hash` CHAR(64) NOT NULL,
    `payload_hash` CHAR(64) NOT NULL,
    `status` VARCHAR(16) NOT NULL,
    `wa_message_id` VARCHAR(128) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wasend_org_key` (`organization_id`, `request_key_hash`),
    KEY `idx_wasend_status_updated` (`status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
