ALTER TABLE `crm_wip_limits`
  ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `id`;

UPDATE `crm_wip_limits`
SET `organization_id` = (SELECT `id` FROM `organizations` ORDER BY `id` ASC LIMIT 1)
WHERE `organization_id` IS NULL
  AND EXISTS (SELECT 1 FROM `organizations`);

ALTER TABLE `crm_wip_limits`
  DROP INDEX `uq_crm_wip_limits_user`,
  ADD UNIQUE KEY `uq_crm_wip_limits_org_user` (`organization_id`, `user_id`),
  ADD KEY `idx_crm_wip_limits_org` (`organization_id`);

ALTER TABLE `crm_wip_scope_limits`
  ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `id`;

UPDATE `crm_wip_scope_limits`
SET `organization_id` = (SELECT `id` FROM `organizations` ORDER BY `id` ASC LIMIT 1)
WHERE `organization_id` IS NULL
  AND EXISTS (SELECT 1 FROM `organizations`);

ALTER TABLE `crm_wip_scope_limits`
  DROP INDEX `uq_crm_wip_scope_limits`,
  ADD UNIQUE KEY `uq_crm_wip_scope_limits_org` (`organization_id`, `scope_type`, `scope_id`),
  ADD KEY `idx_crm_wip_scope_limits_org` (`organization_id`);
