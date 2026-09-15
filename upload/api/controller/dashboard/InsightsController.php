<?php
declare(strict_types=1);

namespace Api\Controller\Dashboard;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AnalyticsService;

/**
 * Metric payloads for the dashboard "insights" widgets.
 *
 * Every widget renders from this one endpoint (`?widget=<key>&period=<7|30|90>`)
 * so the catalog, the JS definitions and the documented route stay in sync; the
 * per-widget shape is decided by the service, which also applies the actor's
 * visibility scope (root sees everything, everyone else only their own scope).
 */
final class InsightsController extends BaseController
{
    private const WIDGETS = ['my_workload_efficiency', 'tasks_actual_time'];

    public function show(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $widget = strtolower(trim((string)($input['widget'] ?? '')));

        if (!in_array($widget, self::WIDGETS, true)) {
            return $this->error(
                'VALIDATION_ERROR',
                $this->t('dashboard/messages.insights_unknown_widget'),
                422
            );
        }

        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        $actor = $authUser['user'];

        $data = $widget === 'tasks_actual_time'
            ? $service->taskActualTime($actor, $input)
            : $service->personalLoad($actor, $input);

        return $this->success('DASHBOARD_INSIGHTS', $this->t('dashboard/messages.insights'), [
            'widget' => $widget,
            'data' => $data,
        ]);
    }
}
