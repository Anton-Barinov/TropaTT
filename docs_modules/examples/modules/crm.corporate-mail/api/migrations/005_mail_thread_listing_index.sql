ALTER TABLE crm_mail_messages
    ADD INDEX idx_mail_thread_scope (organization_id, mailbox_id, thread_id, sent_at);
