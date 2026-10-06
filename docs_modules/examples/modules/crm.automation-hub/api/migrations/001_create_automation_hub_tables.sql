-- 001_create_automation_hub_tables.sql
CREATE TABLE IF NOT EXISTS `crm_automation_recipes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `platform` varchar(32) NOT NULL, -- 'n8n', 'make'
  `recipe_key` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `webhook_endpoint_url` varchar(1024) NOT NULL,
  `secret_token_encrypted` text DEFAULT NULL,
  `field_mappings_json` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_auto_recipes_org` (`organization_id`, `platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_automation_delivery_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `recipe_id` bigint(20) unsigned NOT NULL,
  `correlation_id` varchar(64) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `payload_json` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'delivered', -- 'delivered', 'failed', 'retrying'
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_delivery_correlation` (`correlation_id`),
  KEY `idx_delivery_org` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
