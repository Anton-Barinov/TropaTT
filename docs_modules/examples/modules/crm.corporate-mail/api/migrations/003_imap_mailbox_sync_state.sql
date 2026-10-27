-- Add stable IMAP UID namespace and an atomic per-mailbox lease.
ALTER TABLE crm_mail_mailboxes
    ADD COLUMN sync_uidvalidity BIGINT UNSIGNED NULL AFTER sync_cursor_uid,
    ADD COLUMN sync_lock_token CHAR(48) NULL AFTER sync_status,
    ADD COLUMN sync_lock_until DATETIME NULL AFTER sync_lock_token,
    ADD INDEX idx_mail_sync_lease (organization_id, sync_status, sync_lock_until);
