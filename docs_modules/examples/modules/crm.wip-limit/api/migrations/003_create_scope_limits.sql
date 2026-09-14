-- crm.wip-limit: team- and project-level WIP limits. MySQL/InnoDB only.
CREATE TABLE IF NOT EXISTS `crm_wip_scope_limits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_type` VARCHAR(16) NOT NULL,
  `scope_id` INT UNSIGNED NOT NULL,
  `max_tasks` INT UNSIGNED NOT NULL DEFAULT 5,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_wip_scope_limits` (`scope_type`, `scope_id`),
  KEY `idx_crm_wip_scope_limits_type_active` (`scope_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
