-- Workspace isolation for ClickUp migration records. Legacy ambiguous rows remain NULL and are unavailable (fail closed).
ALTER TABLE module_clickup_connections ADD COLUMN organization_id BIGINT UNSIGNED NULL AFTER public_id;
ALTER TABLE module_clickup_jobs ADD COLUMN organization_id BIGINT UNSIGNED NULL AFTER public_id;
UPDATE module_clickup_connections c JOIN (SELECT user_id, MIN(organization_id) organization_id FROM organization_memberships GROUP BY user_id HAVING COUNT(DISTINCT organization_id)=1) m ON m.user_id=c.created_by_user_id SET c.organization_id=m.organization_id WHERE c.organization_id IS NULL;
UPDATE module_clickup_jobs j JOIN module_clickup_connections c ON c.id=j.connection_id AND c.organization_id IS NOT NULL SET j.organization_id=c.organization_id WHERE j.organization_id IS NULL;
CREATE INDEX idx_clickup_connection_org ON module_clickup_connections (organization_id, created_at);
CREATE INDEX idx_clickup_job_org_queue ON module_clickup_jobs (organization_id, status, lease_until, created_at);
