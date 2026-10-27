-- Rollback for 001_dropbox_tables.sql. Module-owned file mappings and workspace credentials are removed on module uninstall.
DROP TABLE IF EXISTS `module_dropbox_files`;
DROP TABLE IF EXISTS `module_dropbox_settings`;
