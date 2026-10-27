-- Workspace isolation. Legacy connection ownership is assigned only when the
-- creating user had exactly one workspace. Ambiguous rows remain NULL/fail-closed.
ALTER TABLE `module_notion_connections` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL;
ALTER TABLE `module_notion_import_jobs` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL;
ALTER TABLE `module_notion_import_jobs` ADD COLUMN `lease_token` CHAR(64) NULL;
ALTER TABLE `module_notion_import_jobs` ADD COLUMN `lease_until` DATETIME NULL;
ALTER TABLE `module_notion_import_items` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL;
ALTER TABLE `module_notion_import_logs` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL;
ALTER TABLE `module_notion_user_mappings` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL;
UPDATE `module_notion_connections` c JOIN (SELECT om.user_id, MIN(om.organization_id) AS organization_id FROM organization_memberships om GROUP BY om.user_id HAVING COUNT(DISTINCT om.organization_id) = 1) owner_org ON owner_org.user_id = c.created_by_user_id SET c.organization_id = owner_org.organization_id WHERE c.organization_id IS NULL;
UPDATE `module_notion_import_jobs` j JOIN `module_notion_connections` c ON c.id = j.connection_id AND c.organization_id IS NOT NULL SET j.organization_id = c.organization_id WHERE j.organization_id IS NULL;
UPDATE `module_notion_import_items` i JOIN `module_notion_import_jobs` j ON j.id = i.job_id AND j.organization_id IS NOT NULL SET i.organization_id = j.organization_id WHERE i.organization_id IS NULL;
UPDATE `module_notion_import_logs` l JOIN `module_notion_import_jobs` j ON j.id = l.job_id AND j.organization_id IS NOT NULL SET l.organization_id = j.organization_id WHERE l.organization_id IS NULL;
UPDATE `module_notion_user_mappings` m JOIN `module_notion_connections` c ON c.id = m.connection_id AND c.organization_id IS NOT NULL SET m.organization_id = c.organization_id WHERE m.organization_id IS NULL;
CREATE INDEX `idx_notion_org_connections` ON `module_notion_connections` (`organization_id`);
CREATE INDEX `idx_notion_org_jobs` ON `module_notion_import_jobs` (`organization_id`);
CREATE INDEX `idx_notion_org_items` ON `module_notion_import_items` (`organization_id`);
CREATE INDEX `idx_notion_org_logs` ON `module_notion_import_logs` (`organization_id`);
CREATE INDEX `idx_notion_org_mappings` ON `module_notion_user_mappings` (`organization_id`);
CREATE INDEX `idx_notion_jobs_lease` ON `module_notion_import_jobs` (`status`, `lease_until`);
