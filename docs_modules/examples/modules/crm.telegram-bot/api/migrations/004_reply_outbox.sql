-- Durable post-commit replies for inbound webhook/poll processing.
CREATE TABLE IF NOT EXISTS crm_telegram_reply_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    update_id BIGINT UNSIGNED NOT NULL,
    chat_id BIGINT NOT NULL,
    message_text TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    attempt_started_at DATETIME NULL,
    telegram_message_id BIGINT NULL,
    last_error VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_tg_outbox_update (organization_id, update_id),
    KEY idx_tg_outbox_pending (organization_id, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
