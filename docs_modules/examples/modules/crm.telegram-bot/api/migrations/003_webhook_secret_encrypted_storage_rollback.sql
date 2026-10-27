-- Keep the widened column on rollback: encrypted values can exceed VARCHAR(128).
-- Shrinking this column would irreversibly truncate existing credentials.
ALTER TABLE crm_telegram_configs
    MODIFY webhook_secret_token VARCHAR(512) NOT NULL;
