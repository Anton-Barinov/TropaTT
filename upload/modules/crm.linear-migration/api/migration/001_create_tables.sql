-- Linear Migration Module: Initial tables
-- MySQL-only migration

CREATE TABLE IF NOT EXISTS module_linear_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    workspace_name VARCHAR(255) NULL,
    api_key_encrypted TEXT NOT NULL,
    last_check_status VARCHAR(32) NULL,
    last_check_message VARCHAR(500) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_linear_conn_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_linear_import_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    mode VARCHAR(32) NOT NULL DEFAULT 'dry_run',
    source_team_ids_json JSON NULL,
    target_project_public_id VARCHAR(64) NULL,
    options_json JSON NOT NULL,
    stats_json JSON NULL,
    current_step VARCHAR(64) NULL,
    progress_percent DECIMAL(5,2) DEFAULT 0,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_linear_jobs_status (status),
    INDEX idx_linear_jobs_connection (connection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_linear_import_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    source_parent_id VARCHAR(128) NULL,
    target_type VARCHAR(64) NULL,
    target_public_id VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    error_code VARCHAR(64) NULL,
    error_message VARCHAR(500) NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_linear_item (job_id, source_type, source_id),
    INDEX idx_linear_items_job_status (job_id, status),
    INDEX idx_linear_items_target (target_type, target_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_linear_import_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(16) NOT NULL DEFAULT 'info',
    step VARCHAR(64) NULL,
    message VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_linear_logs_job_created (job_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_linear_user_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT UNSIGNED NOT NULL,
    linear_user_id VARCHAR(128) NOT NULL,
    display_name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    crm_user_public_id VARCHAR(64) NULL,
    mapping_status VARCHAR(32) NOT NULL DEFAULT 'unmapped',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_linear_user_mapping (connection_id, linear_user_id),
    INDEX idx_linear_user_mappings_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_linear_settings (
    module_name VARCHAR(190) NOT NULL DEFAULT 'crm.linear-migration',
    setting_key VARCHAR(190) NOT NULL,
    setting_value JSON NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (module_name, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
