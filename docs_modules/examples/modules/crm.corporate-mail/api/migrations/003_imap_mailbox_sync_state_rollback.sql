ALTER TABLE crm_mail_mailboxes
    DROP INDEX idx_mail_sync_lease,
    DROP COLUMN sync_lock_until,
    DROP COLUMN sync_lock_token,
    DROP COLUMN sync_uidvalidity;
