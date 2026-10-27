-- Roll back workspace columns only after confirming no tenant-owned data depends on them.
DROP INDEX idx_gitlab_connections_organization ON module_gitlab_connections;
DROP INDEX idx_gitlab_links_organization ON module_gitlab_project_links;
DROP INDEX idx_gitlab_items_organization ON module_gitlab_sync_items;
DROP INDEX idx_gitlab_logs_organization ON module_gitlab_sync_logs;
ALTER TABLE module_gitlab_sync_logs DROP COLUMN organization_id;
ALTER TABLE module_gitlab_sync_items DROP COLUMN organization_id;
ALTER TABLE module_gitlab_project_links DROP COLUMN organization_id;
ALTER TABLE module_gitlab_connections DROP COLUMN organization_id;
