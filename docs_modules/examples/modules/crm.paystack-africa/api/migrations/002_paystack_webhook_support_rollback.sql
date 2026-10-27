-- Rollback for 002_paystack_webhook_support.sql. Remove module-owned webhook routes and receipt records on downgrade/uninstall.
DROP TABLE IF EXISTS `module_paystack_webhook_events`;
DROP TABLE IF EXISTS `module_paystack_webhook_routes`;
