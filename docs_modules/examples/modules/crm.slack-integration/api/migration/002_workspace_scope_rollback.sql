-- Rollback workspace scope for crm.slack-integration; this removes organization assignments but preserves module records.
DROP INDEX idx_slack_deliveries_organization ON module_slack_deliveries;
ALTER TABLE module_slack_deliveries DROP COLUMN locked_token;
ALTER TABLE module_slack_deliveries DROP COLUMN organization_id;
DROP INDEX idx_slack_rules_organization ON module_slack_rules;
ALTER TABLE module_slack_rules DROP COLUMN organization_id;
DROP INDEX idx_slack_connections_organization ON module_slack_connections;
ALTER TABLE module_slack_connections DROP COLUMN organization_id;
