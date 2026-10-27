CREATE TABLE IF NOT EXISTS `crm_storage_file_migration_failures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `file_public_id` varchar(64) NOT NULL,
  `error_code` varchar(64) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_storage_migration_failure_org_file` (`organization_id`, `file_public_id`),
  KEY `idx_storage_migration_failure_updated` (`organization_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
