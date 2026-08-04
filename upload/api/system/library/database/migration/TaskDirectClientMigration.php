<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Allow tasks to be linked directly to a client (counterparty) without a project.
 * Stores the counterparty public_id on the tasks row.
 */
final class TaskDirectClientMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260804_000002_task_direct_client';
    }

    public function description(): string
    {
        return 'Add direct task-to-client link (client_public_id on tasks)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'tasks', 'client_public_id', 'VARCHAR(64) NULL', $driver);
        IndexHelper::createIndexIfNotExists($pdo, 'tasks', 'idx_tasks_client_public_id', 'client_public_id', false, $driver);
    }
}
