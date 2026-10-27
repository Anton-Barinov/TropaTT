-- Workspace isolation migration. Legacy records with ambiguous ownership remain NULL and hidden until explicitly claimed.
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_connections' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_connections` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_connections' AND index_name='idx_acw_connections_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_connections_org` ON `module_activecollab_connections` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_jobs' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_jobs` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_jobs' AND index_name='idx_acw_jobs_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_jobs_org` ON `module_activecollab_jobs` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_job_items' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_job_items` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_job_items' AND index_name='idx_acw_job_items_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_job_items_org` ON `module_activecollab_job_items` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_source_mappings' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_source_mappings` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_source_mappings' AND index_name='idx_acw_source_mappings_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_source_mappings_org` ON `module_activecollab_source_mappings` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_user_mappings' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_user_mappings` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_user_mappings' AND index_name='idx_acw_user_mappings_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_user_mappings_org` ON `module_activecollab_user_mappings` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_rate_limits' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_rate_limits` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_rate_limits' AND index_name='idx_acw_rate_limits_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_rate_limits_org` ON `module_activecollab_rate_limits` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_job_logs' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_job_logs` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_job_logs' AND index_name='idx_acw_job_logs_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_job_logs_org` ON `module_activecollab_job_logs` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;
SET @acw_has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='module_activecollab_unresolved_entities' AND column_name='organization_id');
SET @acw_sql := IF(@acw_has=0, 'ALTER TABLE `module_activecollab_unresolved_entities` ADD COLUMN `organization_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE acw_stmt FROM @acw_sql; EXECUTE acw_stmt; DEALLOCATE PREPARE acw_stmt;
SET @acw_has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='module_activecollab_unresolved_entities' AND index_name='idx_acw_unresolved_entities_org');
SET @acw_sql_idx := IF(@acw_has_idx=0, 'CREATE INDEX `idx_acw_unresolved_entities_org` ON `module_activecollab_unresolved_entities` (`organization_id`)', 'SELECT 1');
PREPARE acw_stmt_idx FROM @acw_sql_idx; EXECUTE acw_stmt_idx; DEALLOCATE PREPARE acw_stmt_idx;

-- Existing ownership is backfilled only when the membership table exists and the
-- creator has exactly one membership. Core versions without workspaces safely skip it.
SET @acw_has_memberships := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='organization_memberships');
SET @acw_backfill_connections := IF(@acw_has_memberships=1,
  'UPDATE module_activecollab_connections c JOIN (SELECT user_id, MIN(organization_id) organization_id FROM organization_memberships GROUP BY user_id HAVING COUNT(DISTINCT organization_id)=1) m ON m.user_id=c.created_by_user_id SET c.organization_id=m.organization_id WHERE c.organization_id IS NULL',
  'SELECT 1');
PREPARE acw_backfill_stmt FROM @acw_backfill_connections;
EXECUTE acw_backfill_stmt;
DEALLOCATE PREPARE acw_backfill_stmt;
-- Jobs inherit the already-resolved parent connection scope.
UPDATE module_activecollab_jobs j JOIN module_activecollab_connections c ON c.id=j.connection_id AND c.organization_id IS NOT NULL
SET j.organization_id=c.organization_id WHERE j.organization_id IS NULL;
UPDATE module_activecollab_job_items i JOIN module_activecollab_jobs j ON j.id=i.job_id AND j.organization_id IS NOT NULL SET i.organization_id=j.organization_id WHERE i.organization_id IS NULL;
UPDATE module_activecollab_job_logs l JOIN module_activecollab_jobs j ON j.id=l.job_id AND j.organization_id IS NOT NULL SET l.organization_id=j.organization_id WHERE l.organization_id IS NULL;
UPDATE module_activecollab_unresolved_entities u JOIN module_activecollab_jobs j ON j.id=u.job_id AND j.organization_id IS NOT NULL SET u.organization_id=j.organization_id WHERE u.organization_id IS NULL;
UPDATE module_activecollab_source_mappings m JOIN module_activecollab_connections c ON c.id=m.connection_id AND c.organization_id IS NOT NULL SET m.organization_id=c.organization_id WHERE m.organization_id IS NULL;
UPDATE module_activecollab_user_mappings m JOIN module_activecollab_connections c ON c.id=m.connection_id AND c.organization_id IS NOT NULL SET m.organization_id=c.organization_id WHERE m.organization_id IS NULL;
UPDATE module_activecollab_rate_limits r JOIN module_activecollab_connections c ON c.id=r.connection_id AND c.organization_id IS NOT NULL SET r.organization_id=c.organization_id WHERE r.organization_id IS NULL;
