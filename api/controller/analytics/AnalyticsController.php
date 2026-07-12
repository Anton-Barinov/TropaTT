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

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'summary:' . $this->cacheUserId();
            $summary = $cache->remember('analytics', $cacheKey, 60, function () use ($authUser) {
                /** @var AnalyticsService $service */
                $service = $this->container->get('service.analytics');
                return $service->summary($authUser['user']);
            });
        } else {
            /** @var AnalyticsService $service */
            $service = $this->container->get('service.analytics');
            $summary = $service->summary($authUser['user']);
        }

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

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'projects:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $items = $cache->remember('analytics', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var AnalyticsService $service */
                $service = $this->container->get('service.analytics');
                return $service->projects($authUser['user'], $input);
            });
        } else {
            /** @var AnalyticsService $service */
            $service = $this->container->get('service.analytics');
            $items = $service->projects($authUser['user'], $this->request()->allInput());
        }

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

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'users:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $items = $cache->remember('analytics', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var AnalyticsService $service */
                $service = $this->container->get('service.analytics');
                return $service->users($authUser['user'], $input);
            });
        } else {
            /** @var AnalyticsService $service */
            $service = $this->container->get('service.analytics');
            $items = $service->users($authUser['user'], $this->request()->allInput());
        }

        return $this->success('ANALYTICS_USERS', $this->t('analytics/messages.users'), [
            'items' => $items,
        ]);
    }
}
