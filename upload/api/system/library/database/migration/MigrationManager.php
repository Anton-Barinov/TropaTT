<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\SchemaManager;
use PDO;

final class MigrationManager
{
    /** @var array<int,MigrationInterface> */
    private array $migrations;

    public function __construct(SchemaManager $schema)
    {
        $this->migrations = [
            new InitialSchemaMigration($schema),
            new CommentDraftsMigration(),
            new OrganizationMembershipsMigration(),
            new CompanyClientContactOwnershipMigration(),
            new TemplateWorkflowOwnershipMigration(),
            new ListQueryIndexesMigration(),
            new IndexRepairMigration(),
            new SessionDeviceModelMigration(),
            new TeamMembersMigration(),
            new ProjectTeamsMigration(),
            new TeamCreatorsMigration(),
            new NotificationEventPayloadMigration(),
            new TaskSubtaskRelationsMigration(),
            new ClientProfileExpansionMigration(),
            new CalendarEventDescriptionMigration(),
            new AiFoundationMigration(),
            new AiJobsRuntimeCompatibilityMigration(),
            new AiIndexCoverageMigration(),
            new AiAuthorTimestampCoverageMigration(),
            new AiSuggestionsInputHashMigration(),
            new AiSuggestionsCacheFreshnessMigration(),
            new ImportExportJobsQueueRuntimeMigration(),
            new NotificationPushQueueRuntimeMigration(),
            new WebhookDeliveriesQueueRuntimeMigration(),
            new CrmEntityConsolidationMigration(),
            new RecurringProcessorMigration(),
            new RecurringRuleTitleMigration(),
            new GanttPerformanceIndexesMigration(),
            new KnowledgeBaseMigration(),
            new KnowledgeCommentsRepairMigration(),
            new IntakeItemsMigration(),
            new TaskHumanReadableKeysMigration(),
            new TaskRelationsV2Migration(),
            new SavedViewsV2Migration(),
            new TaskActivityFeedMigration(),
            new WorkCyclesMigration(),
            new ProjectModulesMigration(),
            new KnowledgePageVersionsMigration(),
            new StickyNotesMigration(),
            new TaskEstimatesMigration(),
            new KnowledgeSourceMetadataMigration(),
            new RateLimitsMigration(),
            new CounterpartyAddressActualMigration(),
            new TaskDirectClientMigration(),
        ];
    }

    /** @return array{applied:array<int,string>,pending:array<int,string>,all:array<int,string>} */
    public function status(PDO $pdo, string $driver): array
    {
        $this->ensureTable($pdo, $driver);
        $this->backfillLegacyState($pdo, $driver);
        $applied = $this->appliedKeys($pdo);
        $all = array_map(static fn(MigrationInterface $m): string => $m->key(), $this->migrations);
        $pending = array_values(array_diff($all, $applied));

        return [
            'applied' => $applied,
            'pending' => $pending,
            'all' => $all,
        ];
    }

    /** @return array<int,string> */
    public function migrateUp(PDO $pdo, string $driver): array
    {
        $this->ensureTable($pdo, $driver);
        $this->backfillLegacyState($pdo, $driver);
        $applied = $this->appliedKeys($pdo);
        $executed = [];

        foreach ($this->migrations as $migration) {
            if (in_array($migration->key(), $applied, true)) {
                continue;
            }

            $migration->up($pdo, $driver);
            $this->markApplied($pdo, $migration);
            $executed[] = $migration->key();
        }

        return $executed;
    }

    /** @return array{applied:array<int,string>,pending:array<int,string>,would_execute:array<int,string>,count:int} */
    public function dryRun(PDO $pdo, string $driver): array
    {
        $status = $this->status($pdo, $driver);
        $wouldExecute = $status['pending'];

        return [
            'applied' => $status['applied'],
            'pending' => $status['pending'],
            'would_execute' => $wouldExecute,
            'count' => count($wouldExecute),
        ];
    }

    /**
     * @return array{
     *   rollback_possible:bool,
     *   safe_plan_available:bool,
     *   checks:array{unknown_applied:bool,all_applied_have_down:bool,reverse_order_safe:bool},
     *   applied:array<int,string>,
     *   unknown_applied:array<int,string>,
     *   reversible_applied:array<int,string>,
     *   non_reversible_applied:array<int,string>,
     *   rollback_plan:array<int,string>,
     *   safe_rollback_plan:array<int,string>
     * }
     */
    public function rollbackCheck(PDO $pdo, string $driver): array
    {
        $this->ensureTable($pdo, $driver);
        $this->backfillLegacyState($pdo, $driver);

        $applied = $this->appliedKeys($pdo);
        $knownMap = $this->migrationMap();

        $unknownApplied = array_values(array_filter(
            $applied,
            static fn(string $key): bool => !isset($knownMap[$key])
        ));

        $reversibleApplied = [];
        $nonReversibleApplied = [];

        foreach ($applied as $key) {
            $migration = $knownMap[$key] ?? null;
            if ($migration === null) {
                continue;
            }

            if (method_exists($migration, 'down')) {
                $reversibleApplied[] = $key;
                continue;
            }

            $nonReversibleApplied[] = $key;
        }

        $allAppliedHaveDown = $nonReversibleApplied === [];
        $reverseOrderSafe = $allAppliedHaveDown && $unknownApplied === [];
        $rollbackPossible = $reverseOrderSafe;
        $rollbackPlan = $rollbackPossible ? array_reverse($reversibleApplied) : [];
        $safeRollbackPlan = $this->buildSafeRollbackPlan($rollbackPossible, $unknownApplied, $nonReversibleApplied);

        return [
            'rollback_possible' => $rollbackPossible,
            'safe_plan_available' => $safeRollbackPlan !== [],
            'checks' => [
                'unknown_applied' => $unknownApplied === [],
                'all_applied_have_down' => $allAppliedHaveDown,
                'reverse_order_safe' => $reverseOrderSafe,
            ],
            'applied' => $applied,
            'unknown_applied' => $unknownApplied,
            'reversible_applied' => $reversibleApplied,
            'non_reversible_applied' => $nonReversibleApplied,
            'rollback_plan' => $rollbackPlan,
            'safe_rollback_plan' => $safeRollbackPlan,
        ];
    }

    private function ensureTable(PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'mysql' => 'CREATE TABLE IF NOT EXISTS migrations (id BIGINT AUTO_INCREMENT PRIMARY KEY, migration_key VARCHAR(191) UNIQUE, description VARCHAR(255), applied_at DATETIME)',
            'pgsql' => 'CREATE TABLE IF NOT EXISTS migrations (id BIGSERIAL PRIMARY KEY, migration_key VARCHAR(191) UNIQUE, description VARCHAR(255), applied_at TIMESTAMP)',
            'sqlsrv' => 'IF NOT EXISTS (SELECT * FROM sysobjects WHERE name=\'migrations\' AND xtype=\'U\') CREATE TABLE migrations (id INT IDENTITY(1,1) PRIMARY KEY, migration_key NVARCHAR(191) UNIQUE, description NVARCHAR(255), applied_at DATETIME2)',
            default => 'CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration_key VARCHAR(191) UNIQUE, description VARCHAR(255), applied_at DATETIME)',
        };

        $pdo->exec($sql);
    }

    /** @return array<int,string> */
    private function appliedKeys(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT migration_key FROM migrations ORDER BY id ASC')->fetchAll() ?: [];
        return array_map(static fn(array $row): string => (string)$row['migration_key'], $rows);
    }

    private function markApplied(PDO $pdo, MigrationInterface $migration): void
    {
        $stmt = $pdo->prepare('INSERT INTO migrations (migration_key, description, applied_at) VALUES (:migration_key, :description, :applied_at)');
        $stmt->execute([
            'migration_key' => $migration->key(),
            'description' => $migration->description(),
            'applied_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function backfillLegacyState(PDO $pdo, string $driver): void
    {
        $applied = $this->appliedKeys($pdo);
        if ($applied !== []) {
            return;
        }

        $initial = $this->migrations[0] ?? null;
        if (!$initial instanceof MigrationInterface || $initial->key() !== '20260417_000001_initial_schema') {
            return;
        }

        if (!$this->tableExists($pdo, 'users', $driver) || !$this->tableExists($pdo, 'projects', $driver) || !$this->tableExists($pdo, 'tasks', $driver)) {
            return;
        }

        $this->markApplied($pdo, $initial);
    }

    private function tableExists(PDO $pdo, string $table, string $driver): bool
    {
        $sql = $driver === 'sqlsrv'
            ? 'SELECT TOP 1 1 FROM ' . $table
            : 'SELECT 1 FROM ' . $table . ' LIMIT 1';

        try {
            $pdo->query($sql);
            return true;
        } catch (\Throwable $e) {
            error_log('[MigrationManager::tableExists] DB query: ' . $e->getMessage());
            return false;
        }
    }

    /** @return array<string,MigrationInterface> */
    private function migrationMap(): array
    {
        $map = [];

        foreach ($this->migrations as $migration) {
            $map[$migration->key()] = $migration;
        }

        return $map;
    }

    /** @param array<int,string> $unknownApplied @param array<int,string> $nonReversibleApplied @return array<int,string> */
    private function buildSafeRollbackPlan(bool $rollbackPossible, array $unknownApplied, array $nonReversibleApplied): array
    {
        if ($rollbackPossible) {
            return [];
        }

        $plan = [
            '1) Freeze write traffic or switch application to maintenance mode.',
            '2) Create and verify a fresh backup before rollback actions.',
        ];

        if ($unknownApplied !== []) {
            $plan[] = '3) Unknown applied migrations detected; stop automated rollback and perform DBA review: ' . implode(', ', $unknownApplied) . '.';
            return $plan;
        }

        if ($nonReversibleApplied !== []) {
            $plan[] = '3) Non-reversible applied migrations: ' . implode(', ', $nonReversibleApplied) . '.';
            $plan[] = '4) Restore database from backup/snapshot taken before the first non-reversible migration.';
            $plan[] = '5) Re-run /internal/migration/up to reach known consistent schema state.';
            $plan[] = '6) Validate auth, CSRF, and smoke-test baseline before re-enabling write traffic.';
            return $plan;
        }

        $plan[] = '3) No non-reversible migrations detected, but rollback is not marked safe; perform manual DBA verification.';

        return $plan;
    }
}
