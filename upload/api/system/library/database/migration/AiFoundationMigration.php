<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class AiFoundationMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260425_000001_ai_foundation';
    }

    public function description(): string
    {
        return 'AI foundation tables, indexes and baseline defaults';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $bool = $driver === 'sqlsrv' ? 'BIT' : 'INTEGER';
        $text = $driver === 'sqlsrv' ? 'NVARCHAR(MAX)' : 'TEXT';
        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';

        $tables = [
            "CREATE TABLE IF NOT EXISTS ai_providers (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                provider_code VARCHAR(64),
                title VARCHAR(255),
                base_url {$text},
                api_path VARCHAR(255) NULL,
                default_model VARCHAR(190) NULL,
                timeout_ms INTEGER NULL,
                max_tokens INTEGER NULL,
                temperature VARCHAR(16) NULL,
                extra_headers {$text} NULL,
                provider_payload {$text} NULL,
                is_active {$bool} DEFAULT 1,
                is_default {$bool} DEFAULT 0,
                created_by_user_id INTEGER NULL,
                updated_by_user_id INTEGER NULL,
                created_at {$dt},
                updated_at {$dt},
                deleted_at {$dt} NULL
            )",
            "CREATE TABLE IF NOT EXISTS ai_provider_secrets (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                provider_id INTEGER,
                secret_encrypted {$text},
                key_hint VARCHAR(64) NULL,
                rotated_at {$dt} NULL,
                created_by_user_id INTEGER NULL,
                updated_by_user_id INTEGER NULL,
                created_at {$dt},
                updated_at {$dt}
            )",
            "CREATE TABLE IF NOT EXISTS ai_intent_settings (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                intent_code VARCHAR(128) UNIQUE,
                provider_id INTEGER NULL,
                model VARCHAR(190) NULL,
                feature_flag VARCHAR(128) NULL,
                required_permission VARCHAR(128) NULL,
                allow_sensitive_context {$bool} DEFAULT 0,
                max_tokens INTEGER NULL,
                temperature VARCHAR(16) NULL,
                is_enabled {$bool} DEFAULT 1,
                intent_payload {$text} NULL,
                created_by_user_id INTEGER NULL,
                updated_by_user_id INTEGER NULL,
                created_at {$dt},
                updated_at {$dt}
            )",
            "CREATE TABLE IF NOT EXISTS ai_prompt_templates (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                intent_code VARCHAR(128),
                locale VARCHAR(16),
                version INTEGER DEFAULT 1,
                template_text {$text},
                is_active {$bool} DEFAULT 1,
                created_by_user_id INTEGER NULL,
                updated_by_user_id INTEGER NULL,
                created_at {$dt},
                updated_at {$dt}
            )",
            "CREATE TABLE IF NOT EXISTS ai_json_schemas (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                intent_code VARCHAR(128),
                schema_version VARCHAR(32),
                schema_json {$text},
                is_active {$bool} DEFAULT 1,
                created_by_user_id INTEGER NULL,
                updated_by_user_id INTEGER NULL,
                created_at {$dt},
                updated_at {$dt}
            )",
            "CREATE TABLE IF NOT EXISTS ai_suggestions (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                intent_code VARCHAR(128),
                entity_type VARCHAR(64) NULL,
                entity_public_id VARCHAR(64) NULL,
                summary {$text} NULL,
                suggestion_json {$text},
                status VARCHAR(32) DEFAULT 'draft',
                created_by_user_id INTEGER NULL,
                confirmed_by_user_id INTEGER NULL,
                created_at {$dt},
                updated_at {$dt},
                expires_at {$dt} NULL
            )",
            "CREATE TABLE IF NOT EXISTS ai_jobs (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                job_type VARCHAR(64),
                action_type VARCHAR(128) NULL,
                intent_code VARCHAR(128) NULL,
                status VARCHAR(32),
                requested_by_user_id INTEGER NULL,
                scope_type VARCHAR(64) NULL,
                scope_public_id VARCHAR(64) NULL,
                idempotency_key_hash VARCHAR(255) NULL,
                payload_json {$text} NULL,
                result_json {$text} NULL,
                error_code VARCHAR(64) NULL,
                error_message {$text} NULL,
                created_at {$dt},
                started_at {$dt} NULL,
                finished_at {$dt} NULL,
                updated_at {$dt}
            )",
            "CREATE TABLE IF NOT EXISTS ai_usage_logs (
                id {$id},
                public_id VARCHAR(64) UNIQUE,
                user_id INTEGER NULL,
                provider_public_id VARCHAR(64) NULL,
                action_type VARCHAR(128),
                intent_code VARCHAR(128) NULL,
                status VARCHAR(32),
                error_code VARCHAR(64) NULL,
                request_tokens INTEGER NULL,
                response_tokens INTEGER NULL,
                total_tokens INTEGER NULL,
                latency_ms INTEGER NULL,
                is_sensitive_context {$bool} DEFAULT 0,
                request_meta {$text} NULL,
                created_at {$dt}
            )",
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        $this->createIndexIfMissing($pdo, 'ai_providers', 'uq_ai_providers_provider_code_active', 'provider_code, is_active', false);
        $this->createIndexIfMissing($pdo, 'ai_providers', 'idx_ai_providers_default_active', 'is_default, is_active', false);
        $this->createIndexIfMissing($pdo, 'ai_provider_secrets', 'uq_ai_provider_secrets_provider_id', 'provider_id', true);
        $this->createIndexIfMissing($pdo, 'ai_intent_settings', 'idx_ai_intent_settings_provider', 'provider_id', false);
        $this->createIndexIfMissing($pdo, 'ai_prompt_templates', 'idx_ai_prompt_templates_intent_locale', 'intent_code, locale', false);
        $this->createIndexIfMissing($pdo, 'ai_json_schemas', 'idx_ai_json_schemas_intent', 'intent_code', false);
        $this->createIndexIfMissing($pdo, 'ai_suggestions', 'idx_ai_suggestions_scope', 'entity_type, entity_public_id, created_at', false);
        $this->createIndexIfMissing($pdo, 'ai_suggestions', 'idx_ai_suggestions_actor', 'created_by_user_id, created_at', false);
        $this->createIndexIfMissing($pdo, 'ai_jobs', 'idx_ai_jobs_status_created', 'status, created_at', false);
        $this->createIndexIfMissing($pdo, 'ai_jobs', 'idx_ai_jobs_action_created', 'action_type, created_at', false);
        $this->createIndexIfMissing($pdo, 'ai_jobs', 'idx_ai_jobs_actor_created', 'requested_by_user_id, created_at', false);
        $this->createIndexIfMissing($pdo, 'ai_usage_logs', 'idx_ai_usage_logs_actor_created', 'user_id, created_at', false);
        $this->createIndexIfMissing($pdo, 'ai_usage_logs', 'idx_ai_usage_logs_action_created', 'action_type, created_at', false);

        $this->seedAiRetentionDefaults($pdo);
        $this->seedAiActionTypes($pdo);
    }

    private function seedAiRetentionDefaults(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $scope = 'ai_retention';
        $defaults = [
            'suggestions_ttl_days' => 30,
            'jobs_ttl_days' => 30,
            'usage_logs_ttl_days' => 90,
            'prompts_ttl_days' => 30,
        ];

        foreach ($defaults as $name => $value) {
            $check = $pdo->prepare('SELECT id FROM settings WHERE scope = :scope AND name = :name LIMIT 1');
            $check->execute(['scope' => $scope, 'name' => $name]);
            if ($check->fetchColumn() !== false) {
                continue;
            }

            $insert = $pdo->prepare('INSERT INTO settings (public_id, scope, name, value, created_at, updated_at) VALUES (:public_id, :scope, :name, :value, :created_at, :updated_at)');
            $insert->execute([
                'public_id' => 'stg_' . strtoupper(bin2hex(random_bytes(8))),
                'scope' => $scope,
                'name' => $name,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedAiActionTypes(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $scope = 'ai_actions';
        $name = 'allowlist';
        $defaults = [
            'task_summary',
            'task_decomposition',
            'task_checklist',
            'task_quality',
            'task_next_action',
            'task_comment_draft',
            'project_summary',
            'project_risk_summary',
            'project_client_report',
            'client_summary',
            'client_meeting_prep',
            'client_data_quality',
            'client_safe_report',
            'calendar_event_agenda',
            'dashboard_daily_digest',
            'analytics_kpi_explanation',
            'analytics_risks_explanation',
            'analytics_team_workload_summary',
            'admin_log_review',
            'webhook_health_review',
            'workflow_rule_audit',
            'my_day_plan',
            'my_week_plan',
            'task_list_priority',
        ];

        $check = $pdo->prepare('SELECT id FROM settings WHERE scope = :scope AND name = :name LIMIT 1');
        $check->execute(['scope' => $scope, 'name' => $name]);
        if ($check->fetchColumn() !== false) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO settings (public_id, scope, name, value, created_at, updated_at) VALUES (:public_id, :scope, :name, :value, :created_at, :updated_at)');
        $insert->execute([
            'public_id' => 'stg_' . strtoupper(bin2hex(random_bytes(8))),
            'scope' => $scope,
            'name' => $name,
            'value' => json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createIndexIfMissing(PDO $pdo, string $table, string $name, string $columns, bool $unique): void
    {
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        $sql = sprintf('CREATE %s INDEX %s ON %s(%s)', $unique ? 'UNIQUE' : '', $name, $table, $columns);
        try {
            $pdo->exec(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
        } catch (\Throwable $e) {
            error_log('[AiFoundationMigration::createIndexIfMissing] ' . $e->getMessage());
            // Keep migration idempotent in concurrent execution paths.
        }
    }

    private function indexExists(PDO $pdo, string $table, string $name): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :name LIMIT 1");
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT TOP 1 i.name
                    FROM sys.indexes i
                    INNER JOIN sys.objects o ON o.object_id = i.object_id
                    WHERE o.name = :table AND i.name = :name');
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                   AND index_name = :name
                 LIMIT 1'
            );
            $stmt->execute(['table' => $table, 'name' => $name]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[AiFoundationMigration::indexExists] ' . $e->getMessage());
            return false;
        }
    }
}
