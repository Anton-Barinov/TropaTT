-- Rollback for 002_workspace_listing_indexes.sql. The verification marker and index were introduced by this migration.
ALTER TABLE `module_vk_workspace_meetings`
    DROP INDEX `idx_vk_meetings_workspace_start`,
    DROP COLUMN `provider_verified`;
