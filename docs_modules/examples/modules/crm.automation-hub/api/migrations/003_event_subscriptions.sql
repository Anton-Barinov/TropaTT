ALTER TABLE `crm_automation_recipes`
  ADD COLUMN `webhook_public_id` varchar(64) DEFAULT NULL,
  ADD COLUMN `events_json` text DEFAULT NULL;

CREATE UNIQUE INDEX `uq_crm_automation_webhook` ON `crm_automation_recipes` (`webhook_public_id`);
