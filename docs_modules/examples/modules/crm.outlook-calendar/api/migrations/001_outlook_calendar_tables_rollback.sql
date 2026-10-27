-- Rollback for 001_outlook_calendar_tables.sql. Remove module-owned synchronization mappings and workspace credentials on module uninstall.
DROP TABLE IF EXISTS `module_outlook_calendar_events_map`;
DROP TABLE IF EXISTS `module_outlook_calendar_connections`;
