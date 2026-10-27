-- Preserve webhook subscriptions and queued deliveries during module rollback.
-- The native Webhooks screen remains the recovery/management surface after the
-- recipe-to-subscription mapping columns are removed. Deleting deliveries here
-- would silently discard customer automation history.
DROP INDEX `uq_crm_automation_webhook` ON `crm_automation_recipes`;
ALTER TABLE `crm_automation_recipes`
  DROP COLUMN `events_json`,
  DROP COLUMN `webhook_public_id`;
