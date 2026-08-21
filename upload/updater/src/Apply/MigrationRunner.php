<?php
declare(strict_types=1);

namespace Updater\Apply;

use Updater\Util\WorkBudget;

final class MigrationRunner
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Runs pending database migrations using the CRM application's own
     * migration machinery (the same classes used by the installer and by
     * /internal/migration/up). Runs fully inside the web request, so it
     * works on shared hosting without shell access. Never throws: failures
     * are returned as a report so the caller can decide how to proceed.
     *
     * With $maxMigrations set, at most that many migrations run per call and
     * the report carries 'done' => false until the backlog is empty, so an
     * update applies a large migration backlog across many short requests
     * (each well under shared-hosting timeouts) instead of one giant one.
     *
     * @param int|null $maxMigrations max migrations per call (null = all)
     * @return array{ok:bool,done:bool,driver?:string,executed?:array<int,string>,pending_before?:array<int,string>,pending_after?:array<int,string>,applied_total?:array<int,string>,error?:string}
     */
    public function run(?int $maxMigrations = null, ?WorkBudget $budget = null): array
    {
        try {
            $connection = \Updater\Db\Connection::open($this->basePath);
            $pdo = $connection['pdo'];
            $driver = $connection['driver'];

            $schema = new \Api\System\Library\Database\SchemaManager();
            $migrations = new \Api\System\Library\Database\Migration\MigrationManager($schema);
            $before = $migrations->status($pdo, $driver);
            $limit = $maxMigrations !== null && $maxMigrations > 0 ? $maxMigrations : PHP_INT_MAX;
            $executed = $migrations->migrateUpLimit($pdo, $driver, $limit);
            $after = $migrations->status($pdo, $driver);

            return [
                'ok' => true,
                'done' => ($after['pending'] ?? []) === [],
                'driver' => $driver,
                'executed' => $executed,
                'pending_before' => $before['pending'],
                'pending_after' => $after['pending'],
                'applied_total' => $after['applied'],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'done' => false,
                'error' => $e->getMessage(),
                'executed' => [],
                'pending_before' => [],
                'pending_after' => [],
                'applied_total' => [],
            ];
        }
    }
}
