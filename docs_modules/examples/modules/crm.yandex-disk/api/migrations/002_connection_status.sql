ALTER TABLE `module_yandex_disk_settings`
    ADD COLUMN `account_uid` VARCHAR(64) NULL AFTER `oauth_token_encrypted`,
    ADD COLUMN `used_space` BIGINT NULL AFTER `account_uid`,
    ADD COLUMN `total_space` BIGINT NULL AFTER `used_space`;
