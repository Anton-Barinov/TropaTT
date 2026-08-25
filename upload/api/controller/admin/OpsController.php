<?php
declare(strict_types=1);

namespace Api\Controller\Admin;

use Api\Controller\Common\BaseController;
use Api\System\Library\Module\ModuleCronScheduler;
use Api\System\Library\Service\ExportService;
use Api\System\Library\Service\ImportService;
use Api\System\Library\Service\NotificationPushService;
use Api\System\Library\Service\OpsService;
use Api\System\Library\Service\WebhookService;

final class OpsController extends BaseController
{
    public function system(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var OpsService $service */
        $service = $this->container->get('service.ops');
        $payload = $service->system();

        return $this->success('OPS_SYSTEM', $this->t('admin/messages.ops_system'), $payload);
    }

    public function runJobs(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        $input = $this->request()->allInput();
        $limit = max(1, min(100, (int)($input['limit'] ?? 10)));

        /** @var ImportService $imports */
        $imports = $this->container->get('service.import');
        /** @var ExportService $exports */
        $exports = $this->container->get('service.export');
        /** @var NotificationPushService $push */
        $push = $this->container->get('service.notification_push');
        /** @var WebhookService $webhooks */
        $webhooks = $this->container->get('service.webhook');

        $importResult = $imports->runQueued($limit);
        $exportResult = $exports->runQueued($limit);
        $pushResult = $push->runQueued($limit);
        $webhookResult = $webhooks->runQueued($limit);

        return $this->success('OPS_JOBS_RUN', $this->t('admin/messages.ops_system'), [
            'import' => $importResult,
            'export' => $exportResult,
            'push' => $pushResult,
            'webhook' => $webhookResult,
            'limit' => $limit,
            'generated_at' => gmdate('c'),
        ]);
    }

    /**
     * Maximum age of the last web-cron heartbeat before the cron is considered
     * stale ("silent"). Overridable via the 'cron.stale_threshold_minutes'
     * system setting; falls back to 60 minutes.
     */
    private const CRON_STALE_THRESHOLD_MINUTES_DEFAULT = 60;

    public function cronTasks(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        try {
            $pdo = $this->container->get('db.pdo');
            $scheduler = new ModuleCronScheduler($pdo);

            $dbTasks = $scheduler->getTasks();

            // Drop internal-only columns before returning.
            $tasks = array_map(static function (array $row): array {
                unset($row['handler_class'], $row['handler_method']);
                return $row;
            }, $dbTasks);

            // Read the web-cron heartbeat.
            $heartbeat = null;
            try {
                $stmt = $pdo->prepare("SELECT value FROM settings WHERE scope = 'system' AND name = 'cron.last_web_run_at' ORDER BY updated_at DESC LIMIT 1");
                $stmt->execute();
                $raw = $stmt->fetchColumn();
                if ($raw !== false && is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $heartbeat = $decoded;
                    }
                }
            } catch (\Throwable $e) {
                // Settings table may not exist yet on a fresh install.
            }

            // Determine stale threshold from settings, fall back to default.
            $thresholdMinutes = self::CRON_STALE_THRESHOLD_MINUTES_DEFAULT;
            try {
                $tStmt = $pdo->prepare("SELECT value FROM settings WHERE scope = 'system' AND name = 'cron.stale_threshold_minutes' ORDER BY updated_at DESC LIMIT 1");
                $tStmt->execute();
                $tRaw = $tStmt->fetchColumn();
                if ($tRaw !== false && is_string($tRaw)) {
                    $tDecoded = json_decode($tRaw, true);
                    if (is_int($tDecoded) && $tDecoded > 0) {
                        $thresholdMinutes = $tDecoded;
                    }
                }
            } catch (\Throwable $e) {
                // Use default.
            }

            // Compute the stale flag.
            $stale = true;
            if ($heartbeat !== null && isset($heartbeat['ts_utc'])) {
                $lastTs = (int)$heartbeat['ts_utc'];
                $elapsed = time() - $lastTs;
                $stale = $elapsed > ($thresholdMinutes * 60);
            }

            return $this->success('OPS_CRON_TASKS', $this->t('admin/messages.ops_system'), [
                'tasks' => $tasks,
                'cron_heartbeat' => $heartbeat,
                'stale' => $stale,
                'stale_threshold_minutes' => $thresholdMinutes,
            ]);
        } catch (\Throwable $e) {
            error_log('[OpsController::cronTasks] ' . $e->getMessage());
            // Tables may be missing on a fresh install — return empty, not 500.
            return $this->success('OPS_CRON_TASKS', $this->t('admin/messages.ops_system'), [
                'tasks' => [],
                'cron_heartbeat' => null,
                'stale' => true,
                'stale_threshold_minutes' => self::CRON_STALE_THRESHOLD_MINUTES_DEFAULT,
            ]);
        }
    }

    public function cronExecutions(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        try {
            $input = $this->request()->allInput();
            $module = !empty($input['module']) ? trim((string)$input['module']) : null;
            $task = !empty($input['task']) ? trim((string)$input['task']) : null;
            $status = !empty($input['status']) ? trim((string)$input['status']) : null;
            $limit = max(1, min(200, (int)($input['limit'] ?? 50)));

            $pdo = $this->container->get('db.pdo');

            $where = [];
            $params = [];

            if ($module !== null) {
                $where[] = 'module_name = :module';
                $params['module'] = $module;
            }
            if ($task !== null) {
                $where[] = 'task_name = :task';
                $params['task'] = $task;
            }
            if ($status !== null) {
                $where[] = 'status = :status';
                $params['status'] = $status;
            }

            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $params['limit'] = $limit;

            $stmt = $pdo->prepare("SELECT id, module_name, task_name, started_at, finished_at, duration_ms, status, output, error_message, pid, created_at FROM module_task_executions {$whereClause} ORDER BY started_at DESC LIMIT :limit");
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, $key === 'limit' ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $executions = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success('OPS_CRON_EXECUTIONS', $this->t('admin/messages.ops_system'), [
                'executions' => $executions,
            ]);
        } catch (\Throwable $e) {
            error_log('[OpsController::cronExecutions] ' . $e->getMessage());
            return $this->success('OPS_CRON_EXECUTIONS', $this->t('admin/messages.ops_system'), [
                'executions' => [],
            ]);
        }
    }

    public function cronRunDue(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        try {
            $pdo = $this->container->get('db.pdo');
            $scheduler = new ModuleCronScheduler($pdo);

            // Ensure tables + indexes exist (idempotent).
            $dbConfig = $this->container->get('config')?->get('database.connections.' . ($this->container->get('config')?->get('database.default') ?: 'sqlite'));
            $driver = (string)($dbConfig['driver'] ?? 'sqlite');
            $scheduler->ensureTables($driver);

            $result = $scheduler->run();

            return $this->success('OPS_CRON_RUN_DUE', $this->t('admin/messages.ops_system'), [
                'executed' => (int)($result['executed'] ?? 0),
                'failed' => (int)($result['failed'] ?? 0),
                'results' => $result['results'] ?? [],
                'generated_at' => gmdate('c'),
            ]);
        } catch (\Throwable $e) {
            error_log('[OpsController::cronRunDue] ' . $e->getMessage());
            return $this->error('OPS_CRON_RUN_FAILED', $e->getMessage(), 500);
        }
    }

    public function metrics(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var OpsService $service */
        $service = $this->container->get('service.ops');
        $payload = $service->metrics();

        return $this->success('OPS_METRICS', $this->t('admin/messages.ops_system'), $payload);
    }
}
