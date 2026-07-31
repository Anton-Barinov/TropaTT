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
}
