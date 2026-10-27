-- Add tenant isolation. Legacy data is assigned only when creator membership is unambiguous.
ALTER TABLE module_gitlab_connections ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE module_gitlab_project_links ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE module_gitlab_sync_items ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE module_gitlab_sync_logs ADD COLUMN organization_id BIGINT UNSIGNED NULL;
UPDATE module_gitlab_connections c JOIN (SELECT om.user_id, MIN(om.organization_id) AS organization_id FROM organization_memberships om GROUP BY om.user_id HAVING COUNT(*) = 1) owner_org ON owner_org.user_id = c.created_by_user_id SET c.organization_id = owner_org.organization_id WHERE c.organization_id IS NULL;
UPDATE module_gitlab_project_links l JOIN module_gitlab_connections c ON c.id = l.connection_id SET l.organization_id = c.organization_id WHERE l.organization_id IS NULL;
UPDATE module_gitlab_sync_items i JOIN module_gitlab_project_links l ON l.id = i.link_id SET i.organization_id = l.organization_id WHERE i.organization_id IS NULL;
UPDATE module_gitlab_sync_logs g JOIN module_gitlab_project_links l ON l.id = g.link_id SET g.organization_id = l.organization_id WHERE g.organization_id IS NULL;
CREATE INDEX idx_gitlab_connections_organization ON module_gitlab_connections (organization_id);
CREATE INDEX idx_gitlab_links_organization ON module_gitlab_project_links (organization_id);
CREATE INDEX idx_gitlab_items_organization ON module_gitlab_sync_items (organization_id);
CREATE INDEX idx_gitlab_logs_organization ON module_gitlab_sync_logs (organization_id);
