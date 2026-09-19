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
    private const WIDGETS = [
        'my_workload_efficiency',
        'tasks_actual_time',
        'my_kpi_scorecard',
        'assignee_department_load',
        'tasks_completion_velocity',
        'workload_efficiency_management',
        'streams_load_efficiency',
        'stream_detail_load_efficiency',
    ];

    public function show(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
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
        $actor = $this->organizationScopedActor((array)$authUser['user']);

        switch ($widget) {
            case 'tasks_actual_time':
                $data = $service->taskActualTime($actor, $input);
                break;
            case 'my_kpi_scorecard':
                $data = $service->personalScorecard($actor, $input);
                break;
            case 'assignee_department_load':
                $data = $service->assigneeDepartmentLoad($actor, $input);
                break;
            case 'tasks_completion_velocity':
                $data = $service->completionVelocity($actor, $input);
                break;
            case 'workload_efficiency_management':
                $data = $service->workloadManagement($actor, $input);
                break;
            case 'streams_load_efficiency':
                $data = $service->streamsOverview($actor, $input);
                break;
            case 'stream_detail_load_efficiency':
                // null means "the actor cannot see this project": answer 403
                // without revealing whether the project exists.
                $data = $service->streamDetail($actor, $input);
                if ($data === null) {
                    return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
                }
                break;
            default:
                $data = $service->personalLoad($actor, $input);
        }

        return $this->success('DASHBOARD_INSIGHTS', $this->t('dashboard/messages.insights'), [
            'widget' => $widget,
            'data' => $data,
        ]);
    }
}
