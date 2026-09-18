<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Adds the active-workspace boundary to ideas and their workflow records. */
final class OrganizationIdeasScopeMigration implements MigrationInterface
{
    /** @var list<string> */
    private const TABLES = [
        'ideas', 'idea_votes', 'idea_comments', 'idea_ai_iterations',
        'idea_questions', 'idea_answers', 'idea_analyses', 'idea_task_drafts',
    ];

    public function key(): string
    {
        return '20260919_000004_organization_ideas_scope';
    }

    public function description(): string
    {
        return 'Scope ideas and idea workflow records to the active organization';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            throw new \RuntimeException('Unable to resolve default organization for ideas scope migration');
        }
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                continue;
            }
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            $stmt = $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL");
            $stmt->execute(['organization_id' => (int)$default]);
            IndexHelper::createIndexIfNotExists($pdo, $table, 'idx_' . $table . '_organization', 'organization_id', false, $driver);
        }
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table LIMIT 1");
        } elseif ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare("SELECT TOP 1 1 FROM sys.objects WHERE name = :table AND type = 'U'");
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        }
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
