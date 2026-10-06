-- Migration for crm.telegram-bot
CREATE TABLE IF NOT EXISTS crm_telegram_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    bot_token_encrypted TEXT NOT NULL,
    webhook_secret_token VARCHAR(128) NOT NULL,
    webhook_url VARCHAR(255) NULL,
    default_project_public_id VARCHAR(32) NULL,
    default_assignee_user_public_id VARCHAR(32) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    polling_last_update_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_tgcfg_org (organization_id),
    INDEX idx_tgcfg_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_telegram_user_bindings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    user_public_id VARCHAR(32) NOT NULL,
    telegram_chat_id BIGINT NOT NULL,
    telegram_username VARCHAR(128) NULL,
    verification_code VARCHAR(32) NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_tgbind_user (user_public_id),
    INDEX idx_tgbind_chat (telegram_chat_id),
    INDEX idx_tgbind_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_telegram_updates_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    update_id BIGINT UNSIGNED NOT NULL,
    chat_id BIGINT NOT NULL,
    action_type VARCHAR(64) NOT NULL,
    payload_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_tg_update (organization_id, update_id),
    INDEX idx_tgupd_chat (chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
