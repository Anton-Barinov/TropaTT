-- Workspace isolation for Asana migration data. Existing rows are backfilled only
-- when the owner has exactly one workspace membership; ambiguous records remain
-- NULL and are deliberately inaccessible until recreated in an explicit workspace.
ALTER TABLE `module_asana_connections`
  ADD COLUMN `organization_id` BIGINT UNSIGNED NULL AFTER `public_id`,
  ADD INDEX `idx_asana_connection_org_owner` (`organization_id`, `created_by_user_id`);

UPDATE `module_asana_connections` c
JOIN (
  SELECT `user_id`, MIN(`organization_id`) AS `organization_id`
  FROM `organization_memberships`
  GROUP BY `user_id`
  HAVING COUNT(DISTINCT `organization_id`) = 1
) membership ON membership.`user_id` = c.`created_by_user_id`
SET c.`organization_id` = membership.`organization_id`
WHERE c.`organization_id` IS NULL;

ALTER TABLE `module_asana_jobs`
  ADD COLUMN `organization_id` BIGINT UNSIGNED NULL AFTER `public_id`,
  ADD INDEX `idx_asana_job_org_owner` (`organization_id`, `created_by_user_id`, `created_at`),
  ADD INDEX `idx_asana_job_org_queue` (`organization_id`, `status`, `lease_until`, `created_at`);

UPDATE `module_asana_jobs` j
JOIN `module_asana_connections` c ON c.`id` = j.`connection_id`
SET j.`organization_id` = c.`organization_id`
WHERE j.`organization_id` IS NULL AND c.`organization_id` IS NOT NULL;
