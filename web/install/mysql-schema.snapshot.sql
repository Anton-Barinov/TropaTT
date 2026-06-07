-- CRM MySQL schema snapshot generated from crm.ru. Schema only, no user data.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_feed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_feed` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_intent_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_intent_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` int DEFAULT NULL,
  `model` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `feature_flag` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required_permission` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_sensitive_context` int DEFAULT '0',
  `max_tokens` int DEFAULT NULL,
  `temperature` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` int DEFAULT '1',
  `intent_payload` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `intent_code` (`intent_code`),
  KEY `idx_ai_intent_settings_provider` (`provider_id`),
  KEY `idx_ai_intent_settings_intent_created` (`intent_code`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by_user_id` int DEFAULT NULL,
  `scope_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` text COLLATE utf8mb4_unicode_ci,
  `result_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `error_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_json_schemas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_json_schemas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_json` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_json_schemas_intent` (`intent_code`),
  KEY `idx_ai_json_schemas_public_id` (`public_id`),
  KEY `idx_ai_json_schemas_intent_created` (`intent_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_prompt_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_prompt_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int DEFAULT '1',
  `template_text` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_ai_prompt_templates_intent_locale` (`intent_code`,`locale`),
  KEY `idx_ai_prompt_templates_public_id` (`public_id`),
  KEY `idx_ai_prompt_templates_intent_created` (`intent_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_provider_secrets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_provider_secrets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_id` int DEFAULT NULL,
  `secret_encrypted` text COLLATE utf8mb4_unicode_ci,
  `key_hint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rotated_at` datetime DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_ai_provider_secrets_provider_id` (`provider_id`),
  KEY `idx_ai_provider_secrets_public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_providers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_url` text COLLATE utf8mb4_unicode_ci,
  `api_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_model` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timeout_ms` int DEFAULT NULL,
  `max_tokens` int DEFAULT NULL,
  `temperature` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_headers` text COLLATE utf8mb4_unicode_ci,
  `provider_payload` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `is_default` int DEFAULT '0',
  `created_by_user_id` int DEFAULT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `uq_ai_providers_provider_code_active` (`provider_code`,`is_active`),
  KEY `idx_ai_providers_default_active` (`is_default`,`is_active`),
  KEY `idx_ai_providers_public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_suggestions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `suggestion_json` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_by_user_id` int DEFAULT NULL,
  `confirmed_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `input_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dependency_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stale_reason` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_bucket` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `usage_count` int DEFAULT '0',
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invalidated_at` datetime DEFAULT NULL,
  `result_meta_json` text COLLATE utf8mb4_unicode_ci,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `provider_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_tokens` int DEFAULT NULL,
  `response_tokens` int DEFAULT NULL,
  `total_tokens` int DEFAULT NULL,
  `latency_ms` int DEFAULT NULL,
  `is_sensitive_context` int DEFAULT '0',
  `request_meta` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
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
DROP TABLE IF EXISTS `api_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `key_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requester_user_id` int DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_steps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` int DEFAULT NULL,
  `reviewer_user_id` int DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_public_id`),
  KEY `idx_audit_logs_created` (`created_at`),
  KEY `idx_audit_logs_actor_created` (`actor_public_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `automation_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `is_enabled` int DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_automation_rules_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `automation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule_id` int DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_calendars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `owner_user_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_message_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_message_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) DEFAULT NULL,
  `message_id` int NOT NULL,
  `chat_id` int NOT NULL,
  `actor_user_id` int NOT NULL,
  `action` varchar(32) DEFAULT NULL,
  `before_text` longtext,
  `after_text` longtext,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `reply_to_message_id` int DEFAULT NULL,
  `message_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chat_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `muted_until` datetime DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_participant` (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_read_markers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_read_markers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chat_id` int NOT NULL,
  `user_id` int NOT NULL,
  `last_read_message_id` int NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_read` (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `project_id` int DEFAULT NULL,
  `team_id` int DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_by_user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL,
  `archived_by_user_id` int DEFAULT NULL,
  `archived_participant_ids` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checklist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checklist_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_done` int DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_last_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_first_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_middle_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_birth_date` date DEFAULT NULL,
  `tax_inn` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_kpp` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_ogrn` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_ogrnip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_bik` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_corr_account` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `messenger` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_legal` text COLLATE utf8mb4_unicode_ci,
  `address_postal` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `extra_attributes` text COLLATE utf8mb4_unicode_ci,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_clients_created_by` (`created_by_user_id`),
  KEY `idx_clients_type` (`client_type`),
  KEY `idx_clients_tax_inn` (`tax_inn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comment_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comment_drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `uq_comment_drafts_user_task` (`user_id`,`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_user_id` int DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `visibility` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'internal',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_comments_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_companies_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `counterparty_id` int DEFAULT NULL,
  `role` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_contacts_created_by` (`created_by_user_id`),
  KEY `idx_contacts_counterparty` (`counterparty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `counterparties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `counterparties` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `counterparty_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'organization',
  `status` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_last_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_first_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_middle_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `person_birth_date` date DEFAULT NULL,
  `tax_inn` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_kpp` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_ogrn` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_ogrnip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_bik` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_corr_account` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `messenger` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_legal` text COLLATE utf8mb4_unicode_ci,
  `address_postal` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `extra_attributes` text COLLATE utf8mb4_unicode_ci,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_counterparties_type` (`counterparty_type`),
  KEY `idx_counterparties_status` (`status`),
  KEY `idx_counterparties_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_wip_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_wip_counts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `current_count` int NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_wip_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_wip_limits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `max_tasks` int NOT NULL DEFAULT '5',
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_field_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_id` int DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  `is_required` int DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entity_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entity_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `export_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `export_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `result` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `dead_letter` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_export_jobs_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_export_jobs_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feature_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feature_flags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` int DEFAULT '1',
  `payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploader_user_id` int DEFAULT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_path` text COLLATE utf8mb4_unicode_ci,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint DEFAULT NULL,
  `is_deleted` int DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_files_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calendar_id` int DEFAULT NULL,
  `holiday_date` date DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_ai_iterations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_ai_iterations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idea_id` int NOT NULL,
  `iteration` int NOT NULL DEFAULT '1',
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'analyze',
  `request_payload` json DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_analyses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_analyses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idea_id` int NOT NULL,
  `analysis_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `input_snapshot_json` json DEFAULT NULL,
  `result_json` json DEFAULT NULL,
  `input_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_text` text COLLATE utf8mb4_unicode_ci,
  `confidence` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `attempts` int NOT NULL DEFAULT '1',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_analysis_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_analysis_steps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `step_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_order` int NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext COLLATE utf8mb4_unicode_ci,
  `result_json` longtext COLLATE utf8mb4_unicode_ci,
  `result_text` longtext COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `attempts` int NOT NULL DEFAULT '0',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `question_id` int NOT NULL,
  `answer_text` text COLLATE utf8mb4_unicode_ci,
  `selected_option_key` text COLLATE utf8mb4_unicode_ci,
  `selected_option_label` text COLLATE utf8mb4_unicode_ci,
  `selected_options_json` json DEFAULT NULL,
  `is_custom` tinyint NOT NULL DEFAULT '0',
  `is_unknown` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_final_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_final_recommendations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
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
  `recommendation_json` mediumtext,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_implementation_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_implementation_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `plan_json` mediumtext,
  `summary` text,
  `planning_horizon` varchar(50) DEFAULT NULL,
  `plan_type` varchar(20) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_pitfalls_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_pitfalls_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `overall_hidden_complexity` varchar(20) DEFAULT NULL,
  `overall_summary` text,
  `pitfalls_json` mediumtext,
  `data_confidence` decimal(3,2) DEFAULT NULL,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_potential_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_potential_scores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `potential_json` mediumtext,
  `potential_score` decimal(5,2) DEFAULT NULL,
  `potential_level` varchar(20) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `calculation_type` varchar(50) DEFAULT NULL,
  `verdict` text,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_question_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_question_cycles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `cycle_number` int NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `input_snapshot_json` longtext COLLATE utf8mb4_unicode_ci,
  `ai_response_json` longtext COLLATE utf8mb4_unicode_ci,
  `summary_for_user` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idea_id` int NOT NULL,
  `cycle_id` int NOT NULL DEFAULT '1',
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `question_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single_choice',
  `options_json` json DEFAULT NULL,
  `allow_custom` tinyint NOT NULL DEFAULT '1',
  `allow_unknown` tinyint NOT NULL DEFAULT '1',
  `required` tinyint NOT NULL DEFAULT '1',
  `dimension` text COLLATE utf8mb4_unicode_ci,
  `impact` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_refined_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_refined_cards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `profile_json` mediumtext,
  `summary` text,
  `idea_type` varchar(50) DEFAULT NULL,
  `specificity_level` varchar(20) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `next_action` varchar(50) DEFAULT NULL,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_risk_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_risk_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `risk_report_json` mediumtext,
  `overall_risk_score` decimal(5,2) DEFAULT NULL,
  `overall_risk_level` varchar(20) DEFAULT NULL,
  `critical_risks_count` int DEFAULT '0',
  `high_risks_count` int DEFAULT '0',
  `medium_risks_count` int DEFAULT '0',
  `low_risks_count` int DEFAULT '0',
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_suggested_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_suggested_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `tasks_json` mediumtext,
  `summary` text,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_task_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_task_drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idea_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `crm_task_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stage` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `acceptance_criteria_json` json DEFAULT NULL,
  `dependencies_json` json DEFAULT NULL,
  `estimated_duration` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_selected` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_understanding_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_understanding_cards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `profile_json` mediumtext,
  `summary` text,
  `idea_type` varchar(50) DEFAULT NULL,
  `specificity_level` varchar(20) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `next_action` varchar(50) DEFAULT NULL,
  `ai_request_json` mediumtext,
  `ai_response_json` mediumtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idea_id` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_votes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idea_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idea_vote` (`idea_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ideas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ideas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `goal` text COLLATE utf8mb4_unicode_ci,
  `author_user_id` int NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `category` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visibility` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `target_date` date DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maturity` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `known_facts_json` json DEFAULT NULL,
  `unknowns_json` json DEFAULT NULL,
  `assumptions_json` json DEFAULT NULL,
  `coverage_json` json DEFAULT NULL,
  `vote_count` int NOT NULL DEFAULT '0',
  `comment_count` int NOT NULL DEFAULT '0',
  `ai_analysis` text COLLATE utf8mb4_unicode_ci,
  `ai_analysis_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `prompt_version` text COLLATE utf8mb4_unicode_ci,
  `schema_version` text COLLATE utf8mb4_unicode_ci,
  `source_context_json` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idempotency_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `impersonation_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impersonation_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_user_id` int DEFAULT NULL,
  `target_user_id` int DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `result` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `dead_letter` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_import_jobs_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_import_jobs_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `install_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `install_state` (
  `id` int NOT NULL AUTO_INCREMENT,
  `installed_at` datetime DEFAULT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invitations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invited_by_user_id` int DEFAULT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mentioned_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `migration_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `milestones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_deprecations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_deprecations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `since_version` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_errors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_code` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stack_trace` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` int NOT NULL DEFAULT '0',
  `max_attempts` int NOT NULL DEFAULT '3',
  `delay_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `migration_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `batch` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_registry` (
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` int NOT NULL DEFAULT '0',
  `installed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activated_at` datetime DEFAULT NULL,
  `config` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_scheduled_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_scheduled_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handler_class` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handler_method` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` int NOT NULL DEFAULT '1',
  `timeout` int NOT NULL DEFAULT '300',
  `overlap_allowed` int NOT NULL DEFAULT '0',
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime NOT NULL,
  `last_status` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_task_executions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_task_executions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` int DEFAULT NULL,
  `status` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_trace` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memory_peak_mb` int DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_webhooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` int NOT NULL DEFAULT '1',
  `headers` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_count` int NOT NULL DEFAULT '3',
  `timeout` int NOT NULL DEFAULT '30',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_push_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_push_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `notification_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `dead_letter` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_push_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci,
  `p256dh` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` int DEFAULT '1',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_notif_push_subscriptions_user_active` (`user_id`,`is_active`,`updated_at`),
  KEY `idx_notif_push_subscriptions_endpoint` (`endpoint`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_user_id` int DEFAULT NULL,
  `actor_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` text COLLATE utf8mb4_unicode_ci,
  `is_read` int DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  KEY `idx_notifications_user_unread_created` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_user_category_unread` (`user_id`,`category`,`is_read`),
  KEY `idx_notifications_entity` (`entity_type`,`entity_public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organization_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_memberships` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organization_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `priorities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `priorities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` int DEFAULT NULL,
  `color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_project_templates_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_user_id` int DEFAULT NULL,
  `team_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `row_version` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_projects_status` (`status_code`),
  KEY `idx_projects_updated_public` (`updated_at`,`public_id`),
  KEY `idx_projects_archived_updated` (`archived_at`,`updated_at`,`public_id`),
  KEY `idx_projects_creator_archived_updated` (`created_by_user_id`,`archived_at`,`updated_at`),
  KEY `idx_projects_manager_archived_updated` (`manager_user_id`,`archived_at`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `reaction` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_instances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_instances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule_id` int DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rrule` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recycle_bin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recycle_bin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `deleted_by_user_id` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `restored_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `remind_at` datetime DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `request_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correlation_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_code` int DEFAULT NULL,
  `result_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_ms` int DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_request_logs_request` (`request_id`),
  KEY `idx_request_logs_created` (`created_at`),
  KEY `idx_request_logs_user_created` (`user_public_id`,`created_at`),
  KEY `idx_request_logs_method_created` (`method`,`created_at`),
  KEY `idx_request_logs_result_created` (`result_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int DEFAULT NULL,
  `permission_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system` int DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saved_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filters` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `security_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_security_logs_created` (`created_at`),
  KEY `idx_security_logs_actor_created` (`actor_public_id`,`created_at`),
  KEY `idx_security_logs_event_created` (`event_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sla_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sla_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_minutes` int DEFAULT NULL,
  `resolve_minutes` int DEFAULT NULL,
  `escalation_payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  `is_active` int DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `wip_limit` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subtasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subtasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assignee_user_id` int DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_state` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `scope` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cursor_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_assignees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_assignees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_dependencies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `depends_on_task_id` int DEFAULT NULL,
  `dependency_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_relations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_task_id` int DEFAULT NULL,
  `child_task_id` int DEFAULT NULL,
  `relation_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `legacy_subtask_public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `idx_task_rel_child_type` (`child_task_id`,`relation_type`),
  UNIQUE KEY `idx_task_rel_legacy` (`legacy_subtask_public_id`),
  KEY `idx_task_rel_parent_type_sort` (`parent_task_id`,`relation_type`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `old_status` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_task_templates_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_watchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_watchers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `parent_task_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sla_policy_id` int DEFAULT NULL,
  `sla_response_deadline` datetime DEFAULT NULL,
  `sla_resolve_deadline` datetime DEFAULT NULL,
  `sla_breached` tinyint NOT NULL DEFAULT '0',
  `priority_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `assignee_user_id` int DEFAULT NULL,
  `creator_user_id` int DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `row_version` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_tasks_project` (`project_id`),
  KEY `idx_tasks_status` (`status_code`),
  KEY `idx_tasks_due` (`due_at`),
  KEY `idx_tasks_updated_public` (`updated_at`,`public_id`),
  KEY `idx_tasks_active_updated` (`deleted_at`,`archived_at`,`updated_at`,`public_id`),
  KEY `idx_tasks_project_active_updated` (`project_id`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_status_active_updated` (`status_code`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_priority_active_updated` (`priority_code`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_assignee_active_updated` (`assignee_user_id`,`deleted_at`,`archived_at`,`updated_at`),
  KEY `idx_tasks_creator_active_updated` (`creator_user_id`,`deleted_at`,`archived_at`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'team',
  `parent_id` int DEFAULT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_user_id` int DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `member_user_ids` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_teams_created_by` (`created_by_user_id`),
  KEY `idx_teams_type` (`team_type`),
  KEY `idx_teams_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `two_factor_secrets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `two_factor_secrets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `secret_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `backup_codes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_sessions_token` (`token_hash`),
  KEY `idx_sessions_user_device` (`user_id`,`device_fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` int DEFAULT '1',
  `is_root` int DEFAULT '0',
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `login` (`login`),
  KEY `idx_users_login` (`login`),
  KEY `idx_users_created_by` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_deliveries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webhook_id` int DEFAULT NULL,
  `event_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_code` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `payload_json` text COLLATE utf8mb4_unicode_ci,
  `signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `dead_letter` int NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_webhook_deliveries_queue_runnable` (`status`,`dead_letter`,`next_run_at`,`locked_at`,`created_at`),
  KEY `idx_webhook_deliveries_attempts` (`attempts`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci,
  `secret_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `events` text COLLATE utf8mb4_unicode_ci,
  `is_active` int DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `task_id` int DEFAULT NULL,
  `minutes_spent` int DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `logged_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `working_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `working_hours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calendar_id` int DEFAULT NULL,
  `weekday` int DEFAULT NULL,
  `start_time` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_time` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
