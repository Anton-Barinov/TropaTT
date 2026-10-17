ALTER TABLE crm_public_form_submissions
    DROP INDEX idx_pubsub_rate_limit,
    DROP INDEX uk_pubsub_idempotency,
    DROP COLUMN idempotency_payload_hash,
    DROP COLUMN idempotency_key_hash;
