<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use Api\System\Library\Database\IndexHelper;

final class ModuleCronScheduler
{
    private PDO $pdo;
    private CronExpressionParser $parser;
    private string $tasksTable = 'module_scheduled_tasks';
    private string $executionsTable = 'module_task_executions';

    /**
     * Trusted namespace prefixes for cron handler classes. Only classes under
     * these namespaces may be instantiated from the scheduled-tasks table.
     * Modules register their handlers through ServiceProvider hooks during
     * boot; a hand-written row in the DB with an arbitrary class will fail.
     */
    private const HANDLER_NS_ALLOWLIST = [
        'Api\\',
        'Module\\',
    ];

    /**
     * Allowed method names for cron handlers.
     */
    private const HANDLER_METHOD_ALLOWLIST = [
        'run', 'execute', 'handle', 'process',
        'freshnessScan', 'draftsCleanup', 'versionsCleanup', 'reindexSearch',
        'captureDaily', 'autoClosePeriods', 'dispatchQueue',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->parser = new CronExpressionParser();
    }

    /**
     * Run all due tasks for all active modules.
     * @return array{executed: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public function run(): array
    {
        $result = ['executed' => 0, 'failed' => 0, 'results' => []];
        $now = new \DateTime();

        $tasks = $this->getDueTasks($now);
        foreach ($tasks as $task) {
            try {
                $taskResult = $this->executeTask(
                    (int)$task['id'],
                    (string)$task['module_name'],
                    (string)$task['task_name'],
                    (string)$task['handler_class'],
                    (string)$task['handler_method'],
                    (int)$task['timeout'],
                    (string)$task['schedule']
                );
                $result['results'][] = $taskResult;

                if ($taskResult['status'] === 'success') {
                    $result['executed']++;
                } else {
                    $result['failed']++;
                }

                // updateNextRun + last_status already done inside executeTask
            } catch (\Throwable $e) {
                error_log('[ModuleCronScheduler::run] Fatal error running task ' . ($task['task_name'] ?? '?') . ': ' . $e->getMessage());
                $result['results'][] = [
                    'status' => 'failed',
                    'module' => (string)($task['module_name'] ?? ''),
                    'task' => (string)($task['task_name'] ?? ''),
                    'duration_ms' => 0,
                    'error' => $e->getMessage(),
                ];
                $result['failed']++;
                // Still update next run so the broken task doesn't block the queue.
                try {
                    $this->updateNextRun((int)$task['id'], (string)$task['schedule'], 'failed', mb_substr($e->getMessage(), 0, 500));
                } catch (\Throwable $ignored) {
                }
            }
        }

        return $result;
    }

    /**
     * Register a scheduled task from a module's ServiceProvider.
     *
     * Idempotent: exactly one row per (module_name, task_name). Older builds
     * called this with a plain INSERT and no unique constraint on every API
     * request, so module_scheduled_tasks accumulated one duplicate row per
     * request (hundreds of thousands of rows on active installs). We now look
     * the task up first, update it in place when it exists, and drop any
     * leftover duplicates so the table converges to one row per task.
     */
    public function registerTask(string $moduleName, ScheduledTask $task): void
    {
        try {
            $now = gmdate('Y-m-d H:i:s');

            $existing = $this->pdo->prepare("SELECT id FROM {$this->tasksTable} WHERE module_name = :module AND task_name = :task LIMIT 1");
            $existing->execute(['module' => $moduleName, 'task' => $task->name]);
            $existingId = $existing->fetchColumn();

            if ($existingId !== false) {
                $stmt = $this->pdo->prepare("SELECT schedule, next_run_at FROM {$this->tasksTable} WHERE id = :id");
                $stmt->execute(['id' => $existingId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                // Re-registration happens on every API request (App::initModuleSystem).
                // Recomputing next_run_at here would keep pushing the run date forward
                // (e.g. now+1min for '* * * * *'), so a task could never become due.
                // Only recompute when the schedule changed or the stored date is null/
                // stale; otherwise keep the scheduler's own advancement (updateNextRun).
                $nextRun = null;
                if ($row === false || $row['schedule'] !== $task->schedule || empty($row['next_run_at'])) {
                    $nextRun = $this->parser->getNextRunDate($task->schedule);
                }

                $stmt = $this->pdo->prepare("UPDATE {$this->tasksTable} SET description = :desc, schedule = :schedule, handler_class = :class, handler_method = :method, enabled = :enabled, timeout = :timeout, overlap_allowed = :overlap, next_run_at = COALESCE(:next, next_run_at), updated_at = :updated WHERE id = :id");
                $stmt->execute([
                    'desc' => $task->description,
                    'schedule' => $task->schedule,
                    'class' => $task->handler[0],
                    'method' => $task->handler[1],
                    'enabled' => $task->enabled ? 1 : 0,
                    'timeout' => $task->timeout,
                    'overlap' => $task->overlapAllowed ? 1 : 0,
                    'next' => $nextRun?->format('Y-m-d H:i:s'),
                    'updated' => $now,
                    'id' => $existingId,
                ]);

                // Collapse duplicates created by older non-idempotent builds.
                $dup = $this->pdo->prepare("DELETE FROM {$this->tasksTable} WHERE module_name = :module AND task_name = :task AND id <> :id");
                $dup->execute(['module' => $moduleName, 'task' => $task->name, 'id' => $existingId]);
                return;
            }

            $nextRun = $this->parser->getNextRunDate($task->schedule);

            $stmt = $this->pdo->prepare("INSERT INTO {$this->tasksTable} (module_name, task_name, description, schedule, handler_class, handler_method, enabled, timeout, overlap_allowed, last_run_at, next_run_at, created_at, updated_at) VALUES (:module, :task, :desc, :schedule, :class, :method, :enabled, :timeout, :overlap, NULL, :next, :created_at, :updated_at)");
            $stmt->execute([
                'module' => $moduleName,
                'task' => $task->name,
                'desc' => $task->description,
                'schedule' => $task->schedule,
                'class' => $task->handler[0],
                'method' => $task->handler[1],
                'enabled' => $task->enabled ? 1 : 0,
                'timeout' => $task->timeout,
                'overlap' => $task->overlapAllowed ? 1 : 0,
                'next' => $nextRun->format('Y-m-d H:i:s'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            if ($code !== '23000' && !str_contains($e->getMessage(), 'Duplicate') && !str_contains($e->getMessage(), 'UNIQUE')) {
                throw $e;
            }
            // Unique-index race: another request registered the same task just
            // before us — the row already exists, nothing further to do.
        }
    }

    public function disableAllForModule(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tasksTable} SET enabled = 0 WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);
    }

    public function deleteAllForModule(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tasksTable} WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);

        $stmt = $this->pdo->prepare("DELETE FROM {$this->executionsTable} WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getTasks(?string $moduleName = null): array
    {
        if ($moduleName !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tasksTable} WHERE module_name = :module ORDER BY next_run_at ASC");
            $stmt->execute(['module' => $moduleName]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tasksTable} ORDER BY next_run_at ASC");
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getExecutionHistory(string $moduleName, string $taskName, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->executionsTable} WHERE module_name = :module AND task_name = :task ORDER BY id DESC LIMIT :limit");
        $stmt->execute(['module' => $moduleName, 'task' => $taskName, 'limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cleanupOldExecutions(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stmt = $this->pdo->prepare("DELETE FROM {$this->executionsTable} WHERE created_at < :cutoff");
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    public function ensureTables(string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tasksTable} (id {$id}, module_name {$keyType} NOT NULL, task_name {$keyType} NOT NULL, description {$keyType}, schedule {$keyType} NOT NULL, handler_class {$keyType} NOT NULL, handler_method {$keyType} NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, timeout INTEGER NOT NULL DEFAULT 300, overlap_allowed INTEGER NOT NULL DEFAULT 0, last_run_at {$dt}, next_run_at {$dt} NOT NULL, last_status {$keyType}, last_error {$keyType}, created_at {$dt} NOT NULL {$nowDefault}, updated_at {$dt} NOT NULL {$nowDefault})");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->executionsTable} (id {$id}, module_name {$keyType} NOT NULL, task_name {$keyType} NOT NULL, started_at {$dt} NOT NULL {$nowDefault}, finished_at {$dt}, duration_ms INTEGER, status {$keyType} NOT NULL, output {$keyType}, error_message {$keyType}, error_trace {$keyType}, memory_peak_mb INTEGER, pid INTEGER, created_at {$dt} NOT NULL {$nowDefault})");

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tasksTable, 'idx_scheduled_tasks_next', 'next_run_at, enabled');
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tasksTable, 'idx_scheduled_tasks_module', 'module_name');
            IndexHelper::createIndexIfNotExists($this->pdo, $this->executionsTable, 'idx_task_executions_module', 'module_name, task_name, started_at');

            // Idempotency guarantee for registerTask(): one row per
            // (module_name, task_name). Duplicates left by older builds must
            // be collapsed first or the unique index cannot be created.
            $this->dedupeScheduledTasks();
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tasksTable, 'uq_scheduled_tasks_module_task', 'module_name, task_name', true);
        } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::ensureTables] index creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete duplicate scheduled-task rows, keeping only the oldest row per
     * (module_name, task_name). No-op once the table is already unique.
     */
    private function dedupeScheduledTasks(): void
    {
        try {
            // Portable duplicate detection: COUNT(DISTINCT a, b) is not
            // supported on SQLite, so count groups with more than one row.
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM (SELECT 1 FROM {$this->tasksTable} GROUP BY module_name, task_name HAVING COUNT(*) > 1) AS dupes");
            $duplicates = (int)$stmt->fetchColumn();
            if ($duplicates <= 0) {
                return;
            }

            $keep = $this->pdo->query("SELECT MIN(id) FROM {$this->tasksTable} GROUP BY module_name, task_name");
            $ids = $keep->fetchAll(PDO::FETCH_COLUMN);
            if ($ids === []) {
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $del = $this->pdo->prepare("DELETE FROM {$this->tasksTable} WHERE id NOT IN ({$placeholders})");
            $del->execute($ids);
        } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::dedupeScheduledTasks] ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDueTasks(\DateTime $now): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tasksTable} WHERE enabled = 1 AND next_run_at <= :now ORDER BY next_run_at ASC LIMIT 50");
            $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::getDueTasks] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array{status: string, module: string, task: string, duration_ms: float, error: string|null}
     */
    private function executeTask(int $taskId, string $moduleName, string $taskName, string $handlerClass, string $handlerMethod, int $timeout, string $schedule): array
    {
        $startTime = microtime(true);
        $pid = getmypid();
        $now = date('Y-m-d H:i:s');

        $overlapAllowed = $this->isOverlapAllowed($taskId);

        if (!$overlapAllowed) {
            $running = $this->hasRunningExecution($moduleName, $taskName, $timeout);
            if ($running) {
                try {
                    $this->updateNextRun($taskId, $schedule, 'skipped', 'Task already running (overlap disallowed)');
                } catch (\Throwable $e) {
                    error_log('[ModuleCronScheduler::executeTask] Failed to update task status: ' . $e->getMessage());
                }
                return ['status' => 'skipped', 'module' => $moduleName, 'task' => $taskName, 'duration_ms' => 0, 'error' => 'Task already running (overlap disallowed)'];
            }
        }

        try {
            $timeoutSec = $timeout > 0 ? $timeout : 300;

            $stmt = $this->pdo->prepare("INSERT INTO {$this->executionsTable} (module_name, task_name, started_at, status, pid, duration_ms) VALUES (:module, :task, :now, 'running', :pid, 0)");
            $stmt->execute(['module' => $moduleName, 'task' => $taskName, 'now' => $now, 'pid' => $pid]);
            $executionId = (int)$this->pdo->lastInsertId();

            // H-6: validate handler class and method against allowlists.
            $this->validateHandler($handlerClass, $handlerMethod);

            if (!class_exists($handlerClass)) {
                throw new \RuntimeException("Handler class not found: {$handlerClass}");
            }

            $handler = new $handlerClass();
            if (!method_exists($handler, $handlerMethod)) {
                throw new \RuntimeException("Handler method not found: {$handlerClass}::{$handlerMethod}");
            }

            $output = $handler->{$handlerMethod}();
            $duration = (microtime(true) - $startTime) * 1000;

            $finishNow = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("UPDATE {$this->executionsTable} SET finished_at = :finished, duration_ms = :duration, status = 'success', output = :output WHERE id = :id");
            $stmt->execute([
                'finished' => $finishNow,
                'duration' => (int)$duration,
                'output' => is_string($output) ? $output : json_encode($output, JSON_UNESCAPED_UNICODE),
                'id' => $executionId,
            ]);

            try {
                $this->updateNextRun($taskId, $schedule, 'success', null);
            } catch (\Throwable $e) {
                error_log('[ModuleCronScheduler::executeTask] Failed to update task status: ' . $e->getMessage());
            }

            return ['status' => 'success', 'module' => $moduleName, 'task' => $taskName, 'duration_ms' => $duration, 'error' => null];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $finishNow = date('Y-m-d H:i:s');

            try {
                $stmt = $this->pdo->prepare("UPDATE {$this->executionsTable} SET finished_at = :finished, duration_ms = :duration, status = 'failed', error_message = :error, error_trace = :trace WHERE id = :id");
                $stmt->execute([
                    'finished' => $finishNow,
                    'duration' => (int)$duration,
                    'error' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 65535),
                    'id' => $executionId ?? 0,
                ]);
            } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::recordExecution] ' . $e->getMessage());
            }

            $truncatedError = mb_substr($e->getMessage(), 0, 500);
            try {
                $this->updateNextRun($taskId, $schedule, 'failed', $truncatedError);
            } catch (\Throwable $e) {
                error_log('[ModuleCronScheduler::executeTask] Failed to update task status: ' . $e->getMessage());
            }

            return ['status' => 'failed', 'module' => $moduleName, 'task' => $taskName, 'duration_ms' => $duration, 'error' => $e->getMessage()];
        }
    }

    private function updateNextRun(int $taskId, string $schedule, ?string $lastStatus = null, ?string $lastError = null): void
    {
        try {
            $nextRun = $this->parser->getNextRunDate($schedule);
            $now = date('Y-m-d H:i:s');

            if ($lastStatus !== null) {
                $stmt = $this->pdo->prepare("UPDATE {$this->tasksTable} SET last_run_at = :now, next_run_at = :next, last_status = :status, last_error = :error WHERE id = :id");
                $stmt->execute(['now' => $now, 'next' => $nextRun->format('Y-m-d H:i:s'), 'status' => $lastStatus, 'error' => $lastError, 'id' => $taskId]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE {$this->tasksTable} SET last_run_at = :now, next_run_at = :next WHERE id = :id");
                $stmt->execute(['now' => $now, 'next' => $nextRun->format('Y-m-d H:i:s'), 'id' => $taskId]);
            }
        } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::updateNextRun] ' . $e->getMessage());
        }
    }

    private function isOverlapAllowed(int $taskId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT overlap_allowed FROM {$this->tasksTable} WHERE id = :id");
            $stmt->execute(['id' => $taskId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return !empty($row['overlap_allowed']);
        } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::isOverlapAllowed] ' . $e->getMessage());
            return false;
        }
    }

    private function hasRunningExecution(string $moduleName, string $taskName, int $timeout = 300): bool
    {
        try {
            // A run that crashed (PHP fatal, server restart, host OOM-kill) leaves a
            // 'running' row forever, which would block the task permanently via the
            // overlap check. Treat executions older than max(timeout, 15 min) as
            // stale: the process is gone, so the task may run again.
            $grace = max($timeout > 0 ? $timeout : 300, 900);
            $cutoff = date('Y-m-d H:i:s', time() - $grace);

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->executionsTable} WHERE module_name = :module AND task_name = :task AND status = 'running' AND started_at >= :cutoff");
            $stmt->execute(['module' => $moduleName, 'task' => $taskName, 'cutoff' => $cutoff]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            error_log('[ModuleCronScheduler::hasRunningExecution] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate that a cron handler class and method are within the trust boundary.
     * Fail-closed: rejects classes outside the allowlisted namespace prefixes
     * and methods not in the allowlist. This prevents arbitrary class
     * instantiation from rows in the scheduled-tasks table (H-6).
     */
    private function validateHandler(string $handlerClass, string $handlerMethod): void
    {
        $allowed = false;
        foreach (self::HANDLER_NS_ALLOWLIST as $prefix) {
            if (str_starts_with($handlerClass, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new \RuntimeException("Handler class '{$handlerClass}' is not in a trusted namespace");
        }

        if (!in_array($handlerMethod, self::HANDLER_METHOD_ALLOWLIST, true)) {
            throw new \RuntimeException("Handler method '{$handlerMethod}' is not allowed for cron tasks");
        }
    }
}
