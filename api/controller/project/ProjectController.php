<?php
declare(strict_types=1);

namespace Api\Controller\Project;

use Api\Controller\Common\BaseController;
use Api\Model\Project\ProjectRepository;
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

        $cache = $this->cacheApi();
        if ($cache !== null) {
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . md5(json_encode($input));
            $result = $cache->remember('project', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var ProjectService $service */
                $service = $this->container->get('service.project');
                return $service->list($input, $authUser['user']);
            });
        } else {
            /** @var ProjectService $service */
            $service = $this->container->get('service.project');
            $result = $service->list($input, $authUser['user']);
        }

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

        // SEC-003: Sanitize HTML from user input to prevent stored XSS
        if (isset($input['title']) && is_string($input['title'])) {
            $input['title'] = strip_tags((string)$input['title']);
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $input['description'] = strip_tags((string)$input['description'], '<b><i><u><p><br><ul><ol><li><a><strong><em><h1><h2><h3><h4><h5><h6><blockquote><code><pre><table><thead><tbody><tr><th><td><hr>');
        }

        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('project/messages.max_255'))
            ->maxLen($input, 'description', 65000, 'Description is too long');

        // Validate task_key_prefix
        if ($v->fails() || !empty($input['task_key_prefix'])) {
            $prefixError = $this->validateTaskKeyPrefix((string)($input['task_key_prefix'] ?? ''), null);
            if ($prefixError !== null) {
                return $this->error($prefixError['code'], $prefixError['message'], $prefixError['status'], [
                    'task_key_prefix' => [$prefixError['message']],
                ]);
            }
        }

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

            return $this->withIdempotency(function () use ($service, $input, $authUser): \Api\System\Library\Http\JsonResponse {
            $item = $service->create($input, $authUser['user']);

            $this->invalidateCache('project');

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

        // SEC-003: Sanitize HTML from user input to prevent stored XSS
        if (isset($input['title']) && is_string($input['title'])) {
            $input['title'] = strip_tags((string)$input['title']);
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $input['description'] = strip_tags((string)$input['description'], '<b><i><u><p><br><ul><ol><li><a><strong><em><h1><h2><h3><h4><h5><h6><blockquote><code><pre><table><thead><tbody><tr><th><td><hr>');
        }

        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('project/messages.max_255'))
            ->maxLen($input, 'description', 65000, 'Description is too long');
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        // Validate task_key_prefix
        if (!empty($input['task_key_prefix'])) {
            $prefixError = $this->validateTaskKeyPrefix((string)$input['task_key_prefix'], (string)$params['public_id']);
            if ($prefixError !== null) {
                return $this->error($prefixError['code'], $prefixError['message'], $prefixError['status'], [
                    'task_key_prefix' => [$prefixError['message']],
                ]);
            }
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

        $this->invalidateCache('project');

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

        $this->invalidateCache('project');

        return $this->success('PROJECT_DELETED', $this->t('project/messages.deleted'));
    }

    public function timeline(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'timeline:' . $this->cacheUserId() . ':' . (string)$params['public_id'] . ':' . md5(json_encode($input));
            $result = $cache->remember('project', $cacheKey, 60, function () use ($params, $input, $authUser) {
                /** @var GanttService $service */
                $service = $this->container->get('service.gantt');
                return $service->timeline((string)$params['public_id'], $input, $authUser['user']);
            });
        } else {
            /** @var GanttService $service */
            $service = $this->container->get('service.gantt');
            $result = $service->timeline((string)$params['public_id'], $this->request()->allInput(), $authUser['user']);
        }

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

    /**
     * @return array{code: string, message: string, status: int}|null
     */
    private function validateTaskKeyPrefix(string $rawPrefix, ?string $projectPublicId): ?array
    {
        /** @var \Api\System\Library\Service\TaskKeyService $taskKeys */
        try {
            $taskKeys = $this->container->get('service.task_key');
        } catch (\Throwable) {
            return null;
        }

        $normalized = $taskKeys->normalizePrefix($rawPrefix);
        if ($normalized === '') {
            return ['code' => 'PROJECT_TASK_PREFIX_INVALID', 'message' => $this->t('project/messages.invalid_prefix', 'Invalid task key prefix'), 'status' => 422];
        }

        if (!$taskKeys->isValidPrefix($normalized)) {
            return ['code' => 'PROJECT_TASK_PREFIX_INVALID', 'message' => $this->t('project/messages.invalid_prefix_format', 'Prefix must be 2-10 uppercase letters/digits, starting with a letter'), 'status' => 422];
        }

        if ($taskKeys->isReservedPrefix($normalized)) {
            return ['code' => 'PROJECT_TASK_PREFIX_RESERVED', 'message' => $this->t('project/messages.reserved_prefix', 'This prefix is reserved for system use'), 'status' => 422];
        }

        // Check for duplicate prefix
        try {
            /** @var \Api\Model\Project\ProjectRepository $projectRepo */
            $projectRepo = $this->container->get('repository.project');
            if ($projectRepo->taskKeyPrefixExists($normalized, $projectPublicId)) {
                return ['code' => 'PROJECT_TASK_PREFIX_ALREADY_EXISTS', 'message' => $this->t('project/messages.prefix_already_exists', 'This prefix is already used by another project'), 'status' => 409];
            }
        } catch (\Throwable) {
            // If container lookup fails, skip server-side check (rare edge case)
        }

        return null;
    }
}
