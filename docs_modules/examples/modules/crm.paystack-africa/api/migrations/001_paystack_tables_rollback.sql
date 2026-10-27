-- Rollback for 001_paystack_tables.sql. Remove module-owned transaction, audit, and workspace credential data on module uninstall.
DROP TABLE IF EXISTS `module_paystack_audit_log`;
DROP TABLE IF EXISTS `module_paystack_transactions`;
DROP TABLE IF EXISTS `module_paystack_settings`;
