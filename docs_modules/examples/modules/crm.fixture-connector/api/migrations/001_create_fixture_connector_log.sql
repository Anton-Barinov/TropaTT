CREATE TABLE IF NOT EXISTS mod_crm_fixture_connector_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    payload_json LONGTEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'completed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_org_idemp (organization_id, idempotency_key),
    KEY idx_org_created (organization_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
