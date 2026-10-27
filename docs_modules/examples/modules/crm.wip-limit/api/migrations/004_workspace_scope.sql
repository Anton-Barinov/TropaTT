ALTER TABLE `crm_wip_limits`
  ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `id`;

-- A legacy per-user limit has no workspace owner. Assign it only when that
-- user belongs to exactly one workspace; ambiguous and unowned rows stay NULL
-- and therefore remain invisible to workspace-scoped reads (fail closed).
UPDATE `crm_wip_limits` AS `w`
JOIN (
  SELECT `user_id`, MIN(`organization_id`) AS `organization_id`
  FROM `organization_memberships`
  GROUP BY `user_id`
  HAVING COUNT(DISTINCT `organization_id`) = 1
) AS `membership` ON `membership`.`user_id` = `w`.`user_id`
SET `w`.`organization_id` = `membership`.`organization_id`
WHERE `w`.`organization_id` IS NULL;

ALTER TABLE `crm_wip_limits`
  DROP INDEX `uq_crm_wip_limits_user`,
  ADD UNIQUE KEY `uq_crm_wip_limits_org_user` (`organization_id`, `user_id`),
  ADD KEY `idx_crm_wip_limits_org` (`organization_id`);

ALTER TABLE `crm_wip_scope_limits`
  ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `id`;

-- Scope ownership is authoritative on the CRM team/project row, not inferred
-- from the user who created the legacy setting.
UPDATE `crm_wip_scope_limits` AS `w`
JOIN `teams` AS `t` ON `t`.`id` = `w`.`scope_id`
SET `w`.`organization_id` = `t`.`organization_id`
WHERE `w`.`scope_type` = 'team' AND `w`.`organization_id` IS NULL;

UPDATE `crm_wip_scope_limits` AS `w`
JOIN `projects` AS `p` ON `p`.`id` = `w`.`scope_id`
SET `w`.`organization_id` = `p`.`organization_id`
WHERE `w`.`scope_type` = 'project' AND `w`.`organization_id` IS NULL;

ALTER TABLE `crm_wip_scope_limits`
  DROP INDEX `uq_crm_wip_scope_limits`,
  ADD UNIQUE KEY `uq_crm_wip_scope_limits_org` (`organization_id`, `scope_type`, `scope_id`),
  ADD KEY `idx_crm_wip_scope_limits_org` (`organization_id`);
