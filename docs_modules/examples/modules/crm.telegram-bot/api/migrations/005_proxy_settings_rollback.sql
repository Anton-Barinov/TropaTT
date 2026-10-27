ALTER TABLE crm_telegram_configs
    DROP COLUMN proxy_password_encrypted,
    DROP COLUMN proxy_username,
    DROP COLUMN proxy_port,
    DROP COLUMN proxy_host,
    DROP COLUMN proxy_type;
