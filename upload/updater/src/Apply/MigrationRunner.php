<?php
declare(strict_types=1);

namespace Updater\Apply;

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
     * @return array{ok:bool,driver?:string,executed?:array<int,string>,pending_before?:array<int,string>,pending_after?:array<int,string>,applied_total?:array<int,string>,error?:string}
     */
    public function run(): array
    {
        $connection = \Updater\Db\Connection::open($this->basePath);
        $pdo = $connection['pdo'];
        $driver = $connection['driver'];

        $schema = new \Api\System\Library\Database\SchemaManager();
        $migrations = new \Api\System\Library\Database\Migration\MigrationManager($schema);
        $before = $migrations->status($pdo, $driver);
        $executed = $migrations->migrateUp($pdo, $driver);
        $after = $migrations->status($pdo, $driver);

        return [
            'ok' => true,
            'driver' => $driver,
            'executed' => $executed,
            'pending_before' => $before['pending'],
            'pending_after' => $after['pending'],
            'applied_total' => $after['applied'],
        ];
    }
}
