-- Durable reservation before contacting Mercado Pago; unique per workspace/request key.
CREATE TABLE IF NOT EXISTS `module_mercadopago_preference_attempts` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `workspace_id` INT NOT NULL,
    `idempotency_hash` CHAR(64) NOT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `external_reference` VARCHAR(128) NOT NULL,
    `state` VARCHAR(16) NOT NULL,
    `payment_public_id` VARCHAR(64) NULL,
    `preference_id` VARCHAR(128) NULL,
    `checkout_url` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_mp_preference_attempt_workspace_key` (`workspace_id`, `idempotency_hash`),
    KEY `idx_mp_preference_attempt_state` (`workspace_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
