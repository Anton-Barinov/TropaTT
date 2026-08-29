/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `activity_feed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `action` varchar(64),
  `actor_public_id` varchar(64),
  `payload` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_intent_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `intent_code` varchar(128),
  `provider_id` int(11),
  `model` varchar(190),
  `feature_flag` varchar(128),
  `required_permission` varchar(128),
  `allow_sensitive_context` int(11) DEFAULT 0,
  `max_tokens` int(11),
  `temperature` varchar(16),
  `is_enabled` int(11) DEFAULT 1,
  `intent_payload` text,
  `created_by_user_id` int(11),
  `updated_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `intent_code` (`intent_code`),
  KEY `idx_ai_intent_settings_provider` (`provider_id`),
  KEY `idx_ai_intent_settings_intent_created` (`intent_code`,`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `job_type` varchar(64),
  `action_type` varchar(128),
  `intent_code` varchar(128),
  `status` varchar(32),
  `requested_by_user_id` int(11),
  `scope_type` varchar(64),
  `scope_public_id` varchar(64),
  `idempotency_key_hash` varchar(255),
  `payload_json` text,
  `result_json` mediumtext,
  `error_code` varchar(64),
  `error_message` text,
  `created_at` datetime,
  `started_at` datetime,
  `finished_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_jobs_status_created` (`status`,`created_at`),
  KEY `idx_ai_jobs_action_created` (`action_type`,`created_at`),
  KEY `idx_ai_jobs_actor_created` (`requested_by_user_id`,`created_at`),
  KEY `idx_ai_jobs_public_id` (`public_id`),
  KEY `idx_ai_jobs_intent_created` (`intent_code`,`created_at`),
  KEY `idx_ai_jobs_scope_created` (`scope_type`,`scope_public_id`,`created_at`),
  KEY `idx_ai_jobs_status_created_v2` (`status`,`created_at`),
  KEY `idx_ai_jobs_actor_created_v2` (`requested_by_user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_json_schemas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `intent_code` varchar(128),
  `schema_version` varchar(32),
  `schema_json` text,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11),
  `updated_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_json_schemas_intent` (`intent_code`),
  KEY `idx_ai_json_schemas_public_id` (`public_id`),
  KEY `idx_ai_json_schemas_intent_created` (`intent_code`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_prompt_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `intent_code` varchar(128),
  `locale` varchar(16),
  `version` int(11) DEFAULT 1,
  `template_text` text,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11),
  `updated_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_prompt_templates_intent_locale` (`intent_code`,`locale`),
  KEY `idx_ai_prompt_templates_public_id` (`public_id`),
  KEY `idx_ai_prompt_templates_intent_created` (`intent_code`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_provider_secrets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `provider_id` int(11),
  `secret_encrypted` text,
  `key_hint` varchar(64),
  `rotated_at` datetime,
  `created_by_user_id` int(11),
  `updated_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_ai_provider_secrets_provider_id` (`provider_id`),
  KEY `idx_ai_provider_secrets_public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `provider_code` varchar(64),
  `title` varchar(255),
  `base_url` text,
  `api_path` varchar(255),
  `default_model` varchar(190),
  `timeout_ms` int(11),
  `max_tokens` int(11),
  `temperature` varchar(16),
  `extra_headers` text,
  `provider_payload` text,
  `is_active` int(11) DEFAULT 1,
  `is_default` int(11) DEFAULT 0,
  `created_by_user_id` int(11),
  `updated_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `uq_ai_providers_provider_code_active` (`provider_code`,`is_active`),
  KEY `idx_ai_providers_default_active` (`is_default`,`is_active`),
  KEY `idx_ai_providers_public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `intent_code` varchar(128),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `summary` text,
  `suggestion_json` text,
  `status` varchar(32) DEFAULT 'draft',
  `created_by_user_id` int(11),
  `confirmed_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  `expires_at` datetime,
  `input_hash` varchar(64),
  `cache_key` varchar(64),
  `dependency_fingerprint` varchar(64),
  `cache_status` varchar(32),
  `stale_reason` varchar(64),
  `date_bucket` varchar(32),
  `provider_public_id` varchar(64),
  `provider_code` varchar(64),
  `model` varchar(190),
  `last_used_at` datetime,
  `usage_count` int(11) DEFAULT 0,
  `request_id` varchar(64),
  `invalidated_at` datetime,
  `result_meta_json` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_suggestions_scope` (`entity_type`,`entity_public_id`,`created_at`),
  KEY `idx_ai_suggestions_actor` (`created_by_user_id`,`created_at`),
  KEY `idx_ai_suggestions_public_id` (`public_id`),
  KEY `idx_ai_suggestions_intent_created` (`intent_code`,`created_at`),
  KEY `idx_ai_suggestions_entity_created` (`entity_type`,`entity_public_id`,`created_at`),
  KEY `idx_ai_suggestions_status_created` (`status`,`created_at`),
  KEY `idx_ai_suggestions_actor_created_v2` (`created_by_user_id`,`created_at`),
  KEY `idx_ai_suggestions_input_hash_scope` (`intent_code`,`entity_type`,`entity_public_id`,`input_hash`),
  KEY `idx_ai_suggestions_cache_lookup_v2` (`created_by_user_id`,`intent_code`,`entity_type`,`entity_public_id`,`cache_key`,`created_at`),
  KEY `idx_ai_suggestions_cache_status_v2` (`cache_status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `provider_public_id` varchar(64),
  `action_type` varchar(128),
  `intent_code` varchar(128),
  `status` varchar(32),
  `error_code` varchar(64),
  `request_tokens` int(11),
  `response_tokens` int(11),
  `total_tokens` int(11),
  `latency_ms` int(11),
  `is_sensitive_context` int(11) DEFAULT 0,
  `request_meta` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_usage_logs_actor_created` (`user_id`,`created_at`),
  KEY `idx_ai_usage_logs_action_created` (`action_type`,`created_at`),
  KEY `idx_ai_usage_logs_public_id` (`public_id`),
  KEY `idx_ai_usage_logs_intent_created` (`intent_code`,`created_at`),
  KEY `idx_ai_usage_logs_status_created` (`status`,`created_at`),
  KEY `idx_ai_usage_logs_actor_created_v2` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `api_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `scopes` text,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `client_id` int(11),
  `user_id` int(11),
  `key_hash` varchar(255),
  `scopes` text,
  `expires_at` datetime,
  `revoked_at` datetime,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `approval_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `title` varchar(255),
  `requester_user_id` int(11),
  `status` varchar(32),
  `comment` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `approval_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `request_id` int(11),
  `reviewer_user_id` int(11),
  `status` varchar(32),
  `comment` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `actor_public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `action` varchar(64),
  `details` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_public_id`),
  KEY `idx_audit_logs_created` (`created_at`),
  KEY `idx_audit_logs_actor_created` (`actor_public_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=511 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `automation_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `trigger_code` varchar(64),
  `action_code` varchar(64),
  `payload` text,
  `is_enabled` int(11) DEFAULT 1,
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_automation_rules_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `automation_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `rule_id` int(11),
  `status` varchar(32),
  `error` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `business_calendars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `timezone` varchar(64),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `description` text,
  `starts_at` datetime,
  `ends_at` datetime,
  `owner_user_id` int(11),
  `project_id` int(11),
  `task_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  `source_type` varchar(64),
  `source_owner_user_id` int(11),
  `source_external_id` varchar(255),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_calendar_events_source_owner` (`source_type`,`source_owner_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `chat_message_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `message_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `action` varchar(32),
  `before_text` longtext,
  `after_text` longtext,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `reply_to_message_id` int(11),
  `message_type` varchar(32) NOT NULL DEFAULT 'text',
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `edited_at` datetime,
  `deleted_at` datetime,
  `deleted_by_user_id` int(11),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `chat_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'member',
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `muted_until` datetime,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_participant` (`chat_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1508547 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `chat_read_markers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_message_id` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_read` (`chat_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=271 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'direct',
  `project_id` int(11),
  `team_id` int(11),
  `last_message_at` datetime,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime,
  `archived_by_user_id` int(11),
  `archived_participant_ids` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1732 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `checklist_id` int(11),
  `title` varchar(255),
  `is_done` int(11) DEFAULT 0,
  `sort_order` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `task_id` int(11),
  `title` varchar(255),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `company_id` int(11),
  `title` varchar(255),
  `client_type` varchar(32),
  `legal_name` varchar(255),
  `person_last_name` varchar(120),
  `person_first_name` varchar(120),
  `person_middle_name` varchar(120),
  `person_birth_date` date,
  `tax_inn` varchar(12),
  `tax_kpp` varchar(9),
  `tax_ogrn` varchar(13),
  `tax_ogrnip` varchar(15),
  `bank_account` varchar(34),
  `bank_name` varchar(255),
  `bank_bik` varchar(9),
  `bank_corr_account` varchar(34),
  `website` varchar(2048),
  `messenger` varchar(190),
  `address_legal` text,
  `address_postal` text,
  `notes` text,
  `extra_attributes` text,
  `email` varchar(190),
  `phone` varchar(64),
  `status` varchar(64),
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_clients_created_by` (`created_by_user_id`),
  KEY `idx_clients_type` (`client_type`),
  KEY `idx_clients_tax_inn` (`tax_inn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `comment_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `task_id` int(11),
  `body` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_comment_drafts_user_task` (`user_id`,`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `task_id` int(11),
  `project_id` int(11),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `author_user_id` int(11),
  `body` text,
  `visibility` varchar(32) DEFAULT 'internal',
  `created_at` datetime,
  `updated_at` datetime,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_comments_task` (`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4994 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_companies_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `company_id` int(11),
  `client_id` int(11),
  `counterparty_id` int(11),
  `role` varchar(64),
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `full_name` varchar(255),
  `email` varchar(190),
  `phone` varchar(64),
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  `user_id` int(11),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_contacts_created_by` (`created_by_user_id`),
  KEY `idx_contacts_counterparty` (`counterparty_id`),
  KEY `idx_contacts_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `core_update_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(100) NOT NULL,
  `from_version` varchar(50),
  `from_build` varchar(50),
  `from_sha` char(40),
  `to_version` varchar(50) NOT NULL,
  `to_build` varchar(50) NOT NULL,
  `to_sha` char(40) NOT NULL,
  `channel` varchar(50) NOT NULL,
  `status` enum('started','success','failed','rolled_back') NOT NULL,
  `risk_level` varchar(20),
  `package_type` varchar(20),
  `backup_id` varchar(100),
  `started_at` datetime NOT NULL,
  `finished_at` datetime,
  `error_message` text,
  `created_by_user_id` bigint(20),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_core_update_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `core_update_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(100) NOT NULL,
  `level` enum('debug','info','warning','error') NOT NULL,
  `step` varchar(100),
  `message` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`context`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_core_update_log_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `counterparties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `created_by_user_id` int(11),
  `title` varchar(255) NOT NULL,
  `counterparty_type` varchar(32) NOT NULL DEFAULT 'organization',
  `status` varchar(64) NOT NULL DEFAULT 'active',
  `legal_name` varchar(255),
  `person_last_name` varchar(120),
  `person_first_name` varchar(120),
  `person_middle_name` varchar(120),
  `person_birth_date` date,
  `tax_inn` varchar(12),
  `tax_kpp` varchar(9),
  `tax_ogrn` varchar(13),
  `tax_ogrnip` varchar(15),
  `bank_account` varchar(34),
  `bank_name` varchar(255),
  `bank_bik` varchar(9),
  `bank_corr_account` varchar(34),
  `website` varchar(2048),
  `messenger` varchar(190),
  `address_legal` text,
  `address_postal` text,
  `notes` text,
  `extra_attributes` text,
  `email` varchar(190),
  `phone` varchar(64),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `address_actual` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_counterparties_type` (`counterparty_type`),
  KEY `idx_counterparties_status` (`status`),
  KEY `idx_counterparties_created_by` (`created_by_user_id`),
  KEY `idx_counterparties_tax_inn` (`tax_inn`)
) ENGINE=InnoDB AUTO_INCREMENT=331 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `crm_wip_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `max_tasks` int(10) unsigned NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_wip_limits_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `crm_wip_scope_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` varchar(16) NOT NULL,
  `scope_id` int(10) unsigned NOT NULL,
  `max_tasks` int(10) unsigned NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_wip_scope_limits` (`scope_type`,`scope_id`),
  KEY `idx_crm_wip_scope_limits_type_active` (`scope_type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `custom_field_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `field_id` int(11),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `value` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `scope` varchar(64),
  `code` varchar(64),
  `title` varchar(255),
  `type` varchar(64),
  `options` text,
  `is_required` int(11) DEFAULT 0,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cycle_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `cycle_id` bigint(20) unsigned NOT NULL,
  `snapshot_date` date NOT NULL,
  `total_tasks` int(11) NOT NULL DEFAULT 0,
  `completed_tasks` int(11) NOT NULL DEFAULT 0,
  `open_tasks` int(11) NOT NULL DEFAULT 0,
  `overdue_tasks` int(11) NOT NULL DEFAULT 0,
  `unassigned_tasks` int(11) NOT NULL DEFAULT 0,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`payload_json`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cycle_snapshots_public_id` (`public_id`),
  UNIQUE KEY `uq_cycle_snapshots_cycle_date` (`cycle_id`,`snapshot_date`),
  KEY `idx_cycle_snapshots_cycle_created` (`cycle_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cycle_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `cycle_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `active_key` varchar(191),
  `added_by_user_id` bigint(20) unsigned NOT NULL,
  `added_at` datetime NOT NULL,
  `removed_by_user_id` bigint(20) unsigned,
  `removed_at` datetime,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cycle_tasks_public_id` (`public_id`),
  UNIQUE KEY `uq_cycle_tasks_active_key` (`active_key`),
  KEY `idx_cycle_tasks_cycle_active` (`cycle_id`,`deleted_at`),
  KEY `idx_cycle_tasks_task_active` (`task_id`,`deleted_at`),
  KEY `idx_cycle_tasks_added_by` (`added_by_user_id`,`added_at`),
  KEY `idx_cycle_tasks_removed_at` (`removed_at`),
  KEY `idx_cycle_tasks_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `manager_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `entity_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(32),
  `entity_public_id` varchar(64),
  `tag_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  KEY `idx_entity_tags_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `estimate_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `estimate_set_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `code` varchar(64) NOT NULL,
  `numeric_value` decimal(12,2),
  `color` varchar(32),
  `description` text,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `active_key` varchar(191),
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `archived_at` datetime,
  `deleted_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_estimate_options_public_id` (`public_id`),
  UNIQUE KEY `uq_estimate_options_active_key` (`active_key`),
  KEY `idx_estimate_options_set_active` (`estimate_set_id`,`is_active`),
  KEY `idx_estimate_options_set_sort` (`estimate_set_id`,`sort_order`),
  KEY `idx_estimate_options_numeric` (`numeric_value`),
  KEY `idx_estimate_options_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `estimate_sets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `scope_type` varchar(32) NOT NULL DEFAULT 'project',
  `project_id` bigint(20) unsigned,
  `name` varchar(255) NOT NULL,
  `code` varchar(64) NOT NULL,
  `estimate_type` varchar(64) NOT NULL DEFAULT 'custom',
  `unit_label` varchar(32),
  `currency_code` varchar(8),
  `description` text,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `active_key` varchar(191),
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `archived_at` datetime,
  `deleted_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_estimate_sets_public_id` (`public_id`),
  UNIQUE KEY `uq_estimate_sets_active_key` (`active_key`),
  KEY `idx_estimate_sets_scope` (`scope_type`,`project_id`),
  KEY `idx_estimate_sets_project_active` (`project_id`,`is_active`),
  KEY `idx_estimate_sets_type` (`estimate_type`),
  KEY `idx_estimate_sets_code` (`code`),
  KEY `idx_estimate_sets_archived_at` (`archived_at`),
  KEY `idx_estimate_sets_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `export_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `type` varchar(64),
  `status` varchar(32),
  `payload` text,
  `result` text,
  `created_at` datetime,
  `updated_at` datetime,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime,
  `locked_at` datetime,
  `started_at` datetime,
  `finished_at` datetime,
  `last_error` text,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_export_jobs_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_export_jobs_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `external_user_project_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `granted_by_user_id` bigint(20) unsigned,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ext_user_project_access` (`user_id`,`project_id`),
  KEY `idx_ext_user_project_access_user` (`user_id`),
  KEY `idx_ext_user_project_access_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `feature_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `code` varchar(128),
  `is_enabled` int(11) DEFAULT 1,
  `payload` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_feature_flags_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(32),
  `entity_public_id` varchar(64),
  `uploader_user_id` int(11),
  `original_name` varchar(255),
  `storage_path` text,
  `mime_type` varchar(128),
  `size_bytes` bigint(20),
  `is_deleted` int(11) DEFAULT 0,
  `created_at` datetime,
  `source_type` varchar(64),
  `source_id` varchar(255),
  `source_url` varchar(2048),
  `checksum` char(64),
  `source_payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`source_payload_json`)),
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_files_entity` (`entity_type`,`entity_public_id`),
  KEY `idx_files_source` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `google_calendar_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `google_account_email` varchar(190),
  `refresh_token_encrypted` text NOT NULL,
  `access_token_encrypted` text,
  `access_token_expires_at` datetime,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `last_error` text,
  `last_sync_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_google_calendar_connection_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `google_calendar_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `client_id_encrypted` text NOT NULL,
  `client_secret_encrypted` text NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_google_calendar_credentials_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `google_calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `source_id` int(11) NOT NULL,
  `google_event_id` varchar(512) NOT NULL,
  `crm_event_public_id` varchar(64),
  `recurring_event_id` varchar(512),
  `etag` varchar(255),
  `google_updated_at` datetime,
  `is_all_day` int(11) NOT NULL DEFAULT 0,
  `all_day_start` date,
  `all_day_end` date,
  `last_synced_at` datetime,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `last_error` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_google_calendar_event` (`source_id`,`google_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `google_calendar_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `connection_id` int(11) NOT NULL,
  `calendar_id` varchar(512) NOT NULL,
  `summary` varchar(255),
  `timezone` varchar(128),
  `direction` varchar(32) NOT NULL DEFAULT 'google_to_crm',
  `is_enabled` int(11) NOT NULL DEFAULT 1,
  `is_primary` int(11) NOT NULL DEFAULT 0,
  `sync_token` text,
  `watch_channel_id` varchar(128),
  `watch_resource_id` varchar(255),
  `watch_expiration` bigint(20),
  `last_sync_at` datetime,
  `last_error` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `watch_token_encrypted` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_google_calendar_source` (`connection_id`,`calendar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `calendar_id` int(11),
  `holiday_date` date,
  `title` varchar(255),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_ai_iterations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `iteration` int(11) NOT NULL DEFAULT 1,
  `type` varchar(32) NOT NULL DEFAULT 'analyze',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`request_payload`)),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`response_payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=174 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `analysis_type` varchar(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`input_snapshot_json`)),
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`result_json`)),
  `input_hash` varchar(64),
  `prompt_version` varchar(32),
  `schema_version` varchar(32),
  `result_text` text,
  `confidence` varchar(32),
  `error_message` text,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `started_at` datetime,
  `completed_at` datetime,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_analysis_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `step_key` varchar(64) NOT NULL,
  `step_order` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext,
  `result_json` longtext,
  `result_text` longtext,
  `error_message` text,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime,
  `completed_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text,
  `selected_option_key` text,
  `selected_option_label` text,
  `selected_options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`selected_options_json`)),
  `is_custom` tinyint(4) NOT NULL DEFAULT 0,
  `is_unknown` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_final_recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `status` varchar(30),
  `status_label` varchar(200),
  `recommendation_score` decimal(5,2),
  `ai_recommendation_score` decimal(5,2),
  `calculated_recommendation_score` decimal(5,2),
  `potential_score` decimal(5,2),
  `feasibility_score` decimal(5,2),
  `risk_score` decimal(5,2),
  `data_completeness_score` decimal(5,2),
  `plan_quality_score` decimal(5,2),
  `blocker_score` decimal(5,2),
  `confidence_score` decimal(5,2),
  `recommendation_json` mediumtext,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_implementation_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `plan_json` mediumtext,
  `summary` text,
  `planning_horizon` varchar(50),
  `plan_type` varchar(20),
  `confidence_score` decimal(3,2),
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_pitfalls_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `overall_hidden_complexity` varchar(20),
  `overall_summary` text,
  `pitfalls_json` mediumtext,
  `data_confidence` decimal(3,2),
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_potential_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `potential_json` mediumtext,
  `potential_score` decimal(5,2),
  `potential_level` varchar(20),
  `confidence_score` decimal(3,2),
  `completeness_score` decimal(3,2),
  `calculation_type` varchar(50),
  `verdict` text,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_question_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `cycle_number` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext,
  `ai_response_json` longtext,
  `summary_for_user` text,
  `created_at` datetime NOT NULL,
  `completed_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL DEFAULT 1,
  `question_text` text NOT NULL,
  `reason` text,
  `question_type` varchar(32) NOT NULL DEFAULT 'single_choice',
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`options_json`)),
  `allow_custom` tinyint(4) NOT NULL DEFAULT 1,
  `allow_unknown` tinyint(4) NOT NULL DEFAULT 1,
  `required` tinyint(4) NOT NULL DEFAULT 1,
  `dimension` text,
  `impact` varchar(32) DEFAULT 'medium',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_refined_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `profile_json` mediumtext,
  `summary` text,
  `idea_type` varchar(50),
  `specificity_level` varchar(20),
  `completeness_score` decimal(3,2),
  `confidence_score` decimal(3,2),
  `next_action` varchar(50),
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_risk_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `risk_report_json` mediumtext,
  `overall_risk_score` decimal(5,2),
  `overall_risk_level` varchar(20),
  `critical_risks_count` int(11) DEFAULT 0,
  `high_risks_count` int(11) DEFAULT 0,
  `medium_risks_count` int(11) DEFAULT 0,
  `low_risks_count` int(11) DEFAULT 0,
  `confidence_score` decimal(3,2),
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_suggested_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `tasks_json` mediumtext,
  `summary` text,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_task_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `parent_id` int(11),
  `crm_task_id` int(11),
  `title` varchar(255) NOT NULL,
  `description` text,
  `type` varchar(64),
  `stage` varchar(64),
  `priority` varchar(32) DEFAULT 'normal',
  `acceptance_criteria_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`acceptance_criteria_json`)),
  `dependencies_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`dependencies_json`)),
  `estimated_duration` varchar(128),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_selected` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_understanding_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `profile_json` mediumtext,
  `summary` text,
  `idea_type` varchar(50),
  `specificity_level` varchar(20),
  `completeness_score` decimal(3,2),
  `confidence_score` decimal(3,2),
  `next_action` varchar(50),
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idea_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idea_vote` (`idea_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `goal` text,
  `author_user_id` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new',
  `category` varchar(64),
  `region` varchar(190),
  `visibility` varchar(16) NOT NULL DEFAULT 'public',
  `target_date` date,
  `type` varchar(64),
  `domain` varchar(128),
  `maturity` varchar(64),
  `known_facts_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`known_facts_json`)),
  `unknowns_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`unknowns_json`)),
  `assumptions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`assumptions_json`)),
  `coverage_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`coverage_json`)),
  `vote_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  `ai_analysis` text,
  `ai_analysis_at` datetime,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prompt_version` text,
  `schema_version` text,
  `source_context_json` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `key_hash` varchar(255),
  `route` varchar(255),
  `response_payload` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `impersonation_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `admin_user_id` int(11),
  `target_user_id` int(11),
  `reason` text,
  `started_at` datetime,
  `ended_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `import_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `type` varchar(64),
  `status` varchar(32),
  `payload` text,
  `result` text,
  `created_at` datetime,
  `updated_at` datetime,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime,
  `locked_at` datetime,
  `started_at` datetime,
  `finished_at` datetime,
  `last_error` text,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_import_jobs_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_import_jobs_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `install_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `installed_at` datetime,
  `version` varchar(20),
  `payload` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `intake_item_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `intake_item_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned,
  `event_type` varchar(64) NOT NULL,
  `field_name` varchar(128),
  `old_value` text,
  `new_value` text,
  `comment` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_item_activities_public_id` (`public_id`),
  KEY `idx_intake_item_activities_item_created` (`intake_item_id`,`created_at`),
  KEY `idx_intake_item_activities_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_intake_item_activities_type_created` (`event_type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `intake_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `project_id` bigint(20) unsigned,
  `client_id` bigint(20) unsigned,
  `contact_id` bigint(20) unsigned,
  `title` varchar(255) NOT NULL,
  `description` text,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `priority_code` varchar(64),
  `source_type` varchar(64) NOT NULL DEFAULT 'manual',
  `source_ref` varchar(255),
  `source_email` varchar(255),
  `external_source` varchar(255),
  `external_id` varchar(255),
  `extra_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`extra_json`)),
  `due_at` datetime,
  `snoozed_until` datetime,
  `assignee_user_id` bigint(20) unsigned,
  `creator_user_id` bigint(20) unsigned NOT NULL,
  `accepted_task_id` bigint(20) unsigned,
  `duplicate_intake_item_id` bigint(20) unsigned,
  `duplicate_task_id` bigint(20) unsigned,
  `resolution_note` text,
  `resolved_by_user_id` bigint(20) unsigned,
  `resolved_at` datetime,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_items_public_id` (`public_id`),
  KEY `idx_intake_items_status` (`status`),
  KEY `idx_intake_items_project_status` (`project_id`,`status`),
  KEY `idx_intake_items_client_status` (`client_id`,`status`),
  KEY `idx_intake_items_assignee_status` (`assignee_user_id`,`status`),
  KEY `idx_intake_items_creator_status` (`creator_user_id`,`status`),
  KEY `idx_intake_items_snoozed_until` (`snoozed_until`),
  KEY `idx_intake_items_due_at` (`due_at`),
  KEY `idx_intake_items_created_at` (`created_at`),
  KEY `idx_intake_items_updated_at` (`updated_at`),
  KEY `idx_intake_items_deleted_at` (`deleted_at`),
  KEY `idx_intake_items_external` (`external_source`,`external_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `email` varchar(190),
  `invited_by_user_id` int(11),
  `token_hash` varchar(255),
  `expires_at` datetime,
  `accepted_at` datetime,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `page_id` int(11) NOT NULL,
  `parent_id` int(11),
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `resolved_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  `source_type` varchar(64),
  `source_id` varchar(255),
  `source_author_name` varchar(255),
  `source_created_at` datetime,
  `anchor_text` varchar(500),
  `anchor_path` varchar(500),
  `is_inline` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_comments_page` (`page_id`,`created_at`),
  KEY `idx_knowledge_comments_parent` (`parent_id`),
  KEY `idx_knowledge_comments_source` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `page_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255),
  `content_html` text,
  `content_text` text,
  `content_json` text,
  `base_row_version` int(11) DEFAULT 1,
  `autosaved_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_knowledge_drafts_page_user` (`page_id`,`user_id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_entity_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `page_id` int(11) NOT NULL,
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `relation_type` varchar(64) DEFAULT 'related',
  `created_by_user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_knowledge_links_page_entity` (`page_id`,`entity_type`,`entity_public_id`),
  KEY `idx_knowledge_links_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_page_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `subject_type` varchar(32),
  `subject_id` int(11),
  `access_level` varchar(32),
  `created_by_user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_page_properties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` bigint(20) unsigned NOT NULL,
  `property_key` varchar(190) NOT NULL,
  `property_value` longtext,
  `property_type` varchar(32) NOT NULL DEFAULT 'string',
  `source_type` varchar(64),
  `source_id` varchar(255),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_knowledge_page_property` (`page_id`,`property_key`),
  KEY `idx_knowledge_page_properties_source` (`source_type`,`source_id`),
  KEY `idx_knowledge_page_properties_key` (`property_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_page_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `page_public_id` varchar(64) NOT NULL,
  `version_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext,
  `content_text` longtext,
  `summary` text,
  `visibility` varchar(32),
  `status` varchar(32),
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`tags_json`)),
  `links_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`links_json`)),
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`meta_json`)),
  `change_type` varchar(64) NOT NULL DEFAULT 'update',
  `change_note` varchar(1000),
  `restored_from_version_number` int(11),
  `restored_from_version_public_id` varchar(64),
  `created_by_user_id` bigint(20) unsigned,
  `created_by_actor_type` varchar(32) NOT NULL DEFAULT 'user',
  `created_by_display_name` varchar(255),
  `request_id` varchar(128),
  `source_type` varchar(64),
  `source_ref` varchar(255),
  `content_hash` char(64),
  `created_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_knowledge_page_versions_public_id` (`public_id`),
  UNIQUE KEY `uq_knowledge_page_versions_page_number` (`page_id`,`version_number`),
  KEY `idx_knowledge_page_versions_page_created` (`page_id`,`created_at`),
  KEY `idx_knowledge_page_versions_page_public_created` (`page_public_id`,`created_at`),
  KEY `idx_knowledge_page_versions_created_by` (`created_by_user_id`,`created_at`),
  KEY `idx_knowledge_page_versions_change_type` (`change_type`,`created_at`),
  KEY `idx_knowledge_page_versions_hash` (`content_hash`),
  KEY `idx_knowledge_page_versions_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_page_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `user_id` int(11),
  `source` varchar(32) DEFAULT 'direct',
  `viewed_at` datetime,
  PRIMARY KEY (`id`),
  KEY `idx_knowledge_views_page` (`page_id`,`viewed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `space_id` int(11) NOT NULL,
  `parent_id` int(11),
  `title` varchar(255) NOT NULL,
  `slug` varchar(190),
  `page_type` varchar(64) DEFAULT 'article',
  `status` varchar(32) DEFAULT 'draft',
  `content_html` text,
  `content_text` text,
  `content_json` text,
  `excerpt` text,
  `owner_user_id` int(11),
  `last_editor_user_id` int(11),
  `published_by_user_id` int(11),
  `published_at` datetime,
  `review_due_at` datetime,
  `reviewed_at` datetime,
  `review_status` varchar(32),
  `reviewer_user_id` int(11),
  `sort_order` int(11) DEFAULT 0,
  `path` varchar(2048),
  `depth` int(11) DEFAULT 0,
  `children_count` int(11) DEFAULT 0,
  `comments_count` int(11) DEFAULT 0,
  `attachments_count` int(11) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `client_visible` tinyint(1) NOT NULL DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `row_version` int(11) DEFAULT 1,
  `created_at` datetime,
  `updated_at` datetime,
  `deleted_at` datetime,
  `source_type` varchar(64),
  `source_id` varchar(255),
  `source_url` varchar(2048),
  `source_payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`source_payload_json`)),
  `locked_at` datetime,
  `locked_by_user_id` bigint(20) unsigned,
  `lock_reason` varchar(1000),
  `last_version_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_pages_space_parent_sort` (`space_id`,`parent_id`,`sort_order`),
  KEY `idx_knowledge_pages_space_status_updated` (`space_id`,`status`,`updated_at`),
  KEY `idx_knowledge_pages_parent` (`parent_id`),
  KEY `idx_knowledge_pages_owner` (`owner_user_id`),
  KEY `idx_knowledge_pages_review_due` (`review_due_at`),
  KEY `idx_knowledge_pages_type` (`page_type`),
  KEY `idx_knowledge_pages_source` (`source_type`,`source_id`),
  FULLTEXT KEY `ft_knowledge_pages_title_text` (`title`,`content_text`),
  FULLTEXT KEY `ft_search` (`title`,`content_text`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_search_index` (
  `page_id` int(11) NOT NULL,
  `space_id` int(11) NOT NULL,
  `title` varchar(255),
  `content_text` text,
  `tags_text` text,
  `entity_text` text,
  `status` varchar(32),
  `page_type` varchar(64),
  `updated_at` datetime,
  PRIMARY KEY (`page_id`),
  KEY `idx_knowledge_search_space_status_updated` (`space_id`,`status`,`updated_at`),
  KEY `idx_knowledge_search_page_type` (`page_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_search_queries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query` varchar(255),
  `user_id` int(11),
  `results_count` int(11) DEFAULT 0,
  `clicked_page_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_space_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `subject_type` varchar(32),
  `subject_id` int(11),
  `access_level` varchar(32),
  `created_by_user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_spaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255) NOT NULL,
  `slug` varchar(160),
  `description` text,
  `icon` varchar(64),
  `color` varchar(32),
  `owner_user_id` int(11),
  `visibility` varchar(32) DEFAULT 'public',
  `default_access_level` varchar(32) DEFAULT 'view',
  `tree_version` int(11) DEFAULT 1,
  `content_version` int(11) DEFAULT 1,
  `permissions_version` int(11) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `is_system` int(11) DEFAULT 0,
  `is_archived` int(11) DEFAULT 0,
  `row_version` int(11) DEFAULT 1,
  `created_at` datetime,
  `updated_at` datetime,
  `source_type` varchar(64),
  `source_id` varchar(255),
  `source_url` varchar(2048),
  `source_payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`source_payload_json`)),
  `parent_id` int(11),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_knowledge_spaces_archived_sort` (`is_archived`,`sort_order`),
  KEY `idx_knowledge_spaces_owner` (`owner_user_id`),
  KEY `idx_knowledge_spaces_parent` (`parent_id`),
  KEY `idx_knowledge_spaces_source` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `knowledge_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `page_type` varchar(64),
  `description` text,
  `content_html` text,
  `content_json` text,
  `is_system` int(11) DEFAULT 0,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_templates_type_active` (`page_type`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `mentions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `mentioned_user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `migration_key` varchar(191),
  `description` varchar(255),
  `applied_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_key` (`migration_key`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `project_id` int(11),
  `title` varchar(255),
  `due_at` datetime,
  `status` varchar(32),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_milestones_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notification_push_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11) NOT NULL,
  `notification_public_id` varchar(64),
  `payload_json` text NOT NULL,
  `status` varchar(32) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime,
  `locked_at` datetime,
  `last_error` text,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_push_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_push_queue_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=260 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notification_push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `endpoint` text,
  `p256dh` varchar(1024),
  `auth` varchar(1024),
  `user_agent` text,
  `device_label` varchar(255),
  `is_active` int(11) DEFAULT 1,
  `last_error` text,
  `last_seen_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_notif_push_subscriptions_user_active` (`user_id`,`is_active`,`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `category` varchar(64),
  `title` varchar(255),
  `body` text,
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `action_code` varchar(64),
  `actor_user_id` int(11),
  `actor_public_id` varchar(64),
  `actor_name` varchar(255),
  `link` varchar(1024),
  `payload_json` text,
  `is_read` int(11) DEFAULT 0,
  `created_at` datetime,
  `read_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  KEY `idx_notifications_user_unread_created` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_user_category_unread` (`user_id`,`category`,`is_read`),
  KEY `idx_notifications_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8390 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `organization_memberships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_code` varchar(32) NOT NULL,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_membership_org_user` (`organization_id`,`user_id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_org_membership_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `slug` varchar(120),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `token_hash` varchar(255),
  `expires_at` datetime,
  `used_at` datetime,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `code` varchar(128),
  `title` varchar(255),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `priorities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `code` varchar(64),
  `title` varchar(255),
  `weight` int(11),
  `color` varchar(32),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `project_module_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `link_type` varchar(64) NOT NULL DEFAULT 'other',
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_module_links_public_id` (`public_id`),
  KEY `idx_project_module_links_module_active` (`module_id`,`deleted_at`),
  KEY `idx_project_module_links_type` (`link_type`),
  KEY `idx_project_module_links_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `project_module_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_code` varchar(64) NOT NULL DEFAULT 'member',
  `added_by_user_id` bigint(20) unsigned NOT NULL,
  `added_at` datetime NOT NULL,
  `removed_by_user_id` bigint(20) unsigned,
  `removed_at` datetime,
  `active_key` varchar(191),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_module_members_public_id` (`public_id`),
  UNIQUE KEY `uq_project_module_members_active_key` (`active_key`),
  KEY `idx_project_module_members_module_active` (`module_id`,`deleted_at`),
  KEY `idx_project_module_members_user_active` (`user_id`,`deleted_at`),
  KEY `idx_project_module_members_role` (`role_code`),
  KEY `idx_project_module_members_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `project_module_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `added_by_user_id` bigint(20) unsigned NOT NULL,
  `added_at` datetime NOT NULL,
  `removed_by_user_id` bigint(20) unsigned,
  `removed_at` datetime,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `active_key` varchar(191),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_module_tasks_public_id` (`public_id`),
  UNIQUE KEY `uq_project_module_tasks_active_key` (`active_key`),
  KEY `idx_project_module_tasks_module_active` (`module_id`,`deleted_at`),
  KEY `idx_project_module_tasks_task_active` (`task_id`,`deleted_at`),
  KEY `idx_project_module_tasks_added_by` (`added_by_user_id`,`added_at`),
  KEY `idx_project_module_tasks_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `project_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `status` varchar(32) NOT NULL DEFAULT 'planned',
  `lead_user_id` bigint(20) unsigned,
  `start_at` datetime,
  `target_at` datetime,
  `completed_at` datetime,
  `color` varchar(32),
  `icon` varchar(64),
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`meta_json`)),
  `progress_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`progress_snapshot_json`)),
  `row_version` int(11) NOT NULL DEFAULT 1,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned,
  `archived_at` datetime,
  `deleted_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_modules_public_id` (`public_id`),
  KEY `idx_project_modules_project_status` (`project_id`,`status`),
  KEY `idx_project_modules_project_sort` (`project_id`,`sort_order`),
  KEY `idx_project_modules_lead_status` (`lead_user_id`,`status`),
  KEY `idx_project_modules_target_at` (`target_at`),
  KEY `idx_project_modules_archived_at` (`archived_at`),
  KEY `idx_project_modules_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `project_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `payload` text,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_project_templates_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `description` text,
  `status_code` varchar(64),
  `priority_code` varchar(64),
  `client_public_id` varchar(64),
  `manager_user_id` int(11),
  `team_public_id` varchar(64),
  `task_key_prefix` varchar(10),
  `task_key_prefix_locked` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime,
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  `deleted_at` datetime,
  `row_version` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_projects_task_key_prefix` (`task_key_prefix`),
  KEY `idx_projects_status` (`status_code`),
  KEY `idx_projects_updated_public` (`updated_at`,`public_id`),
  KEY `idx_projects_archived_updated` (`archived_at`,`updated_at`,`public_id`),
  KEY `idx_projects_creator_archived_updated` (`created_by_user_id`,`archived_at`,`updated_at`),
  KEY `idx_projects_manager_archived_updated` (`manager_user_id`,`archived_at`,`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1625 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `key` varchar(64) NOT NULL,
  `attempts` text NOT NULL,
  `blocked_until` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `attempts_count` int(11) NOT NULL DEFAULT 0,
  `window_start` int(11) NOT NULL DEFAULT 0,
  `expires_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`key`),
  KEY `idx_rate_limits_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `user_id` int(11),
  `reaction` varchar(32),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `recurring_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `rule_id` int(11),
  `entity_public_id` varchar(64),
  `generated_at` datetime,
  `created_at` datetime,
  `next_occurrence` datetime,
  `processed_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `recurring_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `rrule` text,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime,
  `updated_at` datetime,
  `last_processed_at` datetime,
  `title` varchar(255),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `recycle_bin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `payload` text,
  `deleted_by_user_id` int(11),
  `deleted_at` datetime,
  `restored_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `task_id` int(11),
  `remind_at` datetime,
  `status` varchar(32),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `request_id` varchar(64),
  `correlation_id` varchar(64),
  `user_public_id` varchar(64),
  `route` varchar(255),
  `method` varchar(16),
  `status_code` int(11),
  `result_code` varchar(64),
  `duration_ms` int(11),
  `payload` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_request_logs_request` (`request_id`),
  KEY `idx_request_logs_created` (`created_at`),
  KEY `idx_request_logs_user_created` (`user_public_id`,`created_at`),
  KEY `idx_request_logs_method_created` (`method`,`created_at`),
  KEY `idx_request_logs_result_created` (`result_code`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=53776 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11),
  `permission_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=170 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `code` varchar(64),
  `title` varchar(255),
  `is_system` int(11) DEFAULT 0,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `saved_view_user_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `saved_view_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `last_used_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_view_user_preferences_public_id` (`public_id`),
  UNIQUE KEY `uq_saved_view_user_preferences_view_user` (`saved_view_id`,`user_id`),
  KEY `idx_saved_view_user_preferences_user_pinned` (`user_id`,`is_pinned`,`sort_order`),
  KEY `idx_saved_view_user_preferences_last_used` (`user_id`,`last_used_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `saved_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `updated_by_user_id` bigint(20) unsigned,
  `entity_type` varchar(64),
  `title` varchar(255),
  `description` text,
  `filters` text,
  `access_level` varchar(32) NOT NULL DEFAULT 'private',
  `display_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`display_filters`)),
  `display_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`display_properties`)),
  `rich_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`rich_filters`)),
  `layout` varchar(32) NOT NULL DEFAULT 'list',
  `group_by` varchar(64),
  `order_by` varchar(64),
  `order_dir` varchar(8),
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `archived_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_saved_views_entity_access` (`entity_type`,`access_level`),
  KEY `idx_saved_views_user_entity` (`user_id`,`entity_type`),
  KEY `idx_saved_views_archived` (`archived_at`),
  KEY `idx_saved_views_sort_order` (`sort_order`),
  KEY `idx_saved_views_system_locked` (`is_system`,`is_locked`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `actor_public_id` varchar(64),
  `event_type` varchar(64),
  `ip` varchar(128),
  `user_agent` text,
  `details` text,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_security_logs_created` (`created_at`),
  KEY `idx_security_logs_actor_created` (`actor_public_id`,`created_at`),
  KEY `idx_security_logs_event_created` (`event_type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=990 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `scope` varchar(64),
  `name` varchar(190),
  `value` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sla_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `response_minutes` int(11),
  `resolve_minutes` int(11),
  `escalation_payload` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `scope` varchar(64),
  `code` varchar(64),
  `title` varchar(255),
  `color` varchar(32),
  `sort_order` int(11),
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime,
  `updated_at` datetime,
  `wip_limit` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sticky_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `context_type` varchar(64) NOT NULL DEFAULT 'personal',
  `context_public_id` varchar(64),
  `title` varchar(255),
  `body` text NOT NULL,
  `color` varchar(32) NOT NULL DEFAULT 'yellow',
  `background_color` varchar(32),
  `visibility` varchar(32) NOT NULL DEFAULT 'private',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `converted_to_entity_type` varchar(64),
  `converted_to_entity_public_id` varchar(64),
  `converted_at` datetime,
  `converted_by_user_id` bigint(20) unsigned,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`meta_json`)),
  `row_version` int(11) NOT NULL DEFAULT 1,
  `archived_at` datetime,
  `deleted_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sticky_notes_public_id` (`public_id`),
  KEY `idx_sticky_notes_owner_context` (`owner_user_id`,`context_type`,`context_public_id`),
  KEY `idx_sticky_notes_context` (`context_type`,`context_public_id`),
  KEY `idx_sticky_notes_owner_pinned` (`owner_user_id`,`is_pinned`,`sort_order`),
  KEY `idx_sticky_notes_visibility` (`visibility`),
  KEY `idx_sticky_notes_archived_at` (`archived_at`),
  KEY `idx_sticky_notes_deleted_at` (`deleted_at`),
  KEY `idx_sticky_notes_converted` (`converted_to_entity_type`,`converted_to_entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `entity_type` varchar(64),
  `entity_public_id` varchar(64),
  `user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `subtasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `task_id` int(11),
  `title` varchar(255),
  `status_code` varchar(64),
  `assignee_user_id` int(11),
  `sort_order` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sync_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `scope` varchar(64),
  `cursor_value` varchar(255),
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `code` varchar(64),
  `title` varchar(255),
  `color` varchar(32),
  `created_at` datetime,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_activity_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `task_public_id` varchar(64) NOT NULL,
  `actor_user_id` bigint(20) unsigned,
  `actor_type` varchar(32) NOT NULL DEFAULT 'user',
  `actor_public_id` varchar(64),
  `actor_display_name` varchar(255),
  `event_type` varchar(96) NOT NULL,
  `field_name` varchar(128),
  `old_value` text,
  `new_value` text,
  `old_label` varchar(255),
  `new_label` varchar(255),
  `related_entity_type` varchar(64),
  `related_entity_id` bigint(20) unsigned,
  `related_entity_public_id` varchar(64),
  `related_entity_label` varchar(255),
  `message_key` varchar(128),
  `message_text` varchar(1000),
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`payload_json`)),
  `visibility` varchar(32) NOT NULL DEFAULT 'default',
  `request_id` varchar(128),
  `source_type` varchar(64),
  `source_ref` varchar(255),
  `created_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_activity_events_public_id` (`public_id`),
  KEY `idx_task_activity_events_task_created` (`task_id`,`created_at`),
  KEY `idx_task_activity_events_task_public_created` (`task_public_id`,`created_at`),
  KEY `idx_task_activity_events_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_task_activity_events_event_type` (`event_type`,`created_at`),
  KEY `idx_task_activity_events_related` (`related_entity_type`,`related_entity_public_id`),
  KEY `idx_task_activity_events_request_id` (`request_id`),
  KEY `idx_task_activity_events_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8060 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_assignees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11),
  `user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_dependencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `task_id` int(11),
  `depends_on_task_id` int(11),
  `dependency_type` varchar(32),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_dep_task` (`task_id`),
  KEY `idx_dep_depends_on_task` (`depends_on_task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_estimates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `task_public_id` varchar(64) NOT NULL,
  `estimate_set_id` bigint(20) unsigned NOT NULL,
  `estimate_option_id` bigint(20) unsigned,
  `numeric_value` decimal(12,2),
  `text_value` varchar(255),
  `currency_code` varchar(8),
  `note` varchar(1000),
  `assigned_by_user_id` bigint(20) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL,
  `updated_by_user_id` bigint(20) unsigned,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `active_key` varchar(191),
  `archived_at` datetime,
  `deleted_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_estimates_public_id` (`public_id`),
  UNIQUE KEY `uq_task_estimates_active_key` (`active_key`),
  KEY `idx_task_estimates_task_active` (`task_id`,`deleted_at`),
  KEY `idx_task_estimates_task_public` (`task_public_id`,`deleted_at`),
  KEY `idx_task_estimates_set_value` (`estimate_set_id`,`numeric_value`),
  KEY `idx_task_estimates_option` (`estimate_option_id`),
  KEY `idx_task_estimates_assigned_by` (`assigned_by_user_id`,`assigned_at`),
  KEY `idx_task_estimates_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_key_counters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope_key` varchar(64) NOT NULL,
  `scope_type` varchar(32) NOT NULL,
  `project_id` bigint(20) unsigned,
  `prefix` varchar(10) NOT NULL,
  `current_value` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_key_counters_scope_key` (`scope_key`),
  KEY `idx_task_key_counters_project_id` (`project_id`),
  KEY `idx_task_key_counters_prefix` (`prefix`)
) ENGINE=InnoDB AUTO_INCREMENT=11307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `parent_task_id` int(11),
  `child_task_id` int(11),
  `relation_type` varchar(32),
  `sort_order` int(11) DEFAULT 0,
  `legacy_subtask_public_id` varchar(64),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `idx_task_rel_child_type` (`child_task_id`,`relation_type`),
  UNIQUE KEY `idx_task_rel_legacy` (`legacy_subtask_public_id`),
  KEY `idx_task_rel_parent_type_sort` (`parent_task_id`,`relation_type`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_relations_v2` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `source_task_id` bigint(20) unsigned NOT NULL,
  `target_task_id` bigint(20) unsigned NOT NULL,
  `relation_type` varchar(32) NOT NULL,
  `active_key` varchar(191),
  `note` text,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  `row_version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_relations_v2_public_id` (`public_id`),
  UNIQUE KEY `uq_task_relations_v2_active_key` (`active_key`),
  KEY `idx_task_relations_v2_source` (`source_task_id`,`deleted_at`),
  KEY `idx_task_relations_v2_target` (`target_task_id`,`deleted_at`),
  KEY `idx_task_relations_v2_type` (`relation_type`,`deleted_at`),
  KEY `idx_task_relations_v2_created_by` (`created_by_user_id`,`created_at`),
  KEY `idx_task_relations_v2_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `task_id` int(11),
  `old_status` varchar(64),
  `new_status` varchar(64),
  `changed_by_user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1903 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `payload` text,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_task_templates_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `task_watchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11),
  `user_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `project_id` int(11),
  `parent_task_id` int(11),
  `title` varchar(255),
  `description` text,
  `status_code` varchar(64),
  `sla_policy_id` int(11),
  `sla_response_deadline` datetime,
  `sla_resolve_deadline` datetime,
  `sla_breached` tinyint(4) NOT NULL DEFAULT 0,
  `priority_code` varchar(64),
  `task_key` varchar(32),
  `task_key_prefix` varchar(10),
  `task_sequence_number` bigint(20) unsigned,
  `due_at` datetime,
  `start_at` datetime,
  `end_at` datetime,
  `assignee_user_id` int(11),
  `creator_user_id` int(11),
  `archived_at` datetime,
  `deleted_at` datetime,
  `created_at` datetime,
  `updated_at` datetime,
  `source_type` varchar(64),
  `source_id` varchar(255),
  `source_url` varchar(2048),
  `source_payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`source_payload_json`)),
  `row_version` int(11) DEFAULT 1,
  `client_public_id` varchar(64),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_tasks_task_key` (`task_key`),
  KEY `idx_tasks_project` (`project_id`),
  KEY `idx_tasks_status` (`status_code`),
  KEY `idx_tasks_due` (`due_at`),
  KEY `idx_tasks_updated_public` (`updated_at`,`public_id`),
  KEY `idx_tasks_active_updated` (`deleted_at`,`archived_at`,`updated_at`,`public_id`),
  KEY `idx_tasks_project_active_updated` (`project_id`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_status_active_updated` (`status_code`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_priority_active_updated` (`priority_code`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_assignee_active_updated` (`assignee_user_id`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_creator_active_updated` (`creator_user_id`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_task_key_prefix_sequence` (`task_key_prefix`,`task_sequence_number`),
  KEY `idx_tasks_task_sequence_number` (`task_sequence_number`),
  KEY `idx_tasks_client_public_id` (`client_public_id`),
  KEY `idx_tasks_source` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `team_type` varchar(32) NOT NULL DEFAULT 'team',
  `parent_id` int(11),
  `code` varchar(64),
  `title` varchar(255),
  `manager_user_id` int(11),
  `created_by_user_id` int(11),
  `member_user_ids` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_teams_created_by` (`created_by_user_id`),
  KEY `idx_teams_type` (`team_type`),
  KEY `idx_teams_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `two_factor_secrets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `secret_hash` varchar(255),
  `backup_codes` text,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `role_id` int(11),
  `created_at` datetime,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `token_hash` varchar(255),
  `ip` varchar(128),
  `user_agent` text,
  `device_fingerprint` varchar(64),
  `device_name` varchar(190),
  `expires_at` datetime,
  `revoked_at` datetime,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_sessions_token` (`token_hash`),
  KEY `idx_sessions_user_device` (`user_id`,`device_fingerprint`)
) ENGINE=InnoDB AUTO_INCREMENT=656 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `login` varchar(120),
  `email` varchar(190),
  `password_hash` varchar(255),
  `auth_token_hash` varchar(255),
  `full_name` varchar(255),
  `locale` varchar(16),
  `is_active` int(11) DEFAULT 1,
  `is_root` int(11) DEFAULT 0,
  `created_by_user_id` int(11),
  `created_at` datetime,
  `updated_at` datetime,
  `deleted_at` datetime,
  `cost_rate` decimal(12,2),
  `bill_rate` decimal(12,2),
  `is_external` tinyint(1) NOT NULL DEFAULT 0,
  `external_invitation_expires_at` datetime,
  `external_role` varchar(20) NOT NULL DEFAULT 'observer',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `login` (`login`),
  KEY `idx_users_login` (`login`),
  KEY `idx_users_created_by` (`created_by_user_id`),
  KEY `idx_users_is_external` (`is_external`),
  KEY `idx_users_external_invitation_expiry` (`is_external`,`external_invitation_expires_at`),
  KEY `idx_users_external_role` (`external_role`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `webhook_id` int(11),
  `event_code` varchar(64),
  `status` varchar(32),
  `response_code` int(11),
  `created_at` datetime,
  `payload_json` text,
  `signature` varchar(255),
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime,
  `locked_at` datetime,
  `last_error` text,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_webhook_deliveries_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_webhook_deliveries_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `webhook_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `title` varchar(255),
  `endpoint` text,
  `secret_hash` varchar(255),
  `events` text,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `work_cycles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `goal` text,
  `status` varchar(32) NOT NULL DEFAULT 'planned',
  `start_at` datetime,
  `end_at` datetime,
  `timezone` varchar(64),
  `owner_user_id` bigint(20) unsigned,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `completed_by_user_id` bigint(20) unsigned,
  `completed_at` datetime,
  `archived_at` datetime,
  `progress_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`progress_snapshot_json`)),
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`meta_json`)),
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_work_cycles_public_id` (`public_id`),
  KEY `idx_work_cycles_project_status` (`project_id`,`status`),
  KEY `idx_work_cycles_project_dates` (`project_id`,`start_at`,`end_at`),
  KEY `idx_work_cycles_owner_status` (`owner_user_id`,`status`),
  KEY `idx_work_cycles_created_by` (`created_by_user_id`,`created_at`),
  KEY `idx_work_cycles_completed_at` (`completed_at`),
  KEY `idx_work_cycles_archived_at` (`archived_at`),
  KEY `idx_work_cycles_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `work_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `user_id` int(11),
  `task_id` int(11),
  `minutes_spent` int(11),
  `note` text,
  `logged_at` datetime,
  `started_at` datetime,
  `ended_at` datetime,
  `created_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_work_logs_interval` (`user_id`,`started_at`,`ended_at`)
) ENGINE=InnoDB AUTO_INCREMENT=471 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `working_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64),
  `calendar_id` int(11),
  `weekday` int(11),
  `start_time` varchar(8),
  `end_time` varchar(8),
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `yandex_calendar_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_email` varchar(190) NOT NULL,
  `caldav_username` varchar(190) NOT NULL,
  `credential_encrypted` text NOT NULL,
  `auth_mode` varchar(32) NOT NULL DEFAULT 'app_password',
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `last_error` text,
  `last_sync_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_yandex_calendar_connection_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `yandex_calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `source_id` int(11) NOT NULL,
  `external_uid` varchar(512) NOT NULL,
  `recurrence_id` varchar(128),
  `event_href` varchar(1024),
  `etag` varchar(255),
  `recurrence_rule` text,
  `event_start` datetime,
  `event_end` datetime,
  `is_all_day` int(11) NOT NULL DEFAULT 0,
  `all_day_start` date,
  `all_day_end` date,
  `crm_event_public_id` varchar(64),
  `last_synced_at` datetime,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `last_error` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_yandex_calendar_event` (`source_id`,`external_uid`,`recurrence_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `yandex_calendar_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `connection_id` int(11) NOT NULL,
  `calendar_href` varchar(512) NOT NULL,
  `display_name` varchar(255),
  `timezone` varchar(128),
  `direction` varchar(32) NOT NULL DEFAULT 'yandex_to_crm',
  `is_enabled` int(11) NOT NULL DEFAULT 1,
  `is_primary` int(11) NOT NULL DEFAULT 0,
  `ctag` varchar(255),
  `last_sync_at` datetime,
  `last_error` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_yandex_calendar_source` (`connection_id`,`calendar_href`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
