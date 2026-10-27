-- Reserve a recipe correlation ID before the outbound request. The unique key
-- prevents parallel PHP requests on shared hosting from dispatching duplicates.
CREATE TABLE IF NOT EXISTS `crm_automation_delivery_claims` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `recipe_id` bigint(20) unsigned NOT NULL,
  `correlation_id` varchar(64) NOT NULL,
  `delivery_public_id` varchar(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'sending',
  `response_code` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auto_delivery_claim` (`organization_id`, `recipe_id`, `correlation_id`),
  UNIQUE KEY `uq_auto_delivery_claim_public` (`delivery_public_id`),
  KEY `idx_auto_delivery_claim_status` (`organization_id`, `status`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
