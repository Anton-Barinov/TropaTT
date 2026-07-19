<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class NotificationPushQueueRuntimeMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260505_000041_notification_push_queue_runtime';
    }

    public function description(): string
    {
        return 'Create queue table for async push dispatch runtime';
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_push_queue (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            user_id INTEGER NOT NULL,
            notification_public_id VARCHAR(64) NULL,
            payload_json {$text} NOT NULL,
            status VARCHAR(32) NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            next_run_at {$dt} NULL,
            locked_at {$dt} NULL,
            last_error {$text} NULL,
            dead_letter INTEGER NOT NULL DEFAULT 0,
            created_at {$dt},
            updated_at {$dt}
        )");

        try {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_push_queue_runnable ON notification_push_queue(status, dead_letter, next_run_at, locked_at, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_push_queue_user_created ON notification_push_queue(user_id, created_at)');
        } catch (\Throwable $e) {
            error_log('[NotificationPushQueueRuntimeMigration::up] CREATE INDEX: ' . $e->getMessage());
            // noop
        }
    }
}
