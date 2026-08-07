<?php
declare(strict_types=1);

namespace Api\Controller\Analytics;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AnalyticsService;

final class AnalyticsController extends BaseController
{
    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        // Analytics aggregates are authorization-sensitive. Do not serve a
        // stale file-cache entry after role, team membership, or hierarchy
        // changes; the service applies the current actor scope on every call.
        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        $summary = $service->summary($authUser['user']);

        return $this->success('ANALYTICS_SUMMARY', $this->t('analytics/messages.summary'), [
            'summary' => $summary,
        ]);
    }

    public function projects(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        // Keep the current authorization scope authoritative for every request.
        // In particular, a team or role change must not wait for a cache TTL.
        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        $items = $service->projects($authUser['user'], $this->request()->allInput());

        return $this->success('ANALYTICS_PROJECTS', $this->t('analytics/messages.projects'), [
            'items' => $items,
        ]);
    }

    public function users(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        // User workload is also object-scoped and must be recalculated after
        // hierarchy/team changes instead of being served from a stale cache.
        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        $items = $service->users($authUser['user'], $this->request()->allInput());

        return $this->success('ANALYTICS_USERS', $this->t('analytics/messages.users'), [
            'items' => $items,
        ]);
    }
}
