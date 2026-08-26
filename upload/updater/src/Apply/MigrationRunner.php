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
            // The apply phase has just replaced the application files on disk.
            // PHP-FPM opcache may still serve the PRE-SWAP bytecode of the
            // migration classes (validate_timestamps revalidates only every
            // revalidate_freq seconds). Running migrations against the stale
            // MigrationManager is dangerous: its migration list is the OLD
            // one, so newly shipped migrations would be silently skipped and
            // the update would finish with new code over an old schema (500s
            // afterwards). Force opcache to recompile every migration source
            // file BEFORE the autoloader can pull in the old class.
            $this->invalidateMigrationOpcache();

            $connection = \Updater\Db\Connection::open($this->basePath);
            $pdo = $connection['pdo'];
            $driver = $connection['driver'];

            $schema = new \Api\System\Library\Database\SchemaManager();
            $migrations = new \Api\System\Library\Database\Migration\MigrationManager($schema);
            $before = $migrations->status($pdo, $driver);

            // The loaded MigrationManager is the class definition that PHP
            // compiled when the file was first included in this request. If
            // it lacks migrateUpLimit, it is the OLD manager. Two situations:
            //   a) genuinely old codebase on disk (updater bootstrap stage or
            //      an old install) -> its classic migrateUp() API is the only
            //      one available and is safe to use;
            //   b) the file on disk HAS migrateUpLimit but opcache served the
            //      stale pre-swap class (file swapped mid-request) -> the
            //      loaded manager knows only the OLD migration list, so
            //      migrateUp() would silently skip every new migration. That
            //      must never happen: fail loudly instead of finalizing the
            //      update over a half-migrated schema.
            $onDiskHasLimit = $this->onDiskMigrationManagerHasMigrateUpLimit();
            if (!method_exists($migrations, 'migrateUpLimit') && $onDiskHasLimit) {
                return [
                    'ok' => false,
                    'done' => false,
                    'error' => 'Migration engine is stale: the freshly deployed MigrationManager is not '
                        . 'loaded in this request (opcache served the pre-update class). Retry the update; '
                        . 'on retry the new code is loaded and migrations run normally.',
                    'executed' => [],
                    'pending_before' => $before['pending'],
                    'pending_after' => $before['pending'],
                    'applied_total' => $before['applied'],
                ];
            }

            $limit = $maxMigrations !== null && $maxMigrations > 0 ? $maxMigrations : PHP_INT_MAX;
            if (method_exists($migrations, 'migrateUpLimit')) {
                $executed = $migrations->migrateUpLimit($pdo, $driver, $limit);
            } else {
                // Old manager (updater bootstrap stage on a genuinely old
                // codebase): use its classic single-shot API.
                $executed = $migrations->migrateUp($pdo, $driver);
            }
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

    /**
     * Force opcache to recompile every PHP file under the migration directory
     * (best-effort: no-op when opcache is disabled or unavailable). Without
     * this, a just-applied MigrationManager.php can be served as stale
     * bytecode for up to opcache.revalidate_freq seconds, and the migration
     * step would run against the OLD migration list.
     */
    private function invalidateMigrationOpcache(): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }
        $dir = $this->basePath . '/api/system/library/database/migration';
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fileInfo) {
            if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.php')) {
                @opcache_invalidate($fileInfo->getPathname(), true);
            }
        }
    }

    /**
     * Whether the MigrationManager.php file ON DISK declares migrateUpLimit.
     * Used to distinguish a genuinely old codebase (safe migrateUp fallback)
     * from a mid-swap stale class (must not silently skip migrations).
     */
    private function onDiskMigrationManagerHasMigrateUpLimit(): bool
    {
        $file = $this->basePath . '/api/system/library/database/migration/MigrationManager.php';
        if (!is_file($file)) {
            return false;
        }
        $source = (string)@file_get_contents($file);
        return str_contains($source, 'migrateUpLimit');
    }
}
