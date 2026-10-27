-- Preserve all module data; rollback removes only new isolation and lease metadata.
DROP INDEX `idx_notion_jobs_lease` ON `module_notion_import_jobs`;
DROP INDEX `idx_notion_org_mappings` ON `module_notion_user_mappings`;
ALTER TABLE `module_notion_user_mappings` DROP COLUMN `organization_id`;
DROP INDEX `idx_notion_org_logs` ON `module_notion_import_logs`;
ALTER TABLE `module_notion_import_logs` DROP COLUMN `organization_id`;
DROP INDEX `idx_notion_org_items` ON `module_notion_import_items`;
ALTER TABLE `module_notion_import_items` DROP COLUMN `organization_id`;
ALTER TABLE `module_notion_import_jobs` DROP COLUMN `lease_until`;
ALTER TABLE `module_notion_import_jobs` DROP COLUMN `lease_token`;
DROP INDEX `idx_notion_org_jobs` ON `module_notion_import_jobs`;
ALTER TABLE `module_notion_import_jobs` DROP COLUMN `organization_id`;
DROP INDEX `idx_notion_org_connections` ON `module_notion_connections`;
ALTER TABLE `module_notion_connections` DROP COLUMN `organization_id`;
