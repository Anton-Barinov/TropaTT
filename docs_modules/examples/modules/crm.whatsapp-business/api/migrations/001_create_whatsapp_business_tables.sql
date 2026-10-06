-- Migration for crm.whatsapp-business
CREATE TABLE IF NOT EXISTS crm_whatsapp_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    phone_number_id VARCHAR(64) NOT NULL,
    waba_id VARCHAR(64) NOT NULL,
    api_token_encrypted TEXT NOT NULL,
    webhook_verify_token VARCHAR(128) NOT NULL,
    app_secret_encrypted TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_wacfg_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_whatsapp_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    wa_chat_id VARCHAR(64) NOT NULL,
    contact_phone VARCHAR(32) NOT NULL,
    contact_name VARCHAR(128) NULL,
    intake_public_id VARCHAR(32) NULL,
    assigned_user_public_id VARCHAR(32) NULL,
    last_message_at DATETIME NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open', -- 'open', 'resolved'
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_waconv_chat (organization_id, wa_chat_id),
    INDEX idx_waconv_phone (contact_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_whatsapp_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL,
    wa_message_id VARCHAR(128) NOT NULL,
    direction VARCHAR(8) NOT NULL, -- 'inbound', 'outbound'
    message_text TEXT NOT NULL,
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'sent', -- 'sent', 'delivered', 'read', 'failed'
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_wamsg_id (organization_id, wa_message_id),
    INDEX idx_wamsg_conv (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
