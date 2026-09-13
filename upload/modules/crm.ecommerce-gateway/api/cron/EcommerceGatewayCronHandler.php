<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Cron;

use Api\System\Library\Container;
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
 */
final class EcommerceGatewayCronHandler
{
    private const LOCK_NAME = 'ecom_gateway_cron_lock';
    private const MAX_EXECUTION_TIME = 22.0; // Graceful exit before 25s/30s hosting limit

    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } elseif (class_exists(Container::class) && Container::getInstance()->has('db.pdo')) {
            $this->pdo = Container::getInstance()->get('db.pdo');
        }
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

        if ($this->pdo === null) {
            return [
                'success' => false,
                'error' => 'Database connection not available',
                'duration_ms' => 0,
            ];
        }

        $outboxRepo = new OutboxRepository($this->pdo);

        // 1. Acquire MySQL advisory lock (Zero-Daemon overlap protection)
        if (!$outboxRepo->acquireLock(self::LOCK_NAME, 0)) {
            return [
                'success' => true,
                'status' => 'skipped',
                'reason' => 'Another cron worker is currently running (advisory lock held)',
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        try {
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
        } finally {
            // Always release lock
            $outboxRepo->releaseLock(self::LOCK_NAME);
        }
    }
}
