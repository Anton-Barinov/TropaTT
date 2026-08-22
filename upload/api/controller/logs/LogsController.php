<?php
declare(strict_types=1);

namespace Api\Controller\Logs;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\LogsService;

final class LogsController extends BaseController
{
    public function requestLogs(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->requestList($this->request()->allInput());

        return $this->success('REQUEST_LOG_LIST', $this->t('logs/messages.request_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function securityLogs(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->securityList($this->request()->allInput());

        return $this->success('SECURITY_LOG_LIST', $this->t('logs/messages.security_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function auditLogs(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($this->request()->allInput());

        return $this->success('AUDIT_LOG_LIST', $this->t('logs/messages.audit_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function frontendErrorChart(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->frontendErrorChart($this->request()->allInput());

        return $this->success('FRONTEND_ERROR_CHART', $this->t('logs/messages.frontend_error_chart'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function serverErrors(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        try {
            $pdo = $this->container->get('db.pdo');
            $service = \Api\System\Library\Service\ServerErrorService::getInstance($pdo);
            $input = $this->request()->allInput();
            $result = $service->list([
                'from' => $input['from'] ?? null,
                'to' => $input['to'] ?? null,
                'level' => $input['level'] ?? null,
                'user_public_id' => $input['user_public_id'] ?? null,
                'search' => $input['search'] ?? null,
                'limit' => (int)($input['limit'] ?? 100),
                'offset' => (int)($input['offset'] ?? 0),
            ]);

            return $this->success('SERVER_ERROR_LIST', '', [
                'items' => $result['items'],
            ], meta: ['total' => $result['total']]);
        } catch (\Throwable $e) {
            return $this->error('SERVER_ERRORS_UNAVAILABLE', $this->t('logs/messages.server_errors_unavailable', 'Server errors log is not available'), 500);
        }
    }

    public function moduleErrors(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        try {
            $pdo = $this->container->get('db.pdo');
            $handler = new \Api\System\Library\Module\ModuleErrorHandler($pdo);
            $handler->ensureTable($this->getDriver($pdo));
            $input = $this->request()->allInput();
            $result = $handler->list([
                'module_name' => $input['module_name'] ?? null,
                'from' => $input['from'] ?? null,
                'to' => $input['to'] ?? null,
                'search' => $input['search'] ?? null,
                'limit' => (int)($input['limit'] ?? 100),
                'offset' => (int)($input['offset'] ?? 0),
            ]);

            return $this->success('MODULE_ERROR_LIST', '', [
                'items' => $result['items'],
            ], meta: ['total' => $result['total']]);
        } catch (\Throwable $e) {
            return $this->error('MODULE_ERRORS_UNAVAILABLE', $this->t('logs/messages.module_errors_unavailable', 'Module errors log is not available'), 500);
        }
    }

    /**
     * Unified endpoint: all error sources merged and sorted by time.
     */
    public function allErrors(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        $input = $this->request()->allInput();
        $limit = min((int)($input['limit'] ?? 100), 500);
        $offset = max((int)($input['offset'] ?? 0), 0);
        $from = $input['from'] ?? null;
        $to = $input['to'] ?? null;
        $level = $input['level'] ?? null;
        $search = $input['search'] ?? null;
        $userPublicId = $input['user_public_id'] ?? null;
        // Optional source filter: server_error | frontend_error | module_error.
        // When set, only the requested source is queried and counted.
        $sourceType = in_array($input['source_type'] ?? null, ['server_error', 'frontend_error', 'module_error'], true)
            ? (string)$input['source_type']
            : null;

        $items = [];
        $total = 0;
        $counts = ['server_error' => 0, 'frontend_error' => 0, 'module_error' => 0];

        try {
            $pdo = $this->container->get('db.pdo');

            // 1. Server errors (PHP fatal/exception/error)
            if ($sourceType === null || $sourceType === 'server_error') {
                try {
                    $serverService = \Api\System\Library\Service\ServerErrorService::getInstance($pdo);
                    $serverResult = $serverService->list([
                        'from' => $from, 'to' => $to, 'level' => $level,
                        'user_public_id' => $userPublicId, 'search' => $search,
                        'limit' => 500, 'offset' => 0,
                    ]);
                    foreach ($serverResult['items'] as $item) {
                        $item['_source'] = 'server_error';
                        $item['_sort_time'] = $item['created_at'] ?? '';
                        $items[] = $item;
                    }
                    $total += (int)$serverResult['total'];
                    $counts['server_error'] = (int)$serverResult['total'];
                } catch (\Throwable $e) {
                    // Server errors unavailable, continue
                }
            }

            // 2. Frontend errors from security_logs (js_error, frontend_api_error, csp_violation)
            if ($sourceType === null || $sourceType === 'frontend_error') {
                try {
                    $logsRepo = $this->container->get('service.logs');
                    if ($logsRepo instanceof LogsService) {
                        $secResult = $logsRepo->securityList([
                            'from' => $from, 'to' => $to,
                            'limit' => 500, 'offset' => 0,
                        ]);
                        foreach ($secResult['items'] as $item) {
                            $eventType = $item['event_type'] ?? '';
                            if (!in_array($eventType, ['frontend_api_error', 'frontend_js_error', 'frontend_csp_violation'], true)) {
                                continue;
                            }
                            $item['_source'] = 'frontend_error';
                            $item['_sort_time'] = $item['created_at'] ?? '';
                            $item['_level'] = $eventType === 'frontend_csp_violation' ? 'warning' : 'error';
                            $items[] = $item;
                            $counts['frontend_error']++;
                            $total++;
                        }
                    }
                } catch (\Throwable $e) {
                    // Frontend errors unavailable, continue
                }
            }

            // 3. Module errors
            if ($sourceType === null || $sourceType === 'module_error') {
                try {
                    $handler = new \Api\System\Library\Module\ModuleErrorHandler($pdo);
                    $handler->ensureTable($this->getDriver($pdo));
                    $moduleResult = $handler->list([
                        'from' => $from, 'to' => $to, 'search' => $search,
                        'limit' => 500, 'offset' => 0,
                    ]);
                    foreach ($moduleResult['items'] as $item) {
                        $item['_source'] = 'module_error';
                        $item['_sort_time'] = $item['created_at'] ?? '';
                        $item['_level'] = 'error';
                        $items[] = $item;
                        $counts['module_error']++;
                        $total++;
                    }
                } catch (\Throwable $e) {
                    // Module errors unavailable, continue
                }
            }

            // Sort by time descending
            usort($items, static fn(array $a, array $b): int => strcmp(
                (string)($b['_sort_time'] ?? ''),
                (string)($a['_sort_time'] ?? '')
            ));

            // Apply offset/limit
            $sliced = array_slice($items, $offset, $limit);

            return $this->success('ALL_ERRORS_LIST', '', [
                'items' => $sliced,
            ], meta: [
                'total' => $total,
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            return $this->error('ALL_ERRORS_UNAVAILABLE', $this->t('logs/messages.all_errors_unavailable', 'Unified error log is not available'), 500);
        }
    }

    private function getDriver(\PDO $pdo): string
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        return is_string($driver) ? $driver : 'mysql';
    }
}
