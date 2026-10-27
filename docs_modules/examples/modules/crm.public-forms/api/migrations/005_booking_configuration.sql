ALTER TABLE crm_public_forms
    ADD COLUMN booking_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC' AFTER buffer_minutes,
    ADD COLUMN booking_schedule_json TEXT NULL AFTER booking_timezone,
    ADD INDEX idx_public_form_booking_user (organization_id, calendar_user_public_id);

CREATE TABLE IF NOT EXISTS crm_public_form_slot_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    request_ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_public_form_slot_rate (form_id, request_ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crm_public_form_submissions
    ADD COLUMN booking_buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER event_public_id;
