<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Migration;

use PDO;

/**
 * MySQL migration for crm.jira-migration module tables.
 * Only MySQL is supported. SQLite is forbidden.
 */
class JiraMigrationTablesMigration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        // ── jira_connections ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_connections` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `public_id` VARCHAR(64) NOT NULL UNIQUE,
                `name` VARCHAR(255) NOT NULL,
                `site_url` VARCHAR(512) NOT NULL,
                `cloud_id` VARCHAR(128) NULL,
                `auth_type` VARCHAR(32) NOT NULL DEFAULT 'api_token',
                `email` VARCHAR(255) NULL,
                `token_encrypted` TEXT NULL,
                `oauth_payload_json` JSON NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
                `last_checked_at` DATETIME NULL,
                `last_error` TEXT NULL,
                `created_by_user_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_jobs ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_jobs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `public_id` VARCHAR(64) NOT NULL UNIQUE,
                `connection_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
                `mode` VARCHAR(32) NOT NULL,
                `source_scope_json` JSON NOT NULL,
                `target_options_json` JSON NOT NULL,
                `progress_json` JSON NULL,
                `summary_json` JSON NULL,
                `report_markdown` MEDIUMTEXT NULL,
                `current_step` VARCHAR(64) NULL,
                `progress_percent` DECIMAL(5,2) DEFAULT 0,
                `started_at` DATETIME NULL,
                `finished_at` DATETIME NULL,
                `created_by_user_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                INDEX `idx_jira_jobs_status` (`status`),
                INDEX `idx_jira_jobs_connection` (`connection_id`),
                INDEX `idx_jira_jobs_user` (`created_by_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_job_items ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_job_items` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `source_type` VARCHAR(64) NOT NULL,
                `source_id` VARCHAR(191) NOT NULL,
                `source_key` VARCHAR(191) NULL,
                `source_parent_id` VARCHAR(191) NULL,
                `target_type` VARCHAR(64) NULL,
                `target_public_id` VARCHAR(64) NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `checksum` VARCHAR(128) NULL,
                `source_updated_at` DATETIME NULL,
                `payload_json` JSON NULL,
                `error_code` VARCHAR(64) NULL,
                `error_message` TEXT NULL,
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                UNIQUE KEY `uk_job_item_source` (`job_id`, `source_type`, `source_id`),
                INDEX `idx_item_status` (`job_id`, `status`),
                INDEX `idx_item_source` (`source_type`, `source_id`),
                INDEX `idx_item_target` (`target_type`, `target_public_id`),
                INDEX `idx_item_parent` (`source_parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_job_logs ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_job_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `level` VARCHAR(16) NOT NULL,
                `step` VARCHAR(64) NULL,
                `message` TEXT NOT NULL,
                `context_json` JSON NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_log_job` (`job_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_identity_mappings ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_identity_mappings` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `public_id` VARCHAR(64) NOT NULL UNIQUE,
                `connection_id` BIGINT UNSIGNED NOT NULL,
                `jira_subject_type` VARCHAR(32) NOT NULL,
                `jira_subject_id` VARCHAR(191) NOT NULL,
                `jira_subject_name` VARCHAR(255) NULL,
                `crm_subject_type` VARCHAR(32) NULL,
                `crm_subject_public_id` VARCHAR(64) NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'unresolved',
                `payload_json` JSON NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                UNIQUE KEY `uk_mapping_connection_subject` (`connection_id`, `jira_subject_type`, `jira_subject_id`),
                INDEX `idx_mapping_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_unresolved_entities ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_unresolved_entities` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `source_type` VARCHAR(64) NOT NULL,
                `source_id` VARCHAR(191) NOT NULL,
                `reason_code` VARCHAR(64) NOT NULL,
                `reason_text` TEXT NOT NULL,
                `payload_json` JSON NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_unresolved_job` (`job_id`),
                INDEX `idx_unresolved_source` (`source_type`, `source_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_unsupported_fields ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_unsupported_fields` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `issue_id` VARCHAR(191) NULL,
                `field_id` VARCHAR(191) NOT NULL,
                `field_name` VARCHAR(255) NULL,
                `field_schema_json` JSON NULL,
                `handling` VARCHAR(64) NOT NULL,
                `sample_json` JSON NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_unsupported_job` (`job_id`),
                INDEX `idx_unsupported_field` (`field_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_rate_limits ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_rate_limits` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `connection_id` BIGINT UNSIGNED NOT NULL UNIQUE,
                `requests_made` INT UNSIGNED NOT NULL DEFAULT 0,
                `window_started_at` DATETIME NULL,
                `retry_after_until` DATETIME NULL,
                `updated_at` DATETIME NOT NULL,
                INDEX `idx_ratelimit_conn` (`connection_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── jira_settings ──
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `jira_settings` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `module_name` VARCHAR(190) NOT NULL,
                `setting_key` VARCHAR(190) NOT NULL,
                `setting_value` JSON NULL,
                `updated_at` DATETIME NOT NULL,
                UNIQUE KEY `uk_setting` (`module_name`, `setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $tables = [
            'jira_settings',
            'jira_rate_limits',
            'jira_unsupported_fields',
            'jira_unresolved_entities',
            'jira_identity_mappings',
            'jira_job_logs',
            'jira_job_items',
            'jira_jobs',
            'jira_connections',
        ];

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    public function isApplied(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'jira_connections'");
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
