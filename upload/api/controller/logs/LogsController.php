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
            $service = new \Api\System\Library\Service\ServerErrorService($pdo);
            $input = $this->request()->allInput();
            $result = $service->list([
                'from' => $input['from'] ?? null,
                'to' => $input['to'] ?? null,
                'level' => $input['level'] ?? null,
                'user_public_id' => $input['user_public_id'] ?? null,
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
}
