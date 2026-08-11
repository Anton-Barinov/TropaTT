<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Adds source metadata columns to the tasks table so a task can carry a
 * reference to the chat dialogue (and message) it was created from.
 *
 * Mirrors the source_* convention already used by knowledge_pages/spaces,
 * keeping the CRM's install-location independence intact: source_url is a
 * relative route, so the same row works on any domain/sub-directory hosting.
 */
final class TaskChatSourceMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260811_000001_task_chat_source';
    }

    public function description(): string
    {
        return 'Add source metadata (chat dialogue reference) to tasks';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver !== 'mysql') {
            return;
        }

        $columns = $this->existingColumns($pdo, 'tasks');
        $alter = [];

        if (!in_array('source_type', $columns, true)) {
            $alter[] = 'ADD COLUMN source_type VARCHAR(64) NULL AFTER updated_at';
        }
        if (!in_array('source_id', $columns, true)) {
            $alter[] = 'ADD COLUMN source_id VARCHAR(255) NULL AFTER source_type';
        }
        if (!in_array('source_url', $columns, true)) {
            $alter[] = 'ADD COLUMN source_url VARCHAR(2048) NULL AFTER source_id';
        }
        if (!in_array('source_payload_json', $columns, true)) {
            $alter[] = 'ADD COLUMN source_payload_json JSON NULL AFTER source_url';
        }

        if ($alter !== []) {
            $pdo->exec('ALTER TABLE tasks ' . implode(', ', $alter));
        }

        IndexHelper::createIndexIfNotExists($pdo, 'tasks', 'idx_tasks_source', 'source_type, source_id');
    }

    /** @return array<int,string> */
    private function existingColumns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return is_array($result) ? array_map('strval', $result) : [];
        } catch (\Throwable $e) {
            error_log('[TaskChatSourceMigration::existingColumns] ' . $e->getMessage());

            return [];
        }
    }
}
