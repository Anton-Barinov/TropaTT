SET @activecollab_has_last_request_at := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'module_activecollab_rate_limits'
    AND column_name = 'last_request_at'
);
SET @activecollab_drop_last_request_at := IF(
  @activecollab_has_last_request_at = 1,
  'ALTER TABLE `module_activecollab_rate_limits` DROP COLUMN `last_request_at`',
  'SELECT 1'
);
PREPARE activecollab_stmt FROM @activecollab_drop_last_request_at;
EXECUTE activecollab_stmt;
DEALLOCATE PREPARE activecollab_stmt;
