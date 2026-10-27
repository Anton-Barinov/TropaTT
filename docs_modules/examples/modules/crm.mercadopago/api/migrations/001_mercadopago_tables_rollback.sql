-- Rollback for 001_mercadopago_tables.sql. Remove module-owned payment records, audit history, and workspace credentials on module uninstall.
DROP TABLE IF EXISTS `module_mercadopago_audit_log`;
DROP TABLE IF EXISTS `module_mercadopago_payments`;
DROP TABLE IF EXISTS `module_mercadopago_settings`;
