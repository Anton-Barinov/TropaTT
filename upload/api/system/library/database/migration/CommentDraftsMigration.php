<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class CommentDraftsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260418_000002_comment_drafts';
    }

    public function description(): string
    {
        return 'Add comment drafts table for task-level draft save/restore';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $text = $driver === 'sqlsrv' ? 'NVARCHAR(MAX)' : 'TEXT';

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS comment_drafts ("
            . "id {$id}, "
            . "public_id VARCHAR(64) UNIQUE, "
            . "user_id INTEGER NOT NULL, "
            . "task_id INTEGER NOT NULL, "
            . "body {$text}, "
            . "created_at {$dt}, "
            . "updated_at {$dt})"
        );

        try {
            IndexHelper::createIndexIfNotExists($pdo, 'comment_drafts', 'uq_comment_drafts_user_task', 'user_id, task_id', true);
        } catch (\Throwable $e) {
            error_log('[CommentDraftsMigration::up] CREATE UNIQUE: ' . $e->getMessage());
            // ignore for drivers without IF NOT EXISTS on index
        }
    }
}
