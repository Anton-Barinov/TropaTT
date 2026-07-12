<?php
declare(strict_types=1);

namespace Api\Controller\Admin;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AdminWidgetService;

final class WidgetController extends BaseController
{
    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AdminWidgetService $service */
        $service = $this->container->get('service.admin_widget');

        return $this->success('ADMIN_WIDGETS_SUMMARY', $this->t('admin/messages.widgets_summary'), [
            'widgets' => $service->summary(),
        ]);
    }

    public function system(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var AdminWidgetService $service */
        $service = $this->container->get('service.admin_widget');

        return $this->success('ADMIN_WIDGETS_SYSTEM', $this->t('admin/messages.widgets_system'), [
            'widgets' => $service->system(),
        ]);
    }
}
