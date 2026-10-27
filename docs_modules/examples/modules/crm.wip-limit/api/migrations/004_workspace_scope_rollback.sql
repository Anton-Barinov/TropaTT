-- Refuse to flatten workspace-specific rules. Temporary unique tables check
-- both legacy uniqueness constraints before any CRM table is altered.
-- Preflight both uniqueness constraints before changing either CRM table.
DROP TEMPORARY TABLE IF EXISTS `_crm_wip_limits_rollback_check`;
CREATE TEMPORARY TABLE `_crm_wip_limits_rollback_check` (
  `user_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uq_check_user` (`user_id`)
) ENGINE=InnoDB;
INSERT INTO `_crm_wip_limits_rollback_check` (`user_id`)
SELECT `user_id` FROM `crm_wip_limits`;
DROP TEMPORARY TABLE `_crm_wip_limits_rollback_check`;

DROP TEMPORARY TABLE IF EXISTS `_crm_wip_scope_rollback_check`;
CREATE TEMPORARY TABLE `_crm_wip_scope_rollback_check` (
  `scope_type` VARCHAR(16) NOT NULL,
  `scope_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uq_check_scope` (`scope_type`, `scope_id`)
) ENGINE=InnoDB;
INSERT INTO `_crm_wip_scope_rollback_check` (`scope_type`, `scope_id`)
SELECT `scope_type`, `scope_id` FROM `crm_wip_scope_limits`;
DROP TEMPORARY TABLE `_crm_wip_scope_rollback_check`;

ALTER TABLE `crm_wip_limits`
  ADD UNIQUE KEY `uq_crm_wip_limits_user` (`user_id`);

ALTER TABLE `crm_wip_scope_limits`
  ADD UNIQUE KEY `uq_crm_wip_scope_limits` (`scope_type`, `scope_id`);

ALTER TABLE `crm_wip_limits`
  DROP INDEX `uq_crm_wip_limits_org_user`,
  DROP INDEX `idx_crm_wip_limits_org`,
  DROP COLUMN `organization_id`;

ALTER TABLE `crm_wip_scope_limits`
  DROP INDEX `uq_crm_wip_scope_limits_org`,
  DROP INDEX `idx_crm_wip_scope_limits_org`,
  DROP COLUMN `organization_id`;
