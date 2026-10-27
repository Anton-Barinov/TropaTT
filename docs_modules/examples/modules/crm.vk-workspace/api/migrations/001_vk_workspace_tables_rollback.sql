-- Rollback for 001_vk_workspace_tables.sql. Remove module-owned participant, meeting, and workspace settings data on module uninstall.
DROP TABLE IF EXISTS `module_vk_workspace_participants`;
DROP TABLE IF EXISTS `module_vk_workspace_meetings`;
DROP TABLE IF EXISTS `module_vk_workspace_settings`;
