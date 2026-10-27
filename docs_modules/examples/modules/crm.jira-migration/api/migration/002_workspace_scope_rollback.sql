DROP INDEX idx_jira_job_org_queue ON module_jira_jobs;
DROP INDEX idx_jira_connection_org ON module_jira_connections;
ALTER TABLE module_jira_jobs DROP COLUMN organization_id;
ALTER TABLE module_jira_connections DROP COLUMN organization_id;
