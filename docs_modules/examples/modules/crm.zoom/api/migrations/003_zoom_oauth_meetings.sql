ALTER TABLE module_zoom_settings
    ADD COLUMN host_user_id VARCHAR(128) NULL AFTER client_secret,
    ADD COLUMN verified_email VARCHAR(255) NULL AFTER host_user_id;

ALTER TABLE module_zoom_meetings
    ADD COLUMN join_url_encrypted TEXT NULL AFTER join_url,
    ADD COLUMN start_url_encrypted TEXT NULL AFTER join_url_encrypted,
    ADD COLUMN passcode_encrypted TEXT NULL AFTER start_url_encrypted,
    ADD COLUMN idempotency_key_hash CHAR(64) NULL AFTER provider_verified,
    ADD COLUMN payload_hash CHAR(64) NULL AFTER idempotency_key_hash,
    ADD UNIQUE KEY uq_zoom_meeting_idempotency (workspace_id, idempotency_key_hash);

CREATE TABLE IF NOT EXISTS module_zoom_idempotency (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT NOT NULL,
    idempotency_key_hash CHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    state VARCHAR(16) NOT NULL DEFAULT 'pending',
    meeting_public_id VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zoom_idempotency_workspace_key (workspace_id, idempotency_key_hash),
    INDEX idx_zoom_idempotency_state (workspace_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing start_url/passcode may contain plaintext legacy values. They are not selected or returned.
-- New writes use encrypted columns, while legacy plaintext columns remain NULL.
