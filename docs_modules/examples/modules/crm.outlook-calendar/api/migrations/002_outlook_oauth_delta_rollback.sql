DROP TABLE IF EXISTS `module_outlook_calendar_oauth_states`;
DROP TABLE IF EXISTS `module_outlook_calendar_settings`;
ALTER TABLE `module_outlook_calendar_events_map`
    DROP INDEX `uk_outlook_connection_event`,
    ADD UNIQUE KEY `uk_outlook_event_link` (`workspace_id`, `outlook_event_id`);
ALTER TABLE `module_outlook_calendar_connections`
    DROP COLUMN `delta_next_link`,
    DROP COLUMN `sync_window_start`,
    DROP COLUMN `sync_window_end`,
    DROP COLUMN `sync_lock_token`,
    DROP COLUMN `sync_lock_expires_at`;
