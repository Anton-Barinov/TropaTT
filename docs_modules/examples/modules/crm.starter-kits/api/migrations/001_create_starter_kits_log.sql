-- 001_create_starter_kits_log.sql
CREATE TABLE IF NOT EXISTS `crm_starter_kits_applied` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `kit_id` varchar(64) NOT NULL,
  `kit_version` varchar(32) NOT NULL,
  `applied_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `components_json` text DEFAULT NULL,
  `created_objects_json` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_starter_kits_org` (`organization_id`, `kit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
