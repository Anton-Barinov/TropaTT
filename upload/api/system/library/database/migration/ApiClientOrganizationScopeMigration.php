<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * TROPATTCRM-606: the api_clients / api_keys stores had no organization
 * column at all — a structural gap that made cross-org scoping impossible.
 * Every other business store already carries the nullable workspace boundary
 * (OrganizationScopeMigration family); this migration extends the same
 * pattern to API clients and their keys, backfilling legacy rows into the
 * single pre-existing workspace exactly like the other scope migrations.
 *
 * The column stays nullable so the upgrade remains resumable; ApiClientService
 * enforces the boundary on new writes once the column exists.
 */
final class ApiClientOrganizationScopeMigration implements MigrationInterface
{
    private const TABLES = ['api_clients', 'api_keys'];

    public function key(): string
    {
        return '20260921_000001_api_client_organization_scope';
    }

    public function description(): string
    {
        return 'Scope API clients and keys by workspace (TROPATTCRM-606)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            // No workspace exists yet (pre-organizations installation): the
            // OrganizationScopeMigration will create one; leave the column
            // absent so a later run adds it with the backfill.
            return;
        }
        $organizationId = (int)$default;

        foreach (self::TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                continue;
            }
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            if (!IndexHelper::columnExists($pdo, $driver, $table, 'organization_id')) {
                throw new \RuntimeException("Unable to add {$table}.organization_id");
            }
            $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL")
                ->execute(['organization_id' => $organizationId]);
            IndexHelper::createIndexIfNotExists(
                $pdo,
                $table,
                'idx_' . $table . '_organization',
                'organization_id',
                false,
                $driver
            );
        }
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
