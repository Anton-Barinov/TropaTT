-- Confluence Migration Module: Rate limit tracking table
-- MySQL-only migration

CREATE TABLE IF NOT EXISTS module_confluence_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT UNSIGNED NOT NULL,
    requests_made INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NULL,
    retry_after_until DATETIME NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_rate_limit_connection (connection_id),
    INDEX idx_rate_limit_retry (retry_after_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
