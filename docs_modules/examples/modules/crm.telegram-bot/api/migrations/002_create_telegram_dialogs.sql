-- Migration 002 for crm.telegram-bot: Client Dialogs and Messages
CREATE TABLE IF NOT EXISTS crm_telegram_dialogs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    chat_id BIGINT NOT NULL,
    first_name VARCHAR(128) NULL,
    last_name VARCHAR(128) NULL,
    username VARCHAR(128) NULL,
    title VARCHAR(255) NOT NULL,
    last_message_text TEXT NULL,
    last_message_at DATETIME NOT NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    linked_task_id INT UNSIGNED NULL,
    linked_intake_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_tg_org_chat (organization_id, chat_id),
    INDEX idx_tg_dlg_org_status (organization_id, status),
    INDEX idx_tg_dlg_last_msg (organization_id, last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_telegram_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    dialog_id INT UNSIGNED NOT NULL,
    chat_id BIGINT NOT NULL,
    direction VARCHAR(16) NOT NULL DEFAULT 'inbound',
    sender_type VARCHAR(32) NOT NULL DEFAULT 'client',
    sender_user_id INT UNSIGNED NULL,
    sender_name VARCHAR(128) NULL,
    telegram_message_id BIGINT NULL,
    message_text TEXT NOT NULL,
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'delivered',
    created_at DATETIME NOT NULL,
    INDEX idx_tg_msg_dialog (dialog_id, id),
    INDEX idx_tg_msg_org_chat (organization_id, chat_id),
    INDEX idx_tg_msg_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
