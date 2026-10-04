<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class AiAgentNameCleanupMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261004_000001_ai_agent_name_cleanup';
    }

    public function description(): string
    {
        return 'Replace legacy AI Copilot titles and user names with AI Assistant';
    }

    public function up(PDO $pdo, string $driver): void
    {
        try {
            $pdo->exec("UPDATE users SET full_name = 'AI Ассистент' WHERE login = 'ai_agent' OR public_id = 'usr_ai_agent' OR full_name LIKE '%copilot%' OR full_name LIKE '%Copilot%'");
        } catch (\Throwable) {
        }

        try {
            $pdo->exec("UPDATE users SET name = 'AI Ассистент' WHERE (login = 'ai_agent' OR public_id = 'usr_ai_agent' OR name LIKE '%copilot%' OR name LIKE '%Copilot%')");
        } catch (\Throwable) {
        }

        try {
            $pdo->exec("UPDATE chats SET title = 'AI Ассистент' WHERE type = 'ai_agent' OR title LIKE '%copilot%' OR title LIKE '%Copilot%'");
        } catch (\Throwable) {
        }
    }
}
