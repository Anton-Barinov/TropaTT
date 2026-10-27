-- Conservative workspace backfill: ambiguous/ownerless legacy data remains NULL and is unavailable (fail closed).
ALTER TABLE module_jira_connections ADD COLUMN organization_id BIGINT UNSIGNED NULL AFTER public_id;
ALTER TABLE module_jira_jobs ADD COLUMN organization_id BIGINT UNSIGNED NULL AFTER public_id;
UPDATE module_jira_connections c JOIN (SELECT user_id,MIN(organization_id) organization_id FROM organization_memberships GROUP BY user_id HAVING COUNT(DISTINCT organization_id)=1) m ON m.user_id=c.created_by_user_id SET c.organization_id=m.organization_id WHERE c.organization_id IS NULL;
UPDATE module_jira_jobs j JOIN module_jira_connections c ON c.id=j.connection_id AND c.organization_id IS NOT NULL SET j.organization_id=c.organization_id WHERE j.organization_id IS NULL;
CREATE INDEX idx_jira_connection_org ON module_jira_connections (organization_id,created_at);
CREATE INDEX idx_jira_job_org_queue ON module_jira_jobs (organization_id,status,created_at);
