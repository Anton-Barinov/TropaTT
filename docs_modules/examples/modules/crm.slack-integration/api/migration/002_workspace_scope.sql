-- Add workspace isolation to crm.slack-integration; legacy rows are assigned only when ownership is unambiguous.
ALTER TABLE module_slack_connections ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE module_slack_rules ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE module_slack_deliveries ADD COLUMN organization_id BIGINT UNSIGNED NULL;
ALTER TABLE module_slack_deliveries ADD COLUMN locked_token VARCHAR(64) NULL;
UPDATE module_slack_connections c JOIN (SELECT om.user_id, MIN(om.organization_id) AS organization_id FROM organization_memberships om GROUP BY om.user_id HAVING COUNT(*) = 1) owner_org ON owner_org.user_id = c.created_by_user_id SET c.organization_id = owner_org.organization_id WHERE c.organization_id IS NULL;
UPDATE module_slack_rules r JOIN module_slack_connections c ON c.id = r.connection_id SET r.organization_id = c.organization_id WHERE r.organization_id IS NULL;
UPDATE module_slack_deliveries d JOIN module_slack_connections c ON c.id = d.connection_id SET d.organization_id = c.organization_id WHERE d.organization_id IS NULL;
CREATE INDEX idx_slack_connections_organization ON module_slack_connections (organization_id);
CREATE INDEX idx_slack_rules_organization ON module_slack_rules (organization_id);
CREATE INDEX idx_slack_deliveries_organization ON module_slack_deliveries (organization_id);
