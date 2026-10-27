-- Rollback for 001_hubspot_migration_tables.sql. Remove module-owned import session and staging data on module uninstall.
DROP TABLE IF EXISTS `module_hubspot_migration_id_map`;
DROP TABLE IF EXISTS `module_hubspot_migration_staging`;
DROP TABLE IF EXISTS `module_hubspot_migration_sessions`;
