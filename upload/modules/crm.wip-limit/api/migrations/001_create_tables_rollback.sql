-- crm.wip-limit rollback for 001_create_tables.sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `crm_wip_counts`;
DROP TABLE IF EXISTS `crm_wip_limits`;
SET FOREIGN_KEY_CHECKS = 1;
