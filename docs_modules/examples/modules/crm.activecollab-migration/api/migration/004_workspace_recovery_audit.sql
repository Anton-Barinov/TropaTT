-- Audit trail for explicit root recovery of legacy unscoped connections.
CREATE TABLE IF NOT EXISTS `module_activecollab_workspace_recovery_audit` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `organization_id` BIGINT UNSIGNED NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NOT NULL,
  `connection_public_id` VARCHAR(64) NOT NULL,
  `jobs_assigned` INT UNSIGNED NOT NULL DEFAULT 0,
  `details_json` JSON NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_acw_recovery_audit_org` (`organization_id`, `created_at`),
  INDEX `idx_acw_recovery_audit_connection` (`connection_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
