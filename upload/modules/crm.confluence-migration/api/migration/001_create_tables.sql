-- Confluence Migration Module: Initial tables
-- MySQL-only migration

CREATE TABLE IF NOT EXISTS module_confluence_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(500) NOT NULL,
    auth_type VARCHAR(32) NOT NULL DEFAULT 'api_token',
    email VARCHAR(255) NULL,
    token_encrypted TEXT NULL,
    oauth_payload_encrypted TEXT NULL,
    last_check_status VARCHAR(32) NULL,
    last_check_message TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_connections_created_by (created_by_user_id),
    INDEX idx_connections_base_url (base_url(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_import_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    mode VARCHAR(32) NOT NULL DEFAULT 'dry_run',
    source_space_keys_json JSON NOT NULL,
    target_root_space_public_id VARCHAR(64) NULL,
    options_json JSON NOT NULL,
    stats_json JSON NULL,
    current_step VARCHAR(64) NULL,
    progress_percent DECIMAL(5,2) DEFAULT 0,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_jobs_status (status),
    INDEX idx_jobs_connection (connection_id),
    INDEX idx_jobs_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_import_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    source_key VARCHAR(255) NULL,
    source_parent_id VARCHAR(128) NULL,
    target_type VARCHAR(64) NULL,
    target_public_id VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    checksum CHAR(64) NULL,
    source_updated_at DATETIME NULL,
    error_code VARCHAR(64) NULL,
    error_message TEXT NULL,
    attempts INT DEFAULT 0,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_import_item (job_id, source_type, source_id),
    INDEX idx_items_job_status (job_id, status),
    INDEX idx_items_source (source_type, source_id),
    INDEX idx_items_target (target_type, target_public_id),
    INDEX idx_items_parent (source_parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_import_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    level VARCHAR(16) NOT NULL DEFAULT 'info',
    step VARCHAR(64) NULL,
    message TEXT NOT NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_logs_job_created (job_id, created_at),
    INDEX idx_logs_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_user_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT UNSIGNED NOT NULL,
    confluence_account_id VARCHAR(255) NOT NULL,
    confluence_display_name VARCHAR(255) NULL,
    confluence_email VARCHAR(255) NULL,
    crm_user_public_id VARCHAR(64) NULL,
    mapping_status VARCHAR(32) NOT NULL DEFAULT 'unmapped',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_user_mapping (connection_id, confluence_account_id),
    INDEX idx_user_mappings_crm (crm_user_public_id),
    INDEX idx_user_mappings_status (mapping_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_group_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT UNSIGNED NOT NULL,
    confluence_group_id VARCHAR(255) NULL,
    confluence_group_name VARCHAR(255) NOT NULL,
    crm_subject_type VARCHAR(32) NULL,
    crm_subject_public_id VARCHAR(64) NULL,
    mapping_status VARCHAR(32) NOT NULL DEFAULT 'unmapped',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_group_mapping (connection_id, confluence_group_name),
    INDEX idx_group_mappings_crm (crm_subject_type, crm_subject_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_unresolved_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    source_page_id VARCHAR(128) NOT NULL,
    source_url TEXT NOT NULL,
    link_text VARCHAR(500) NULL,
    reason VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_unresolved_links_job (job_id, source_page_id),
    INDEX idx_unresolved_links_reason (reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_unsupported_macros (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    source_page_id VARCHAR(128) NOT NULL,
    macro_name VARCHAR(255) NOT NULL,
    macro_hash CHAR(64) NULL,
    handling VARCHAR(64) NOT NULL DEFAULT 'placeholder',
    count INT NOT NULL DEFAULT 1,
    sample_html TEXT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_macro (job_id, source_page_id, macro_name, macro_hash),
    INDEX idx_macros_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_confluence_settings (
    module_name VARCHAR(190) NOT NULL DEFAULT 'crm.confluence-migration',
    setting_key VARCHAR(190) NOT NULL,
    setting_value JSON NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (module_name, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
