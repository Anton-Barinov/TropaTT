<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Cron;

use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Support\Autoloader;
use Api\System\Library\Support\EnvLoader;
use Module\Crm\EcommerceGateway\Job\EcommerceWebhookJob;
use Module\Crm\EcommerceGateway\Repository\OutboxRepository;
use PDO;

/**
 * Scheduled cron runner for E-Commerce Gateway (E-COM-13 §1, §2, §3).
 *
 * Designed for shared hosting environments (Zero-Daemon):
 * - Works without persistent daemon processes (Supervisor/systemd) or Redis.
 * - Enforces execution time guards (< 20-25 seconds) to respect max_execution_time.
 * - Uses MySQL GET_LOCK to prevent overlapping executions from parallel crons.
 * - Batches Outbox event delivery with memory safety (unset).
 * - Periodically purges stale DLQ and old logs.
 *
 * ModuleCronScheduler instantiates handler classes with `new $handlerClass()`
 * and no arguments, so the PDO is resolved lazily and, when no connection was
 * injected, the handler bootstraps the core config itself. It must never rely
 * on a static container accessor: `Api\System\Library\Container` has no
 * `getInstance()`, and calling it made every cron run die with
 * "Call to undefined method", so no outbox webhook was ever delivered.
 */
final class EcommerceGatewayCronHandler
{
    private const LOCK_NAME = 'ecom_gateway_cron_lock';
    private const MAX_EXECUTION_TIME = 22.0; // Graceful exit before 25s/30s hosting limit

    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lazily resolves the database connection for cron runs.
     */
    private function pdo(): ?PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $apiRoot = $this->apiRoot();

            require_once $apiRoot . '/system/library/support/Autoloader.php';
            require_once $apiRoot . '/system/library/support/EnvLoader.php';

            $autoloader = new Autoloader($apiRoot);
            $autoloader->register();

            // A bare CLI scheduler run does not boot the module system, so the
            // module's own classes must be autoloadable here as well.
            self::registerModuleAutoloader();

            // Load the environment before reading the database config: the
            // credentials live in .env, and without them the connection falls
            // back to the driver defaults (root with no password).
            EnvLoader::loadFiles([
                dirname($apiRoot) . '/.env',
                $apiRoot . '/.env',
                dirname($apiRoot) . '/.env.local',
                $apiRoot . '/.env.local',
            ]);

            $config = new Config();
            $config->load($apiRoot . '/config/database.php', 'database');

            $this->pdo = (new ConnectionManager($config))->connect();
        } catch (\Throwable $e) {
            error_log('[EcommerceGatewayCronHandler] database bootstrap failed: ' . $e->getMessage());
            $this->pdo = null;
        }

        return $this->pdo;
    }

    /**
     * Registers the module namespace once per process.
     */
    private static function registerModuleAutoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $moduleApi = dirname(__DIR__);
        spl_autoload_register(static function (string $class) use ($moduleApi): void {
            $prefix = 'Module\\Crm\\EcommerceGateway\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $parts = explode('\\', substr($class, strlen($prefix)));
            $first = strtolower((string)array_shift($parts));
            $path = $moduleApi . '/' . $first . '/' . implode('/', $parts) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    /**
     * Locates the core `api/` directory from the installed module layout.
     *
     * The module lives in `<root>/modules/<vendor.name>/api/cron/`, so the core
     * API root is a *sibling* of `modules/` (`<root>/api`) and never an ancestor:
     * a plain upward walk that only probes `<dir>/system/...` misses it and the
     * handler then reports "Database connection not available" on every run.
     */
    private function apiRoot(): string
    {
        $dir = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            if (is_file($dir . '/system/library/support/Autoloader.php')) {
                return $dir;
            }

            if (is_file($dir . '/api/system/library/support/Autoloader.php')) {
                return $dir . '/api';
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new \RuntimeException('Unable to locate the TropaTT api/ directory');
    }

    /**
     * Main cron entry point invoked by ModuleCronScheduler or Web-Cron (OpsController::cronRunDue).
     *
     * @return array<string,mixed>
     */
    public function run(): array
    {
        return $this->dispatchQueue();
    }

    /**
     * Allowed method in ModuleCronScheduler HANDLER_METHOD_ALLOWLIST.
     *
     * @return array<string,mixed>
     */
    public function dispatchQueue(): array
    {
        $startTime = microtime(true);

        $pdo = $this->pdo();
        if (!$pdo instanceof PDO) {
            return [
                'success' => false,
                'error' => 'Database connection not available',
                'duration_ms' => 0,
            ];
        }

        $outboxRepo = new OutboxRepository($pdo);
        $lockAcquired = false;

        try {
            // 1. Acquire MySQL advisory lock (Zero-Daemon overlap protection)
            $lockAcquired = $outboxRepo->acquireLock(self::LOCK_NAME, 0);
            if (!$lockAcquired) {
                return [
                    'success' => true,
                    'status' => 'skipped',
                    'reason' => 'Another cron worker is currently running (advisory lock held)',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
            }

            // 2. Process pending outbox webhook deliveries
            $job = new EcommerceWebhookJob($outboxRepo);
            $batchResult = $job->processPending(20, self::MAX_EXECUTION_TIME);

            // 3. Maintenance: prune stale dead-letter queue records older than 30 days
            $archivedDlq = 0;
            $elapsed = microtime(true) - $startTime;
            if ($elapsed < self::MAX_EXECUTION_TIME) {
                $archivedDlq = $outboxRepo->archiveStaleDeadLetterEvents(30);
            }

            $endMemory = memory_get_peak_usage(true);
            $memoryUsedMb = round($endMemory / (1024 * 1024), 2);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'status' => 'completed',
                'outbox' => $batchResult,
                'archived_dlq_count' => $archivedDlq,
                'peak_memory_mb' => $memoryUsedMb,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            // A cron task must report a failure result, never abort the scheduler
            // run with an uncaught exception (for example while the module
            // migrations have not created the outbox table yet).
            error_log('[EcommerceGatewayCronHandler] dispatchQueue failed: ' . $e->getMessage());

            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } finally {
            // Always release the lock when this run acquired it
            if ($lockAcquired) {
                try {
                    $outboxRepo->releaseLock(self::LOCK_NAME);
                } catch (\Throwable $ignored) {
                }
            }
        }
    }
}
