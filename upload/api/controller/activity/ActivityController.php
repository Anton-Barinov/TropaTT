<?php
declare(strict_types=1);

namespace Api\Controller\Activity;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ActivityService;
use Api\System\Library\Service\TaskActivityService;
use Api\System\Library\Service\TaskService;

final class ActivityController extends BaseController
{
    public function feed(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        // TROPATTCRM-635: even with per-channel counts the cold path stays
        // ~1s; a 20s file-cache layer absorbs repeated dashboard/API polls
        // (same key shape as calendar/counterparty/idea lists).
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'feed:' . $this->cacheUserId() . ':' . $this->organizationContextCacheKey() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('activity', $cacheKey, 20, function () use ($input, $auth) {
                /** @var ActivityService $service */
                $service = $this->container->get('service.activity');
                return $service->feed($input, $auth['user']);
            });
        } else {
            /** @var ActivityService $service */
            $service = $this->container->get('service.activity');
            $result = $service->feed($this->request()->allInput(), $auth['user']);
        }

        return $this->success('ACTIVITY_FEED', $this->t('activity/messages.feed'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function entityHistory(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $entityType = trim((string)($params['entity_type'] ?? $this->request()->input('entity_type', '')));
        $publicId = trim((string)($params['public_id'] ?? $this->request()->input('public_id', '')));

        if ($entityType === '' || $publicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'entity' => [$this->t('activity/messages.entity_required')],
            ]);
        }

        if ($entityType === 'task') {
            /** @var TaskService $taskService */
            $taskService = $this->container->get('service.task');
            if ($taskService->get($publicId, $auth['user']) === null) {
                return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                    'task' => [$this->t('common/messages.task_not_found')],
                ]);
            }

            /** @var TaskActivityService $taskActivity */
            $taskActivity = $this->container->get('service.task_activity');
            $filters = $this->request()->allInput();
            $filters['fields_only'] = true;
            $result = $taskActivity->list($publicId, $filters, $auth['user']);
        } else {
            /** @var ActivityService $service */
            $service = $this->container->get('service.activity');
            $result = $service->entityHistory($entityType, $publicId, $this->request()->allInput(), $auth['user']);
        }

        return $this->success('ENTITY_HISTORY', $this->t('activity/messages.history'), [
            'entity_type' => $entityType,
            'entity_public_id' => $publicId,
            'items' => $result['items'],
        ], meta: $result['meta']);
    }
}
