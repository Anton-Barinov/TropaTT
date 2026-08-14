-- Add request timestamp used by the client-side one-request-per-second throttle.
SET @activecollab_has_last_request_at := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'module_activecollab_rate_limits'
    AND column_name = 'last_request_at'
);
SET @activecollab_add_last_request_at := IF(
  @activecollab_has_last_request_at = 0,
  'ALTER TABLE `module_activecollab_rate_limits` ADD COLUMN `last_request_at` DATETIME NULL AFTER `last_http_status`',
  'SELECT 1'
);
PREPARE activecollab_stmt FROM @activecollab_add_last_request_at;
EXECUTE activecollab_stmt;
DEALLOCATE PREPARE activecollab_stmt;
