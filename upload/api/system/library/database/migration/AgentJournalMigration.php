<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class AgentJournalMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261007_000002_agent_journal';
    }

    public function description(): string
    {
        return 'Idempotent task journal events linked to native comments';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_journal (
                id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL,
                task_public_id TEXT NOT NULL, event_id TEXT NOT NULL, operation_id TEXT NOT NULL,
                event_kind TEXT NOT NULL, stage TEXT NOT NULL, owner_user_id INTEGER NOT NULL,
                agent_id TEXT NOT NULL, run_id TEXT NOT NULL, generation INTEGER NOT NULL,
                comment_public_id TEXT NOT NULL, payload_hash TEXT NOT NULL, created_at DATETIME NOT NULL,
                UNIQUE (organization_id, task_public_id, event_id)
            )');
            return;
        }
        if ($driver !== 'mysql') {
            throw new \RuntimeException('Agent journal requires MySQL');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_journal (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            task_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            operation_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            stage VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            run_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            generation BIGINT UNSIGNED NOT NULL,
            comment_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            payload_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_agent_journal_event (organization_id, task_public_id, event_id),
            KEY idx_agent_journal_task (organization_id, task_public_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
