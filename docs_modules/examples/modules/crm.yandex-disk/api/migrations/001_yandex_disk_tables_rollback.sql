-- Rollback for 001_yandex_disk_tables.sql. Remove module-owned file mappings and workspace credentials on module uninstall.
DROP TABLE IF EXISTS `module_yandex_disk_files`;
DROP TABLE IF EXISTS `module_yandex_disk_settings`;
