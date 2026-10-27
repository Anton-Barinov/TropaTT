-- Durable per-UID errors allow the cursor to continue without losing failed messages.
CREATE TABLE IF NOT EXISTS crm_mail_sync_failures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    mailbox_id INT UNSIGNED NOT NULL,
    uidvalidity BIGINT UNSIGNED NOT NULL,
    uid BIGINT UNSIGNED NOT NULL,
    error_code VARCHAR(32) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_mail_sync_failure_uid (organization_id, mailbox_id, uidvalidity, uid),
    INDEX idx_mail_sync_failure_list (organization_id, mailbox_id, uidvalidity, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
