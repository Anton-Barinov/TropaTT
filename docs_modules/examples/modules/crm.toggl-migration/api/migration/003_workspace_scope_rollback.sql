-- Rollback organization isolation; preserves module records and drops only isolation metadata.
DROP INDEX `idx_toggl_org_entities` ON `module_toggl_unresolved_entities`;
ALTER TABLE `module_toggl_unresolved_entities` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_logs` ON `module_toggl_job_logs`;
ALTER TABLE `module_toggl_job_logs` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_limits` ON `module_toggl_rate_limits`;
ALTER TABLE `module_toggl_rate_limits` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_user_mappings` ON `module_toggl_user_mappings`;
ALTER TABLE `module_toggl_user_mappings` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_mappings` ON `module_toggl_source_mappings`;
ALTER TABLE `module_toggl_source_mappings` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_items` ON `module_toggl_job_items`;
ALTER TABLE `module_toggl_job_items` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_jobs` ON `module_toggl_jobs`;
ALTER TABLE `module_toggl_jobs` DROP COLUMN `organization_id`;
DROP INDEX `idx_toggl_org_connections` ON `module_toggl_connections`;
ALTER TABLE `module_toggl_connections` DROP COLUMN `organization_id`;
