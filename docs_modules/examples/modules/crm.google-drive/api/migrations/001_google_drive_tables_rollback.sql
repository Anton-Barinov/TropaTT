-- Rollback for 001_google_drive_tables.sql. Module-owned file mappings and workspace credentials are removed on module uninstall.
DROP TABLE IF EXISTS `module_google_drive_files`;
DROP TABLE IF EXISTS `module_google_drive_settings`;
