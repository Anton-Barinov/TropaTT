SET @has_rate_last_request := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_toggl_rate_limits' AND COLUMN_NAME = 'last_request_at'
);
SET @sql_rate_last_request := IF(@has_rate_last_request = 1,
  'ALTER TABLE module_toggl_rate_limits DROP COLUMN last_request_at',
  'SELECT 1');
PREPARE stmt_rate_last_request FROM @sql_rate_last_request;
EXECUTE stmt_rate_last_request;
DEALLOCATE PREPARE stmt_rate_last_request;
