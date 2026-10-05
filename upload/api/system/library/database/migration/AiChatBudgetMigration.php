<?php
declare(strict_types=1);
namespace Api\System\Library\Database\Migration;
use PDO;
use Api\System\Library\Database\IndexHelper;
final class AiChatBudgetMigration implements MigrationInterface
{
    public function key(): string { return '20261005_000004_ai_chat_budgets'; }
    public function description(): string { return 'Atomic per-user AI chat request and token reservations'; }
    public function up(PDO $pdo, string $driver): void
    {
        $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_chat_budget_locks (user_id BIGINT NOT NULL PRIMARY KEY){$suffix}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_chat_budget_reservations (
            id {$id}, public_id VARCHAR(64) NOT NULL UNIQUE,
            user_id BIGINT NOT NULL, run_public_id VARCHAR(64) NOT NULL,
            reserved_tokens BIGINT NOT NULL, charged_tokens BIGINT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, finished_at DATETIME NULL
        ){$suffix}");
        IndexHelper::createIndexIfNotExists($pdo, 'ai_chat_budget_reservations', 'idx_ai_chat_budget_user', 'user_id, created_at');
        IndexHelper::createIndexIfNotExists($pdo, 'ai_chat_budget_reservations', 'idx_ai_chat_budget_active', 'status, expires_at');
    }
}
