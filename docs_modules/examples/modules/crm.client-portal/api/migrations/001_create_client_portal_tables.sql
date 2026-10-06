-- Migration: create service requests and approvals tables for crm.client-portal
CREATE TABLE IF NOT EXISTS crm_client_service_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    client_public_id VARCHAR(32) NOT NULL,
    task_public_id VARCHAR(32) NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    service_status VARCHAR(32) NOT NULL DEFAULT 'submitted',
    sla_response_due_at DATETIME NULL,
    sla_resolution_due_at DATETIME NULL,
    sla_status VARCHAR(32) NOT NULL DEFAULT 'on_track',
    created_by_user_public_id VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_client_service_req_org (organization_id),
    INDEX idx_client_service_req_client (client_public_id),
    INDEX idx_client_service_req_status (service_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_client_service_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    request_public_id VARCHAR(32) NOT NULL,
    author_type VARCHAR(16) NOT NULL, -- 'client' or 'staff'
    author_public_id VARCHAR(32) NOT NULL,
    is_client_visible TINYINT(1) NOT NULL DEFAULT 1,
    message_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_service_msg_req (request_public_id),
    INDEX idx_service_msg_visible (is_client_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_client_service_approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL UNIQUE,
    organization_id INT UNSIGNED NOT NULL,
    request_public_id VARCHAR(32) NOT NULL,
    approval_type VARCHAR(32) NOT NULL DEFAULT 'estimate', -- 'estimate', 'deliverable'
    title VARCHAR(255) NOT NULL,
    amount_cents BIGINT NULL,
    currency VARCHAR(8) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    resolution_note TEXT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_service_appr_req (request_public_id),
    INDEX idx_service_appr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
