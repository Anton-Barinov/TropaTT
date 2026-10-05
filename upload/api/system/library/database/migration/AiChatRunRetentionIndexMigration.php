<?php
declare(strict_types=1);
namespace Api\System\Library\Database\Migration;
use PDO;
use Api\System\Library\Database\IndexHelper;
final class AiChatRunRetentionIndexMigration implements MigrationInterface
{
    public function key(): string { return '20261005_000005_ai_chat_retention_index'; }
    public function description(): string { return 'Index AI chat run retention and lease cleanup'; }
    public function up(PDO $pdo,string $driver): void
    {
        IndexHelper::createIndexIfNotExists($pdo,'ai_chat_runs','idx_ai_chat_retention','updated_at, status, locked_at');
    }
}
