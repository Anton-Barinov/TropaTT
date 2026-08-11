/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_feed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `action` varchar(64) DEFAULT NULL,
  `actor_public_id` varchar(64) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_intent_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `intent_code` varchar(128) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `model` varchar(190) DEFAULT NULL,
  `feature_flag` varchar(128) DEFAULT NULL,
  `required_permission` varchar(128) DEFAULT NULL,
  `allow_sensitive_context` int(11) DEFAULT 0,
  `max_tokens` int(11) DEFAULT NULL,
  `temperature` varchar(16) DEFAULT NULL,
  `is_enabled` int(11) DEFAULT 1,
  `intent_payload` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `intent_code` (`intent_code`),
  KEY `idx_ai_intent_settings_provider` (`provider_id`),
  KEY `idx_ai_intent_settings_intent_created` (`intent_code`,`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `job_type` varchar(64) DEFAULT NULL,
  `action_type` varchar(128) DEFAULT NULL,
  `intent_code` varchar(128) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `requested_by_user_id` int(11) DEFAULT NULL,
  `scope_type` varchar(64) DEFAULT NULL,
  `scope_public_id` varchar(64) DEFAULT NULL,
  `idempotency_key_hash` varchar(255) DEFAULT NULL,
  `payload_json` text DEFAULT NULL,
  `result_json` mediumtext DEFAULT NULL,
  `error_code` varchar(64) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=324 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_json_schemas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `intent_code` varchar(128) DEFAULT NULL,
  `schema_version` varchar(32) DEFAULT NULL,
  `schema_json` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_json_schemas_intent` (`intent_code`),
  KEY `idx_ai_json_schemas_public_id` (`public_id`),
  KEY `idx_ai_json_schemas_intent_created` (`intent_code`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_prompt_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `intent_code` varchar(128) DEFAULT NULL,
  `locale` varchar(16) DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `template_text` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_prompt_templates_intent_locale` (`intent_code`,`locale`),
  KEY `idx_ai_prompt_templates_public_id` (`public_id`),
  KEY `idx_ai_prompt_templates_intent_created` (`intent_code`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_provider_secrets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `secret_encrypted` text DEFAULT NULL,
  `key_hint` varchar(64) DEFAULT NULL,
  `rotated_at` datetime DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_ai_provider_secrets_provider_id` (`provider_id`),
  KEY `idx_ai_provider_secrets_public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `provider_code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `base_url` text DEFAULT NULL,
  `api_path` varchar(255) DEFAULT NULL,
  `default_model` varchar(190) DEFAULT NULL,
  `timeout_ms` int(11) DEFAULT NULL,
  `max_tokens` int(11) DEFAULT NULL,
  `temperature` varchar(16) DEFAULT NULL,
  `extra_headers` text DEFAULT NULL,
  `provider_payload` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `is_default` int(11) DEFAULT 0,
  `created_by_user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `uq_ai_providers_provider_code_active` (`provider_code`,`is_active`),
  KEY `idx_ai_providers_default_active` (`is_default`,`is_active`),
  KEY `idx_ai_providers_public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `intent_code` varchar(128) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `suggestion_json` text DEFAULT NULL,
  `status` varchar(32) DEFAULT 'draft',
  `created_by_user_id` int(11) DEFAULT NULL,
  `confirmed_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `input_hash` varchar(64) DEFAULT NULL,
  `cache_key` varchar(64) DEFAULT NULL,
  `dependency_fingerprint` varchar(64) DEFAULT NULL,
  `cache_status` varchar(32) DEFAULT NULL,
  `stale_reason` varchar(64) DEFAULT NULL,
  `date_bucket` varchar(32) DEFAULT NULL,
  `provider_public_id` varchar(64) DEFAULT NULL,
  `provider_code` varchar(64) DEFAULT NULL,
  `model` varchar(190) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `request_id` varchar(64) DEFAULT NULL,
  `invalidated_at` datetime DEFAULT NULL,
  `result_meta_json` text DEFAULT NULL,
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
CREATE TABLE `ai_usage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_public_id` varchar(64) DEFAULT NULL,
  `action_type` varchar(128) DEFAULT NULL,
  `intent_code` varchar(128) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `error_code` varchar(64) DEFAULT NULL,
  `request_tokens` int(11) DEFAULT NULL,
  `response_tokens` int(11) DEFAULT NULL,
  `total_tokens` int(11) DEFAULT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `is_sensitive_context` int(11) DEFAULT 0,
  `request_meta` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_usage_logs_actor_created` (`user_id`,`created_at`),
  KEY `idx_ai_usage_logs_action_created` (`action_type`,`created_at`),
  KEY `idx_ai_usage_logs_public_id` (`public_id`),
  KEY `idx_ai_usage_logs_intent_created` (`intent_code`,`created_at`),
  KEY `idx_ai_usage_logs_status_created` (`status`,`created_at`),
  KEY `idx_ai_usage_logs_actor_created_v2` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=324 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `key_hash` varchar(255) DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `requester_user_id` int(11) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `reviewer_user_id` int(11) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `actor_public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `action` varchar(64) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_public_id`),
  KEY `idx_audit_logs_created` (`created_at`),
  KEY `idx_audit_logs_actor_created` (`actor_public_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1754 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `trigger_code` varchar(64) DEFAULT NULL,
  `action_code` varchar(64) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `is_enabled` int(11) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_automation_rules_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `automation_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `rule_id` int(11) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `business_calendars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_message_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `message_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `action` varchar(32) DEFAULT NULL,
  `before_text` longtext DEFAULT NULL,
  `after_text` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `reply_to_message_id` int(11) DEFAULT NULL,
  `message_type` varchar(32) NOT NULL DEFAULT 'text',
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'member',
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `muted_until` datetime DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_participant` (`chat_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=67850 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_read_markers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_message_id` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_read` (`chat_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'direct',
  `project_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archived_by_user_id` int(11) DEFAULT NULL,
  `archived_participant_ids` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `checklist_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `is_done` int(11) DEFAULT 0,
  `sort_order` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `client_type` varchar(32) DEFAULT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `person_last_name` varchar(120) DEFAULT NULL,
  `person_first_name` varchar(120) DEFAULT NULL,
  `person_middle_name` varchar(120) DEFAULT NULL,
  `person_birth_date` date DEFAULT NULL,
  `tax_inn` varchar(12) DEFAULT NULL,
  `tax_kpp` varchar(9) DEFAULT NULL,
  `tax_ogrn` varchar(13) DEFAULT NULL,
  `tax_ogrnip` varchar(15) DEFAULT NULL,
  `bank_account` varchar(34) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_bik` varchar(9) DEFAULT NULL,
  `bank_corr_account` varchar(34) DEFAULT NULL,
  `website` varchar(2048) DEFAULT NULL,
  `messenger` varchar(190) DEFAULT NULL,
  `address_legal` text DEFAULT NULL,
  `address_postal` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `extra_attributes` text DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `status` varchar(64) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_clients_created_by` (`created_by_user_id`),
  KEY `idx_clients_type` (`client_type`),
  KEY `idx_clients_tax_inn` (`tax_inn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comment_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_comment_drafts_user_task` (`user_id`,`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `author_user_id` int(11) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `visibility` varchar(32) DEFAULT 'internal',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_comments_task` (`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_companies_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `counterparty_id` int(11) DEFAULT NULL,
  `role` varchar(64) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_contacts_created_by` (`created_by_user_id`),
  KEY `idx_contacts_counterparty` (`counterparty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `counterparties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `counterparty_type` varchar(32) NOT NULL DEFAULT 'organization',
  `status` varchar(64) NOT NULL DEFAULT 'active',
  `legal_name` varchar(255) DEFAULT NULL,
  `person_last_name` varchar(120) DEFAULT NULL,
  `person_first_name` varchar(120) DEFAULT NULL,
  `person_middle_name` varchar(120) DEFAULT NULL,
  `person_birth_date` date DEFAULT NULL,
  `tax_inn` varchar(12) DEFAULT NULL,
  `tax_kpp` varchar(9) DEFAULT NULL,
  `tax_ogrn` varchar(13) DEFAULT NULL,
  `tax_ogrnip` varchar(15) DEFAULT NULL,
  `bank_account` varchar(34) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_bik` varchar(9) DEFAULT NULL,
  `bank_corr_account` varchar(34) DEFAULT NULL,
  `website` varchar(2048) DEFAULT NULL,
  `messenger` varchar(190) DEFAULT NULL,
  `address_legal` text DEFAULT NULL,
  `address_postal` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `extra_attributes` text DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_counterparties_type` (`counterparty_type`),
  KEY `idx_counterparties_status` (`status`),
  KEY `idx_counterparties_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_wip_counts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `current_count` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crm_wip_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `max_tasks` int(11) NOT NULL DEFAULT 5,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_field_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `field_id` int(11) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `scope` varchar(64) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `options` text DEFAULT NULL,
  `is_required` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cycle_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `cycle_id` bigint(20) unsigned NOT NULL,
  `snapshot_date` date NOT NULL,
  `total_tasks` int(11) NOT NULL DEFAULT 0,
  `completed_tasks` int(11) NOT NULL DEFAULT 0,
  `open_tasks` int(11) NOT NULL DEFAULT 0,
  `overdue_tasks` int(11) NOT NULL DEFAULT 0,
  `unassigned_tasks` int(11) NOT NULL DEFAULT 0,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cycle_snapshots_public_id` (`public_id`),
  UNIQUE KEY `uq_cycle_snapshots_cycle_date` (`cycle_id`,`snapshot_date`),
  KEY `idx_cycle_snapshots_cycle_created` (`cycle_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cycle_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `cycle_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `active_key` varchar(191) DEFAULT NULL,
  `added_by_user_id` bigint(20) unsigned NOT NULL,
  `added_at` datetime NOT NULL,
  `removed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cycle_tasks_public_id` (`public_id`),
  UNIQUE KEY `uq_cycle_tasks_active_key` (`active_key`),
  KEY `idx_cycle_tasks_cycle_active` (`cycle_id`,`deleted_at`),
  KEY `idx_cycle_tasks_task_active` (`task_id`,`deleted_at`),
  KEY `idx_cycle_tasks_added_by` (`added_by_user_id`,`added_at`),
  KEY `idx_cycle_tasks_removed_at` (`removed_at`),
  KEY `idx_cycle_tasks_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `manager_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entity_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `tag_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entity_tags_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estimate_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `estimate_set_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `code` varchar(64) NOT NULL,
  `numeric_value` decimal(12,2) DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `active_key` varchar(191) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
CREATE TABLE `estimate_sets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `scope_type` varchar(32) NOT NULL DEFAULT 'project',
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(64) NOT NULL,
  `estimate_type` varchar(64) NOT NULL DEFAULT 'custom',
  `unit_label` varchar(32) DEFAULT NULL,
  `currency_code` varchar(8) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `active_key` varchar(191) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
CREATE TABLE `export_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `result` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_export_jobs_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_export_jobs_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feature_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `code` varchar(128) DEFAULT NULL,
  `is_enabled` int(11) DEFAULT 1,
  `payload` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `uploader_user_id` int(11) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `storage_path` text DEFAULT NULL,
  `mime_type` varchar(128) DEFAULT NULL,
  `size_bytes` bigint(20) DEFAULT NULL,
  `is_deleted` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_files_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `calendar_id` int(11) DEFAULT NULL,
  `holiday_date` date DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_ai_iterations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `iteration` int(11) NOT NULL DEFAULT 1,
  `type` varchar(32) NOT NULL DEFAULT 'analyze',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `analysis_type` varchar(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_snapshot_json`)),
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_json`)),
  `input_hash` varchar(64) DEFAULT NULL,
  `prompt_version` varchar(32) DEFAULT NULL,
  `schema_version` varchar(32) DEFAULT NULL,
  `result_text` text DEFAULT NULL,
  `confidence` varchar(32) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_analysis_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `step_key` varchar(64) NOT NULL,
  `step_order` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext DEFAULT NULL,
  `result_json` longtext DEFAULT NULL,
  `result_text` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `selected_option_key` text DEFAULT NULL,
  `selected_option_label` text DEFAULT NULL,
  `selected_options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selected_options_json`)),
  `is_custom` tinyint(4) NOT NULL DEFAULT 0,
  `is_unknown` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_final_recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `status` varchar(30) DEFAULT NULL,
  `status_label` varchar(200) DEFAULT NULL,
  `recommendation_score` decimal(5,2) DEFAULT NULL,
  `ai_recommendation_score` decimal(5,2) DEFAULT NULL,
  `calculated_recommendation_score` decimal(5,2) DEFAULT NULL,
  `potential_score` decimal(5,2) DEFAULT NULL,
  `feasibility_score` decimal(5,2) DEFAULT NULL,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `data_completeness_score` decimal(5,2) DEFAULT NULL,
  `plan_quality_score` decimal(5,2) DEFAULT NULL,
  `blocker_score` decimal(5,2) DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `recommendation_json` mediumtext DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_implementation_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `plan_json` mediumtext DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `planning_horizon` varchar(50) DEFAULT NULL,
  `plan_type` varchar(20) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_pitfalls_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `overall_hidden_complexity` varchar(20) DEFAULT NULL,
  `overall_summary` text DEFAULT NULL,
  `pitfalls_json` mediumtext DEFAULT NULL,
  `data_confidence` decimal(3,2) DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_potential_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `potential_json` mediumtext DEFAULT NULL,
  `potential_score` decimal(5,2) DEFAULT NULL,
  `potential_level` varchar(20) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `calculation_type` varchar(50) DEFAULT NULL,
  `verdict` text DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_question_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `cycle_number` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext DEFAULT NULL,
  `ai_response_json` longtext DEFAULT NULL,
  `summary_for_user` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL DEFAULT 1,
  `question_text` text NOT NULL,
  `reason` text DEFAULT NULL,
  `question_type` varchar(32) NOT NULL DEFAULT 'single_choice',
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `allow_custom` tinyint(4) NOT NULL DEFAULT 1,
  `allow_unknown` tinyint(4) NOT NULL DEFAULT 1,
  `required` tinyint(4) NOT NULL DEFAULT 1,
  `dimension` text DEFAULT NULL,
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
CREATE TABLE `idea_refined_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `profile_json` mediumtext DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `idea_type` varchar(50) DEFAULT NULL,
  `specificity_level` varchar(20) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `next_action` varchar(50) DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_risk_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `risk_report_json` mediumtext DEFAULT NULL,
  `overall_risk_score` decimal(5,2) DEFAULT NULL,
  `overall_risk_level` varchar(20) DEFAULT NULL,
  `critical_risks_count` int(11) DEFAULT 0,
  `high_risks_count` int(11) DEFAULT 0,
  `medium_risks_count` int(11) DEFAULT 0,
  `low_risks_count` int(11) DEFAULT 0,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_suggested_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `tasks_json` mediumtext DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_task_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `crm_task_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `stage` varchar(64) DEFAULT NULL,
  `priority` varchar(32) DEFAULT 'normal',
  `acceptance_criteria_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`acceptance_criteria_json`)),
  `dependencies_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dependencies_json`)),
  `estimated_duration` varchar(128) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_selected` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_understanding_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `profile_json` mediumtext DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `idea_type` varchar(50) DEFAULT NULL,
  `specificity_level` varchar(20) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `next_action` varchar(50) DEFAULT NULL,
  `ai_request_json` mediumtext DEFAULT NULL,
  `ai_response_json` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idea_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idea_vote` (`idea_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `goal` text DEFAULT NULL,
  `author_user_id` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new',
  `category` varchar(64) DEFAULT NULL,
  `region` varchar(190) DEFAULT NULL,
  `visibility` varchar(16) NOT NULL DEFAULT 'public',
  `target_date` date DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `domain` varchar(128) DEFAULT NULL,
  `maturity` varchar(64) DEFAULT NULL,
  `known_facts_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`known_facts_json`)),
  `unknowns_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unknowns_json`)),
  `assumptions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assumptions_json`)),
  `coverage_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`coverage_json`)),
  `vote_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  `ai_analysis` text DEFAULT NULL,
  `ai_analysis_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prompt_version` text DEFAULT NULL,
  `schema_version` text DEFAULT NULL,
  `source_context_json` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idempotency_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `key_hash` varchar(255) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `response_payload` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `impersonation_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `admin_user_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `result` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_import_jobs_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_import_jobs_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `install_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `installed_at` datetime DEFAULT NULL,
  `version` varchar(20) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intake_item_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `intake_item_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `field_name` varchar(128) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_item_activities_public_id` (`public_id`),
  KEY `idx_intake_item_activities_item_created` (`intake_item_id`,`created_at`),
  KEY `idx_intake_item_activities_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_intake_item_activities_type_created` (`event_type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intake_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `priority_code` varchar(64) DEFAULT NULL,
  `source_type` varchar(64) NOT NULL DEFAULT 'manual',
  `source_ref` varchar(255) DEFAULT NULL,
  `source_email` varchar(255) DEFAULT NULL,
  `external_source` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `extra_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_json`)),
  `due_at` datetime DEFAULT NULL,
  `snoozed_until` datetime DEFAULT NULL,
  `assignee_user_id` bigint(20) unsigned DEFAULT NULL,
  `creator_user_id` bigint(20) unsigned NOT NULL,
  `accepted_task_id` bigint(20) unsigned DEFAULT NULL,
  `duplicate_intake_item_id` bigint(20) unsigned DEFAULT NULL,
  `duplicate_task_id` bigint(20) unsigned DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `resolved_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `invited_by_user_id` int(11) DEFAULT NULL,
  `token_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `page_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_comments_page` (`page_id`,`created_at`),
  KEY `idx_knowledge_comments_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `page_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content_html` text DEFAULT NULL,
  `content_text` text DEFAULT NULL,
  `content_json` text DEFAULT NULL,
  `base_row_version` int(11) DEFAULT 1,
  `autosaved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_knowledge_drafts_page_user` (`page_id`,`user_id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_entity_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `page_id` int(11) NOT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `relation_type` varchar(64) DEFAULT 'related',
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_links_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_page_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `subject_type` varchar(32) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `access_level` varchar(32) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_page_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `page_id` bigint(20) unsigned NOT NULL,
  `page_public_id` varchar(64) NOT NULL,
  `version_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `content_text` longtext DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `visibility` varchar(32) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags_json`)),
  `links_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`links_json`)),
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `change_type` varchar(64) NOT NULL DEFAULT 'update',
  `change_note` varchar(1000) DEFAULT NULL,
  `restored_from_version_number` int(11) DEFAULT NULL,
  `restored_from_version_public_id` varchar(64) DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_actor_type` varchar(32) NOT NULL DEFAULT 'user',
  `created_by_display_name` varchar(255) DEFAULT NULL,
  `request_id` varchar(128) DEFAULT NULL,
  `source_type` varchar(64) DEFAULT NULL,
  `source_ref` varchar(255) DEFAULT NULL,
  `content_hash` char(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_knowledge_page_versions_public_id` (`public_id`),
  UNIQUE KEY `uq_knowledge_page_versions_page_number` (`page_id`,`version_number`),
  KEY `idx_knowledge_page_versions_page_created` (`page_id`,`created_at`),
  KEY `idx_knowledge_page_versions_page_public_created` (`page_public_id`,`created_at`),
  KEY `idx_knowledge_page_versions_created_by` (`created_by_user_id`,`created_at`),
  KEY `idx_knowledge_page_versions_change_type` (`change_type`,`created_at`),
  KEY `idx_knowledge_page_versions_hash` (`content_hash`),
  KEY `idx_knowledge_page_versions_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_page_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `source` varchar(32) DEFAULT 'direct',
  `viewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_knowledge_views_page` (`page_id`,`viewed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `space_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(190) DEFAULT NULL,
  `page_type` varchar(64) DEFAULT 'article',
  `status` varchar(32) DEFAULT 'draft',
  `content_html` text DEFAULT NULL,
  `content_text` text DEFAULT NULL,
  `content_json` text DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `last_editor_user_id` int(11) DEFAULT NULL,
  `published_by_user_id` int(11) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `review_due_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_status` varchar(32) DEFAULT NULL,
  `reviewer_user_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `path` varchar(2048) DEFAULT NULL,
  `depth` int(11) DEFAULT 0,
  `children_count` int(11) DEFAULT 0,
  `comments_count` int(11) DEFAULT 0,
  `attachments_count` int(11) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `row_version` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `lock_reason` varchar(1000) DEFAULT NULL,
  `last_version_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_pages_space_parent_sort` (`space_id`,`parent_id`,`sort_order`),
  KEY `idx_knowledge_pages_space_status_updated` (`space_id`,`status`,`updated_at`),
  KEY `idx_knowledge_pages_parent` (`parent_id`),
  KEY `idx_knowledge_pages_owner` (`owner_user_id`),
  KEY `idx_knowledge_pages_review_due` (`review_due_at`),
  KEY `idx_knowledge_pages_type` (`page_type`),
  FULLTEXT KEY `ft_knowledge_pages_title_text` (`title`,`content_text`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_search_index` (
  `page_id` int(11) NOT NULL,
  `space_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content_text` text DEFAULT NULL,
  `tags_text` text DEFAULT NULL,
  `entity_text` text DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `page_type` varchar(64) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`page_id`),
  KEY `idx_knowledge_search_space_status_updated` (`space_id`,`status`,`updated_at`),
  KEY `idx_knowledge_search_page_type` (`page_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_search_queries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `results_count` int(11) DEFAULT 0,
  `clicked_page_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_space_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `subject_type` varchar(32) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `access_level` varchar(32) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_spaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(160) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `visibility` varchar(32) DEFAULT 'public',
  `default_access_level` varchar(32) DEFAULT 'view',
  `tree_version` int(11) DEFAULT 1,
  `content_version` int(11) DEFAULT 1,
  `permissions_version` int(11) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `is_system` int(11) DEFAULT 0,
  `is_archived` int(11) DEFAULT 0,
  `row_version` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_knowledge_spaces_archived_sort` (`is_archived`,`sort_order`),
  KEY `idx_knowledge_spaces_owner` (`owner_user_id`),
  KEY `idx_knowledge_spaces_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `knowledge_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `page_type` varchar(64) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `content_html` text DEFAULT NULL,
  `content_json` text DEFAULT NULL,
  `is_system` int(11) DEFAULT 0,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_knowledge_templates_type_active` (`page_type`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mentions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `mentioned_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `migration_key` varchar(191) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_key` (`migration_key`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_milestones_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `event_type` varchar(190) NOT NULL,
  `event_name` varchar(190) NOT NULL,
  `details` varchar(190) DEFAULT NULL,
  `ip_address` varchar(190) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_audit_module` (`module_name`),
  KEY `idx_module_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_deprecations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `message` varchar(190) NOT NULL,
  `since_version` varchar(190) DEFAULT NULL,
  `replacement` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_deprecations_module` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `context` varchar(190) NOT NULL,
  `error_code` varchar(190) DEFAULT NULL,
  `error_message` varchar(190) NOT NULL,
  `stack_trace` varchar(190) DEFAULT NULL,
  `request_id` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_errors_module` (`module_name`),
  KEY `idx_module_errors_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `job_name` varchar(190) NOT NULL,
  `payload` varchar(190) NOT NULL,
  `status` varchar(190) NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `delay_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_module_jobs_status` (`status`,`created_at`),
  KEY `idx_module_jobs_module` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `migration_name` varchar(190) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `batch` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_module_migrations_unique` (`module_name`,`migration_name`),
  KEY `idx_module_migrations_module` (`module_name`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_registry` (
  `module_name` varchar(190) NOT NULL,
  `vendor` varchar(190) NOT NULL,
  `version` varchar(190) NOT NULL,
  `is_active` int(11) NOT NULL DEFAULT 0,
  `installed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `activated_at` datetime DEFAULT NULL,
  `config` text NOT NULL,
  PRIMARY KEY (`module_name`),
  UNIQUE KEY `idx_module_registry_name` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_scheduled_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `task_name` varchar(190) NOT NULL,
  `description` varchar(190) DEFAULT NULL,
  `schedule` varchar(190) NOT NULL,
  `handler_class` varchar(190) NOT NULL,
  `handler_method` varchar(190) NOT NULL,
  `enabled` int(11) NOT NULL DEFAULT 1,
  `timeout` int(11) NOT NULL DEFAULT 300,
  `overlap_allowed` int(11) NOT NULL DEFAULT 0,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime NOT NULL,
  `last_status` varchar(190) DEFAULT NULL,
  `last_error` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scheduled_tasks_next` (`next_run_at`,`enabled`),
  KEY `idx_scheduled_tasks_module` (`module_name`)
) ENGINE=InnoDB AUTO_INCREMENT=198565 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_task_executions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `task_name` varchar(190) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `status` varchar(190) NOT NULL,
  `output` varchar(190) DEFAULT NULL,
  `error_message` varchar(190) DEFAULT NULL,
  `error_trace` varchar(190) DEFAULT NULL,
  `memory_peak_mb` int(11) DEFAULT NULL,
  `pid` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task_executions_module` (`module_name`,`task_name`,`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=64401 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) NOT NULL,
  `event_name` varchar(190) NOT NULL,
  `url` varchar(190) NOT NULL,
  `secret` varchar(190) DEFAULT NULL,
  `is_active` int(11) NOT NULL DEFAULT 1,
  `headers` varchar(190) DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 3,
  `timeout` int(11) NOT NULL DEFAULT 30,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_webhooks_module` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_push_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `notification_public_id` varchar(64) DEFAULT NULL,
  `payload_json` text NOT NULL,
  `status` varchar(32) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `endpoint` text DEFAULT NULL,
  `p256dh` varchar(1024) DEFAULT NULL,
  `auth` varchar(1024) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_label` varchar(255) DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `last_error` text DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_notif_push_subscriptions_user_active` (`user_id`,`is_active`,`updated_at`),
  KEY `idx_notif_push_subscriptions_endpoint` (`endpoint`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `action_code` varchar(64) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_public_id` varchar(64) DEFAULT NULL,
  `actor_name` varchar(255) DEFAULT NULL,
  `link` varchar(1024) DEFAULT NULL,
  `payload_json` text DEFAULT NULL,
  `is_read` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  KEY `idx_notifications_user_unread_created` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_user_category_unread` (`user_id`,`category`,`is_read`),
  KEY `idx_notifications_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=556 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization_memberships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_code` varchar(32) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `token_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `code` varchar(128) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `priorities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `weight` int(11) DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_module_links` (
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
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_module_links_public_id` (`public_id`),
  KEY `idx_project_module_links_module_active` (`module_id`,`deleted_at`),
  KEY `idx_project_module_links_type` (`link_type`),
  KEY `idx_project_module_links_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_module_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_code` varchar(64) NOT NULL DEFAULT 'member',
  `added_by_user_id` bigint(20) unsigned NOT NULL,
  `added_at` datetime NOT NULL,
  `removed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `active_key` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
CREATE TABLE `project_module_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `module_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `added_by_user_id` bigint(20) unsigned NOT NULL,
  `added_at` datetime NOT NULL,
  `removed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `active_key` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
CREATE TABLE `project_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'planned',
  `lead_user_id` bigint(20) unsigned DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `target_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `progress_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`progress_snapshot_json`)),
  `row_version` int(11) NOT NULL DEFAULT 1,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_project_templates_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status_code` varchar(64) DEFAULT NULL,
  `priority_code` varchar(64) DEFAULT NULL,
  `client_public_id` varchar(64) DEFAULT NULL,
  `manager_user_id` int(11) DEFAULT NULL,
  `team_public_id` varchar(64) DEFAULT NULL,
  `task_key_prefix` varchar(10) DEFAULT NULL,
  `task_key_prefix_locked` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `row_version` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_projects_task_key_prefix` (`task_key_prefix`),
  KEY `idx_projects_status` (`status_code`),
  KEY `idx_projects_updated_public` (`updated_at`,`public_id`),
  KEY `idx_projects_archived_updated` (`archived_at`,`updated_at`,`public_id`),
  KEY `idx_projects_creator_archived_updated` (`created_by_user_id`,`archived_at`,`updated_at`),
  KEY `idx_projects_manager_archived_updated` (`manager_user_id`,`archived_at`,`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reaction` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `rule_id` int(11) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `next_occurrence` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `rrule` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_processed_at` datetime DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recycle_bin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `deleted_by_user_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `restored_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `remind_at` datetime DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `request_id` varchar(64) DEFAULT NULL,
  `correlation_id` varchar(64) DEFAULT NULL,
  `user_public_id` varchar(64) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `method` varchar(16) DEFAULT NULL,
  `status_code` int(11) DEFAULT NULL,
  `result_code` varchar(64) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_request_logs_request` (`request_id`),
  KEY `idx_request_logs_created` (`created_at`),
  KEY `idx_request_logs_user_created` (`user_public_id`,`created_at`),
  KEY `idx_request_logs_method_created` (`method`,`created_at`),
  KEY `idx_request_logs_result_created` (`result_code`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=102970 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `key` varchar(64) NOT NULL,
  `attempts` text NOT NULL,
  `blocked_until` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `is_system` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_view_user_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `saved_view_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_view_user_preferences_public_id` (`public_id`),
  UNIQUE KEY `uq_saved_view_user_preferences_view_user` (`saved_view_id`,`user_id`),
  KEY `idx_saved_view_user_preferences_user_pinned` (`user_id`,`is_pinned`,`sort_order`),
  KEY `idx_saved_view_user_preferences_last_used` (`user_id`,`last_used_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `filters` text DEFAULT NULL,
  `access_level` varchar(32) NOT NULL DEFAULT 'private',
  `display_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`display_filters`)),
  `display_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`display_properties`)),
  `rich_filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rich_filters`)),
  `layout` varchar(32) NOT NULL DEFAULT 'list',
  `group_by` varchar(64) DEFAULT NULL,
  `order_by` varchar(64) DEFAULT NULL,
  `order_dir` varchar(8) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_saved_views_entity_access` (`entity_type`,`access_level`),
  KEY `idx_saved_views_user_entity` (`user_id`,`entity_type`),
  KEY `idx_saved_views_archived` (`archived_at`),
  KEY `idx_saved_views_sort_order` (`sort_order`),
  KEY `idx_saved_views_system_locked` (`is_system`,`is_locked`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `actor_public_id` varchar(64) DEFAULT NULL,
  `event_type` varchar(64) DEFAULT NULL,
  `ip` varchar(128) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_security_logs_created` (`created_at`),
  KEY `idx_security_logs_actor_created` (`actor_public_id`,`created_at`),
  KEY `idx_security_logs_event_created` (`event_type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=887 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `scope` varchar(64) DEFAULT NULL,
  `name` varchar(190) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sla_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `response_minutes` int(11) DEFAULT NULL,
  `resolve_minutes` int(11) DEFAULT NULL,
  `escalation_payload` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `scope` varchar(64) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `wip_limit` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sticky_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `owner_user_id` bigint(20) unsigned NOT NULL,
  `context_type` varchar(64) NOT NULL DEFAULT 'personal',
  `context_public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `color` varchar(32) NOT NULL DEFAULT 'yellow',
  `background_color` varchar(32) DEFAULT NULL,
  `visibility` varchar(32) NOT NULL DEFAULT 'private',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `converted_to_entity_type` varchar(64) DEFAULT NULL,
  `converted_to_entity_public_id` varchar(64) DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `converted_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `row_version` int(11) NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subtasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `status_code` varchar(64) DEFAULT NULL,
  `assignee_user_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sync_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `scope` varchar(64) DEFAULT NULL,
  `cursor_value` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_activity_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `task_public_id` varchar(64) NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(32) NOT NULL DEFAULT 'user',
  `actor_public_id` varchar(64) DEFAULT NULL,
  `actor_display_name` varchar(255) DEFAULT NULL,
  `event_type` varchar(96) NOT NULL,
  `field_name` varchar(128) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `old_label` varchar(255) DEFAULT NULL,
  `new_label` varchar(255) DEFAULT NULL,
  `related_entity_type` varchar(64) DEFAULT NULL,
  `related_entity_id` bigint(20) unsigned DEFAULT NULL,
  `related_entity_public_id` varchar(64) DEFAULT NULL,
  `related_entity_label` varchar(255) DEFAULT NULL,
  `message_key` varchar(128) DEFAULT NULL,
  `message_text` varchar(1000) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `visibility` varchar(32) NOT NULL DEFAULT 'default',
  `request_id` varchar(128) DEFAULT NULL,
  `source_type` varchar(64) DEFAULT NULL,
  `source_ref` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_activity_events_public_id` (`public_id`),
  KEY `idx_task_activity_events_task_created` (`task_id`,`created_at`),
  KEY `idx_task_activity_events_task_public_created` (`task_public_id`,`created_at`),
  KEY `idx_task_activity_events_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_task_activity_events_event_type` (`event_type`,`created_at`),
  KEY `idx_task_activity_events_related` (`related_entity_type`,`related_entity_public_id`),
  KEY `idx_task_activity_events_request_id` (`request_id`),
  KEY `idx_task_activity_events_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assignees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_dependencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `depends_on_task_id` int(11) DEFAULT NULL,
  `dependency_type` varchar(32) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_dep_task` (`task_id`),
  KEY `idx_dep_depends_on_task` (`depends_on_task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_estimates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `task_public_id` varchar(64) NOT NULL,
  `estimate_set_id` bigint(20) unsigned NOT NULL,
  `estimate_option_id` bigint(20) unsigned DEFAULT NULL,
  `numeric_value` decimal(12,2) DEFAULT NULL,
  `text_value` varchar(255) DEFAULT NULL,
  `currency_code` varchar(8) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `assigned_by_user_id` bigint(20) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL,
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `active_key` varchar(191) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_key_counters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope_key` varchar(64) NOT NULL,
  `scope_type` varchar(32) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `prefix` varchar(10) NOT NULL,
  `current_value` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_key_counters_scope_key` (`scope_key`),
  KEY `idx_task_key_counters_project_id` (`project_id`),
  KEY `idx_task_key_counters_prefix` (`prefix`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `parent_task_id` int(11) DEFAULT NULL,
  `child_task_id` int(11) DEFAULT NULL,
  `relation_type` varchar(32) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `legacy_subtask_public_id` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `idx_task_rel_child_type` (`child_task_id`,`relation_type`),
  UNIQUE KEY `idx_task_rel_legacy` (`legacy_subtask_public_id`),
  KEY `idx_task_rel_parent_type_sort` (`parent_task_id`,`relation_type`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_relations_v2` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `source_task_id` bigint(20) unsigned NOT NULL,
  `target_task_id` bigint(20) unsigned NOT NULL,
  `relation_type` varchar(32) NOT NULL,
  `active_key` varchar(191) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `row_version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_relations_v2_public_id` (`public_id`),
  UNIQUE KEY `uq_task_relations_v2_active_key` (`active_key`),
  KEY `idx_task_relations_v2_source` (`source_task_id`,`deleted_at`),
  KEY `idx_task_relations_v2_target` (`target_task_id`,`deleted_at`),
  KEY `idx_task_relations_v2_type` (`relation_type`,`deleted_at`),
  KEY `idx_task_relations_v2_created_by` (`created_by_user_id`,`created_at`),
  KEY `idx_task_relations_v2_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `old_status` varchar(64) DEFAULT NULL,
  `new_status` varchar(64) DEFAULT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_task_templates_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_watchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `parent_task_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status_code` varchar(64) DEFAULT NULL,
  `sla_policy_id` int(11) DEFAULT NULL,
  `sla_response_deadline` datetime DEFAULT NULL,
  `sla_resolve_deadline` datetime DEFAULT NULL,
  `sla_breached` tinyint(4) NOT NULL DEFAULT 0,
  `priority_code` varchar(64) DEFAULT NULL,
  `task_key` varchar(32) DEFAULT NULL,
  `task_key_prefix` varchar(10) DEFAULT NULL,
  `task_sequence_number` bigint(20) unsigned DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `assignee_user_id` int(11) DEFAULT NULL,
  `creator_user_id` int(11) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `row_version` int(11) DEFAULT 1,
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
  KEY `idx_tasks_task_sequence_number` (`task_sequence_number`)
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `team_type` varchar(32) NOT NULL DEFAULT 'team',
  `parent_id` int(11) DEFAULT NULL,
  `code` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `manager_user_id` int(11) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `member_user_ids` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_teams_created_by` (`created_by_user_id`),
  KEY `idx_teams_type` (`team_type`),
  KEY `idx_teams_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `two_factor_secrets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `secret_hash` varchar(255) DEFAULT NULL,
  `backup_codes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `token_hash` varchar(255) DEFAULT NULL,
  `ip` varchar(128) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_fingerprint` varchar(64) DEFAULT NULL,
  `device_name` varchar(190) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_sessions_token` (`token_hash`),
  KEY `idx_sessions_user_device` (`user_id`,`device_fingerprint`)
) ENGINE=InnoDB AUTO_INCREMENT=422 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `login` varchar(120) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `auth_token_hash` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `locale` varchar(16) DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `is_root` int(11) DEFAULT 0,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `cost_rate` decimal(12,2) DEFAULT NULL,
  `bill_rate` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `login` (`login`),
  KEY `idx_users_login` (`login`),
  KEY `idx_users_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `webhook_id` int(11) DEFAULT NULL,
  `event_code` varchar(64) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `payload_json` text DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `dead_letter` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_webhook_deliveries_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_webhook_deliveries_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `endpoint` text DEFAULT NULL,
  `secret_hash` varchar(255) DEFAULT NULL,
  `events` text DEFAULT NULL,
  `is_active` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_cycles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `goal` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'planned',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `completed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `progress_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`progress_snapshot_json`)),
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `sort_order` int(11) NOT NULL DEFAULT 65535,
  `row_version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_work_cycles_public_id` (`public_id`),
  KEY `idx_work_cycles_project_status` (`project_id`,`status`),
  KEY `idx_work_cycles_project_dates` (`project_id`,`start_at`,`end_at`),
  KEY `idx_work_cycles_owner_status` (`owner_user_id`,`status`),
  KEY `idx_work_cycles_created_by` (`created_by_user_id`,`created_at`),
  KEY `idx_work_cycles_completed_at` (`completed_at`),
  KEY `idx_work_cycles_archived_at` (`archived_at`),
  KEY `idx_work_cycles_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `minutes_spent` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `logged_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_work_logs_interval` (`user_id`,`started_at`,`ended_at`)
) ENGINE=InnoDB AUTO_INCREMENT=360 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `working_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `calendar_id` int(11) DEFAULT NULL,
  `weekday` int(11) DEFAULT NULL,
  `start_time` varchar(8) DEFAULT NULL,
  `end_time` varchar(8) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
