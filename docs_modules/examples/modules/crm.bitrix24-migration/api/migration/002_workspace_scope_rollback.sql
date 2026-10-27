DROP INDEX idx_b24_job_org_queue ON module_bitrix24_jobs;
DROP INDEX idx_b24_connection_org ON module_bitrix24_connections;
ALTER TABLE module_bitrix24_jobs DROP COLUMN organization_id;
ALTER TABLE module_bitrix24_connections DROP COLUMN organization_id;
