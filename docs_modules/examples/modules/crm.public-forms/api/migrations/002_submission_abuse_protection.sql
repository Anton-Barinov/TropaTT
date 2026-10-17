ALTER TABLE crm_public_form_submissions
    ADD COLUMN idempotency_key_hash CHAR(64) NULL AFTER consent_version,
    ADD COLUMN idempotency_payload_hash CHAR(64) NULL AFTER idempotency_key_hash,
    ADD UNIQUE KEY uk_pubsub_idempotency (form_id, idempotency_key_hash),
    ADD INDEX idx_pubsub_rate_limit (form_id, submitter_ip, created_at);
