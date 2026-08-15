-- Slack Integration Module: Initial tables
-- MySQL-only migration

CREATE TABLE IF NOT EXISTS module_slack_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    channel VARCHAR(255) NULL,
    webhook_url_encrypted TEXT NOT NULL,
    last_status VARCHAR(32) NULL,
    last_message VARCHAR(500) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_slack_conn_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_slack_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    event_code VARCHAR(64) NOT NULL,
    text_template TEXT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_slack_rules_connection (connection_id),
    INDEX idx_slack_rules_event (event_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_slack_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    connection_id BIGINT UNSIGNED NULL,
    rule_id BIGINT UNSIGNED NULL,
    event_code VARCHAR(64) NULL,
    payload_json JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    attempts INT NOT NULL DEFAULT 0,
    response_code INT NULL,
    last_error VARCHAR(500) NULL,
    next_run_at DATETIME NULL,
    locked_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_slack_deliveries_status (status, next_run_at),
    INDEX idx_slack_deliveries_connection (connection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
