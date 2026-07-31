<?php
declare(strict_types=1);

namespace Api\Controller\Dashboard;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\DashboardService;

final class DashboardController extends BaseController
{
    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DashboardService $service */
        $service = $this->container->get('service.dashboard');
        $summary = $service->summary($authUser['user']);

        return $this->success('DASHBOARD_SUMMARY', $this->t('dashboard/messages.summary'), ['summary' => $summary]);
    }
}
