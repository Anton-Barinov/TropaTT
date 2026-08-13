-- Upgrade migration for installations created from the first Toggl module build.
-- Dynamic checks keep this idempotent on fresh installs where 001 already contains
-- the columns. Worklog intervals are core-compatible columns and are not removed
-- by module rollback.
SET @has_rate_last_request := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_toggl_rate_limits' AND COLUMN_NAME = 'last_request_at'
);
SET @sql_rate_last_request := IF(@has_rate_last_request = 0,
  'ALTER TABLE module_toggl_rate_limits ADD COLUMN last_request_at DATETIME NULL AFTER last_http_status',
  'SELECT 1');
PREPARE stmt_rate_last_request FROM @sql_rate_last_request;
EXECUTE stmt_rate_last_request;
DEALLOCATE PREPARE stmt_rate_last_request;

SET @has_started := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_logs' AND COLUMN_NAME = 'started_at'
);
SET @sql_started := IF(@has_started = 0,
  'ALTER TABLE work_logs ADD COLUMN started_at DATETIME NULL AFTER logged_at',
  'SELECT 1');
PREPARE stmt_started FROM @sql_started;
EXECUTE stmt_started;
DEALLOCATE PREPARE stmt_started;

SET @has_ended := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_logs' AND COLUMN_NAME = 'ended_at'
);
SET @sql_ended := IF(@has_ended = 0,
  'ALTER TABLE work_logs ADD COLUMN ended_at DATETIME NULL AFTER started_at',
  'SELECT 1');
PREPARE stmt_ended FROM @sql_ended;
EXECUTE stmt_ended;
DEALLOCATE PREPARE stmt_ended;
