<?php
declare(strict_types=1);

namespace Api\Controller\Admin;

use Api\Controller\Common\BaseController;
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
