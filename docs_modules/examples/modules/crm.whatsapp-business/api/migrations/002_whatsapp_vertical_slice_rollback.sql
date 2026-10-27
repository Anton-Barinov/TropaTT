DROP TABLE IF EXISTS `crm_whatsapp_send_requests`;
ALTER TABLE `crm_whatsapp_conversations` DROP COLUMN `last_inbound_at`;
ALTER TABLE `crm_whatsapp_configs` DROP COLUMN `configured_by_user_id`;
