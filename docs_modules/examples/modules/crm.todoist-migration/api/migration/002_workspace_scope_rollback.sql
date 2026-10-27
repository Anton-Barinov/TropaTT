-- Rollback organization isolation; preserves module records and drops only isolation metadata.
DROP INDEX `idx_todoist_org_entities` ON `module_todoist_unresolved_entities`;
ALTER TABLE `module_todoist_unresolved_entities` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_logs` ON `module_todoist_job_logs`;
ALTER TABLE `module_todoist_job_logs` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_limits` ON `module_todoist_rate_limits`;
ALTER TABLE `module_todoist_rate_limits` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_mappings` ON `module_todoist_user_mappings`;
ALTER TABLE `module_todoist_user_mappings` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_mappings` ON `module_todoist_source_mappings`;
ALTER TABLE `module_todoist_source_mappings` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_items` ON `module_todoist_job_items`;
ALTER TABLE `module_todoist_job_items` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_jobs` ON `module_todoist_jobs`;
ALTER TABLE `module_todoist_jobs` DROP COLUMN `organization_id`;
DROP INDEX `idx_todoist_org_connections` ON `module_todoist_connections`;
ALTER TABLE `module_todoist_connections` DROP COLUMN `organization_id`;
