-- GitLab Integration Module: Initial tables
-- MySQL-only migration

CREATE TABLE IF NOT EXISTS module_gitlab_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(255) NOT NULL DEFAULT 'https://gitlab.com/api/v4',
    token_encrypted TEXT NOT NULL,
    last_status VARCHAR(32) NULL,
    last_message VARCHAR(500) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_gitlab_conn_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_gitlab_project_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    project_path VARCHAR(255) NOT NULL,
    project_public_id VARCHAR(64) NOT NULL,
    webhook_secret_encrypted TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_dirty TINYINT(1) NOT NULL DEFAULT 0,
    last_synced_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_gitlab_link (connection_id, project_path),
    INDEX idx_gitlab_link_project (project_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_gitlab_sync_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    link_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    target_type VARCHAR(64) NULL,
    target_public_id VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    payload_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_gitlab_item (link_id, source_type, source_id),
    INDEX idx_gitlab_item_target (target_type, target_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_gitlab_sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    link_id BIGINT UNSIGNED NULL,
    level VARCHAR(16) NOT NULL DEFAULT 'info',
    message VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_gitlab_logs_link (link_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
