-- Migration for crm.corporate-mail
CREATE TABLE IF NOT EXISTS crm_mail_mailboxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    display_name VARCHAR(128) NOT NULL,
    protocol_adapter VARCHAR(32) NOT NULL DEFAULT 'imap_smtp', -- 'imap_smtp', 'ms365', 'gmail'
    auth_config_encrypted TEXT NOT NULL,
    sync_cursor_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sync_status VARCHAR(32) NOT NULL DEFAULT 'idle', -- 'idle', 'syncing', 'error'
    last_error TEXT NULL,
    last_synced_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_mail_org (organization_id),
    INDEX idx_mail_email (email_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_mail_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    mailbox_id INT UNSIGNED NOT NULL,
    message_id_header VARCHAR(255) NOT NULL,
    thread_id VARCHAR(128) NOT NULL,
    in_reply_to VARCHAR(255) NULL,
    sender_email VARCHAR(255) NOT NULL,
    sender_name VARCHAR(128) NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body_text LONGTEXT NOT NULL,
    intake_public_id VARCHAR(32) NULL,
    direction VARCHAR(8) NOT NULL DEFAULT 'inbound', -- 'inbound', 'outbound'
    sent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_mail_msg_hdr (organization_id, mailbox_id, message_id_header),
    INDEX idx_mail_thread (thread_id),
    INDEX idx_mail_sender (sender_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
