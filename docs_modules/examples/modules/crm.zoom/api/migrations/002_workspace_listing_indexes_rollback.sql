-- Rollback for 002_workspace_listing_indexes.sql. The verification marker and index were introduced by this migration.
ALTER TABLE `module_zoom_meetings`
    DROP INDEX `idx_zoom_meetings_workspace_start`,
    DROP COLUMN `provider_verified`;
