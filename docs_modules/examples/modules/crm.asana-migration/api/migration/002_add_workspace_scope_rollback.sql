-- Rollback removes only the workspace columns/indexes. It does not delete records.
ALTER TABLE `module_asana_jobs`
  DROP INDEX `idx_asana_job_org_owner`,
  DROP INDEX `idx_asana_job_org_queue`,
  DROP COLUMN `organization_id`;

ALTER TABLE `module_asana_connections`
  DROP INDEX `idx_asana_connection_org_owner`,
  DROP COLUMN `organization_id`;
