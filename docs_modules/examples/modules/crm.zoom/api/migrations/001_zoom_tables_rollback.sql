-- Rollback for 001_zoom_tables.sql. Remove module-owned meeting, recording, and workspace settings data on module uninstall.
DROP TABLE IF EXISTS `module_zoom_recordings`;
DROP TABLE IF EXISTS `module_zoom_meetings`;
DROP TABLE IF EXISTS `module_zoom_settings`;
