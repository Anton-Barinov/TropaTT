-- The authenticated-encryption envelope can be larger than the legacy plaintext.
-- 512 bytes holds the maximum supported 256-byte secret plus its AES-GCM envelope.
ALTER TABLE crm_telegram_configs
    MODIFY webhook_secret_token VARCHAR(512) NOT NULL;
