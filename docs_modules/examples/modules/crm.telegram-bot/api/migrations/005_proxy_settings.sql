ALTER TABLE crm_telegram_configs
    ADD COLUMN proxy_type VARCHAR(16) NOT NULL DEFAULT 'none',
    ADD COLUMN proxy_host VARCHAR(255) NULL,
    ADD COLUMN proxy_port SMALLINT UNSIGNED NULL,
    ADD COLUMN proxy_username VARCHAR(255) NULL,
    ADD COLUMN proxy_password_encrypted TEXT NULL;
