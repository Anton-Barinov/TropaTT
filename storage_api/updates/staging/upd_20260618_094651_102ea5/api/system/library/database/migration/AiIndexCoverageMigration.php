<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use Throwable;

final class AiIndexCoverageMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260427_000034_ai_index_coverage';
    }

    public function description(): string
    {
        return 'Ensure AI index coverage for public_id/intent/entity/actor/status/created_at access patterns';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $indexes = [
            ['ai_providers', 'idx_ai_providers_public_id', 'public_id', false],
            ['ai_provider_secrets', 'idx_ai_provider_secrets_public_id', 'public_id', false],
            ['ai_prompt_templates', 'idx_ai_prompt_templates_public_id', 'public_id', false],
            ['ai_json_schemas', 'idx_ai_json_schemas_public_id', 'public_id', false],
            ['ai_suggestions', 'idx_ai_suggestions_public_id', 'public_id', false],
            ['ai_jobs', 'idx_ai_jobs_public_id', 'public_id', false],
            ['ai_usage_logs', 'idx_ai_usage_logs_public_id', 'public_id', false],

            ['ai_intent_settings', 'idx_ai_intent_settings_intent_created', 'intent_code, updated_at', false],
            ['ai_prompt_templates', 'idx_ai_prompt_templates_intent_created', 'intent_code, created_at', false],
            ['ai_json_schemas', 'idx_ai_json_schemas_intent_created', 'intent_code, created_at', false],
            ['ai_suggestions', 'idx_ai_suggestions_intent_created', 'intent_code, created_at', false],
            ['ai_jobs', 'idx_ai_jobs_intent_created', 'intent_code, created_at', false],
            ['ai_usage_logs', 'idx_ai_usage_logs_intent_created', 'intent_code, created_at', false],

            ['ai_suggestions', 'idx_ai_suggestions_entity_created', 'entity_type, entity_public_id, created_at', false],
            ['ai_jobs', 'idx_ai_jobs_scope_created', 'scope_type, scope_public_id, created_at', false],

            ['ai_suggestions', 'idx_ai_suggestions_status_created', 'status, created_at', false],
            ['ai_jobs', 'idx_ai_jobs_status_created_v2', 'status, created_at', false],
            ['ai_usage_logs', 'idx_ai_usage_logs_status_created', 'status, created_at', false],

            ['ai_suggestions', 'idx_ai_suggestions_actor_created_v2', 'created_by_user_id, created_at', false],
            ['ai_jobs', 'idx_ai_jobs_actor_created_v2', 'requested_by_user_id, created_at', false],
            ['ai_usage_logs', 'idx_ai_usage_logs_actor_created_v2', 'user_id, created_at', false],
        ];

        foreach ($indexes as [$table, $name, $columns, $unique]) {
            if (!$this->tableExists($pdo, (string)$table)) {
                continue;
            }
            $this->createIndexIfMissing($pdo, (string)$table, (string)$name, (string)$columns, (bool)$unique);
        }
    }

    private function createIndexIfMissing(PDO $pdo, string $table, string $name, string $columns, bool $unique): void
    {
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        $sql = sprintf('CREATE %s INDEX %s ON %s(%s)', $unique ? 'UNIQUE' : '', $name, $table, $columns);
        try {
            $pdo->exec(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
        } catch (Throwable) {
            // keep migration idempotent across driver/index variants
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
                $stmt->execute(['table' => $table]);
                return (bool)$stmt->fetchColumn();
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT TOP 1 1 FROM sys.tables WHERE name = :table');
                $stmt->execute(['table' => $table]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_name = :table AND table_schema = ' .
                ($driver === 'pgsql' ? 'current_schema()' : 'DATABASE()') . ' LIMIT 1'
            );
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
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
                $stmt = $pdo->prepare('SELECT TOP 1 i.name FROM sys.indexes i INNER JOIN sys.objects o ON o.object_id = i.object_id WHERE o.name = :table AND i.name = :name');
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.statistics WHERE table_name = :table AND index_name = :name AND table_schema = DATABASE() LIMIT 1'
            );
            $stmt->execute(['table' => $table, 'name' => $name]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
