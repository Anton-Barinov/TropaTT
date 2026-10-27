DROP TABLE IF EXISTS module_zoom_idempotency;
ALTER TABLE module_zoom_meetings
    DROP INDEX uq_zoom_meeting_idempotency,
    DROP COLUMN payload_hash,
    DROP COLUMN idempotency_key_hash,
    DROP COLUMN passcode_encrypted,
    DROP COLUMN start_url_encrypted,
    DROP COLUMN join_url_encrypted;
ALTER TABLE module_zoom_settings
    DROP COLUMN verified_email,
    DROP COLUMN host_user_id;
