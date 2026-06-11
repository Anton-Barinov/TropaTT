<?php
declare(strict_types=1);

namespace Api\Controller\Milestone;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\MilestoneService;
use Api\System\Library\Validation\Validator;

final class MilestoneController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $projectPublicIds = $input['project_public_ids'] ?? null;

        if ($projectPublicIds !== null && is_array($projectPublicIds) && $projectPublicIds !== []) {
            $projectPublicIds = array_values(array_filter(array_map('trim', $projectPublicIds)));

            $cache = $this->cacheApi();
            if ($cache !== null) {
                $cacheKey = 'list:' . $this->cacheUserId() . ':' . md5(json_encode($projectPublicIds));
                $byProject = $cache->remember('milestone', $cacheKey, 60, function () use ($projectPublicIds, $auth) {
                    /** @var MilestoneService $service */
                    $service = $this->container->get('service.milestone');
                    return $service->listByProjectIds($projectPublicIds, $auth['user']);
                });
            } else {
                /** @var MilestoneService $service */
                $service = $this->container->get('service.milestone');
                $byProject = $service->listByProjectIds($projectPublicIds, $auth['user']);
            }

            return $this->success('MILESTONE_LIST', $this->t('milestone/messages.list'), ['by_project' => $byProject]);
        }

        $projectPublicId = trim((string)($input['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'project_public_id' => [$this->t('milestone/messages.project_public_id_required')],
            ]);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . $projectPublicId;
            $items = $cache->remember('milestone', $cacheKey, 60, function () use ($projectPublicId, $auth) {
                /** @var MilestoneService $service */
                $service = $this->container->get('service.milestone');
                return $service->list($projectPublicId, $auth['user']);
            });
        } else {
            /** @var MilestoneService $service */
            $service = $this->container->get('service.milestone');
            $items = $service->list($projectPublicId, $auth['user']);
        }
        if ($items === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('milestone/messages.project_not_found'), 404);
        }

        return $this->success('MILESTONE_LIST', $this->t('milestone/messages.list'), ['items' => $items]);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $item = $service->get((string)$params['public_id'], $auth['user']);
        if (!$item) {
            return $this->error('MILESTONE_NOT_FOUND', $this->t('milestone/messages.not_found'), 404);
        }

        return $this->success('MILESTONE_DETAIL', $this->t('milestone/messages.detail'), ['milestone' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'project_public_id', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('milestone/messages.max_255'))
            ->date($input, 'due_at', $this->t('common/messages.invalid_date'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $item = $service->create($input, $auth['user']);
        if ($item === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('milestone/messages.project_not_found'), 404);
        }

        $this->invalidateCache('milestone');

        return $this->success('MILESTONE_CREATED', $this->t('milestone/messages.created'), ['milestone' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('milestone/messages.max_255'))
            ->date($input, 'due_at', $this->t('common/messages.invalid_date'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $item = $service->update((string)$params['public_id'], $input, $auth['user']);
        if ($item === null) {
            return $this->error('MILESTONE_NOT_FOUND', $this->t('milestone/messages.not_found'), 404);
        }
        if ($item === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('milestone/messages.project_not_found'), 404);
        }

        $this->invalidateCache('milestone');

        return $this->success('MILESTONE_UPDATED', $this->t('milestone/messages.updated'), ['milestone' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if ($ok === false) {
            return $this->error('MILESTONE_NOT_FOUND', $this->t('milestone/messages.not_found'), 404);
        }
        if ($ok === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('milestone/messages.project_not_found'), 404);
        }

        $this->invalidateCache('milestone');

        return $this->success('MILESTONE_DELETED', $this->t('milestone/messages.deleted'));
    }
}
