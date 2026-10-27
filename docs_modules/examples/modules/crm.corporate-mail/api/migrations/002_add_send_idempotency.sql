-- Reserve outbound email requests before contacting SMTP. This prevents duplicate delivery
-- when a provider accepts DATA but the CRM response or local persistence is interrupted.
CREATE TABLE IF NOT EXISTS crm_mail_send_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    mailbox_id INT UNSIGNED NOT NULL,
    idempotency_hash CHAR(64) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'sending',
    message_public_id VARCHAR(32) NOT NULL,
    message_id_header VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_mail_send_idempotency (organization_id, mailbox_id, idempotency_hash),
    INDEX idx_mail_send_status (organization_id, mailbox_id, status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
