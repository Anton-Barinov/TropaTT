-- Rollback workspace scope for crm.github-integration; this removes organization assignments but preserves module records.
DROP INDEX idx_github_logs_organization ON module_github_sync_logs;
ALTER TABLE module_github_sync_logs DROP COLUMN organization_id;
DROP INDEX idx_github_items_organization ON module_github_sync_items;
ALTER TABLE module_github_sync_items DROP COLUMN organization_id;
DROP INDEX idx_github_links_organization ON module_github_repo_links;
ALTER TABLE module_github_repo_links DROP COLUMN organization_id;
DROP INDEX idx_github_connections_organization ON module_github_connections;
ALTER TABLE module_github_connections DROP COLUMN organization_id;
