<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Adds tenant keys to dashboard data sources that were introduced before workspaces. */
final class OrganizationDashboardWidgetScopeMigration implements MigrationInterface
{
    private const TABLES = ['tags', 'subscriptions', 'automation_rules', 'automation_runs'];

    public function key(): string
    {
        return '20260919_000008_organization_dashboard_widget_scope';
    }

    public function description(): string
    {
        return 'Scope dashboard directory, subscription and automation widgets by workspace';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            throw new \RuntimeException('Unable to resolve default workspace for dashboard widget scope');
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
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        }
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
