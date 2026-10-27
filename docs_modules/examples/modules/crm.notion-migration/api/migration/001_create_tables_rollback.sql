-- Notion Migration Module: rollback for initial tables

DROP TABLE IF EXISTS module_notion_settings;
DROP TABLE IF EXISTS module_notion_user_mappings;
DROP TABLE IF EXISTS module_notion_import_logs;
DROP TABLE IF EXISTS module_notion_import_items;
DROP TABLE IF EXISTS module_notion_import_jobs;
DROP TABLE IF EXISTS module_notion_connections;
