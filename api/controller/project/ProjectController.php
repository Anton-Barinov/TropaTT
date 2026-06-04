<?php
declare(strict_types=1);

namespace Api\Controller\Project;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\GanttService;
use Api\System\Library\Service\ProjectService;
use Api\System\Library\Service\ProjectSummaryService;
use Api\System\Library\Validation\Validator;

final class ProjectController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $errors = [];
        if (!empty($input['updated_since']) && strtotime((string)$input['updated_since']) === false) {
            $errors['updated_since'][] = $this->t('common/messages.invalid_date');
        }
        if (!empty($input['cursor']) && strlen((string)$input['cursor']) > 1024) {
            $errors['cursor'][] = $this->t('project/messages.invalid_cursor');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        $result = $service->list($input, $authUser['user']);

        return $this->success('PROJECT_LIST', $this->t('project/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        $input = $this->request()->allInput();

        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('project/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($service, $input, $authUser): \Api\System\Library\Http\JsonResponse {
            $item = $service->create($input, $authUser['user']);

            return $this->success('PROJECT_CREATED', $this->t('project/messages.created'), [
                'project' => $item,
            ], 201, [
                'row_version' => (int)($item['row_version'] ?? 1),
            ]);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        $item = $service->get((string)$params['public_id'], $authUser['user']);

        if (!$item) {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_DETAIL', $this->t('project/messages.detail'), [
            'project' => $item,
        ], meta: [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('project/messages.max_255'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        if ($item === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('project/messages.row_version_conflict'), 409);
        }
        if (!$item || !is_array($item)) {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_UPDATED', $this->t('project/messages.updated'), [
            'project' => $item,
        ], meta: [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_DELETED', $this->t('project/messages.deleted'));
    }

    public function timeline(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var GanttService $service */
        $service = $this->container->get('service.gantt');
        $result = $service->timeline((string)$params['public_id'], $this->request()->allInput(), $authUser['user']);
        if ($result === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_TIMELINE', $this->t('project/messages.timeline'), [
            'timeline' => $result,
        ]);
    }

    public function summary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->summary((string)$params['public_id'], $authUser['user']);
        if ($result === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_SUMMARY', $this->t('project/messages.summary'), [
            'summary' => $result,
        ]);
    }

    public function milestonesSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->milestones((string)$params['public_id'], $authUser['user']);
        if ($result === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_MILESTONES_SUMMARY', $this->t('project/messages.milestones_summary'), [
            'milestones' => $result,
        ]);
    }

    public function risksSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->risks((string)$params['public_id'], $authUser['user']);
        if ($result === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_RISKS_SUMMARY', $this->t('project/messages.risks_summary'), [
            'risks' => $result,
        ]);
    }

    public function workloadSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->workload((string)$params['public_id'], $authUser['user']);
        if ($result === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }

        return $this->success('PROJECT_WORKLOAD_SUMMARY', $this->t('project/messages.workload_summary'), [
            'workload' => $result,
        ]);
    }

}
