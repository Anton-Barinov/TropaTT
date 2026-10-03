<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use Api\System\Library\Database\IndexHelper;

final class AiChatRunsMigration implements MigrationInterface
{
    public function key(): string { return '20261004_000001_ai_chat_runs'; }
    public function description(): string { return 'Persist resumable AI chat execution and checkpoints'; }
    public function up(PDO $pdo, string $driver): void
    {
        $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $text = $driver === 'sqlite' ? 'TEXT' : 'LONGTEXT';
        $suffix = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_chat_runs (
            id {$id}, public_id VARCHAR(64) NOT NULL UNIQUE,
            chat_id BIGINT NOT NULL, actor_user_id BIGINT NOT NULL,
            message_public_id VARCHAR(64) NOT NULL UNIQUE,
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            state_json {$text} NOT NULL, step_count INTEGER NOT NULL DEFAULT 0,
            lock_token VARCHAR(64) NULL, locked_at DATETIME NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (chat_id, message_public_id)
        ){$suffix}");
        IndexHelper::createIndexIfNotExists($pdo, 'ai_chat_runs', 'idx_ai_chat_actor', 'chat_id, actor_user_id, id');
    }
}
