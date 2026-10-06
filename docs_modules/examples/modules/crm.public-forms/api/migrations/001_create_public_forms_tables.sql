-- Migration for crm.public-forms
CREATE TABLE IF NOT EXISTS crm_public_forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    slug VARCHAR(64) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    form_type VARCHAR(32) NOT NULL DEFAULT 'intake', -- 'intake', 'booking'
    fields_schema_json TEXT NOT NULL,
    consent_text TEXT NOT NULL,
    consent_version INT UNSIGNED NOT NULL DEFAULT 1,
    target_project_public_id VARCHAR(32) NULL,
    target_assignee_user_public_id VARCHAR(32) NULL,
    calendar_user_public_id VARCHAR(32) NULL,
    slot_duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    buffer_minutes INT UNSIGNED NOT NULL DEFAULT 15,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_pubform_org (organization_id),
    INDEX idx_pubform_slug (slug),
    INDEX idx_pubform_pub (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_public_form_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    intake_public_id VARCHAR(32) NULL,
    event_public_id VARCHAR(32) NULL,
    submitter_ip VARCHAR(64) NOT NULL,
    submitter_data_json TEXT NOT NULL,
    consent_agreed TINYINT(1) NOT NULL DEFAULT 1,
    consent_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX idx_pubsub_form (form_id),
    INDEX idx_pubsub_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
