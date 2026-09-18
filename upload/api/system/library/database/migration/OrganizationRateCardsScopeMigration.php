<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Adds the active workspace boundary to rate cards and their children. */
final class OrganizationRateCardsScopeMigration implements MigrationInterface
{
    private const TABLES = ['rate_cards', 'rate_card_lines', 'rate_card_assignments'];

    public function key(): string
    {
        return '20260919_000006_organization_rate_cards_scope';
    }

    public function description(): string
    {
        return 'Scope rate cards, lines and assignments by workspace';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            throw new \RuntimeException('Unable to resolve default workspace for rate cards scope migration');
        }
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                continue;
            }
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL")
                ->execute(['organization_id' => (int)$default]);
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
