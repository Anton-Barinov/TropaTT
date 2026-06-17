<?php
declare(strict_types=1);

namespace Api\Controller\Project;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\ProjectModuleService;

final class ProjectModuleController extends BaseController
{
    private function service(): ProjectModuleService
    {
        return $this->container->get('service.project_module');
    }

    // ── CRUD ──

    public function list(): JsonResponse
    {
        $filters = $this->request()->allInput();
        $result = $this->service()->list($filters, ($this->user())['user'] ?? []);

        return $this->success('PROJECT_MODULE_LIST', 'Project modules', $result);
    }

    public function create(): JsonResponse
    {
        $input = $this->request()->allInput();
        $result = $this->service()->create($input, ($this->user())['user'] ?? []);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_CREATED', 'Project module created', $result, 201);
    }

    public function get(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->get($publicId, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_DETAIL', 'Project module', $result);
    }

    public function update(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $result = $this->service()->update($publicId, $input, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_UPDATED', 'Project module updated', $result);
    }

    public function delete(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->delete($publicId, ($this->user())['user'] ?? []);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_DELETED', 'Project module deleted', [], 200);
    }

    public function archive(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->archive($publicId, ($this->user())['user'] ?? []);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_ARCHIVED', 'Project module archived', [], 200);
    }

    // ── Tasks ──

    public function tasks(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $filters = $this->request()->allInput();
        $result = $this->service()->tasks($publicId, $filters, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_TASKS', 'Module tasks', $result);
    }

    public function addTasks(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $result = $this->service()->addTasks($publicId, $input, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        // Invalidate cache
        $this->invalidateCache('task');
        $this->invalidateCache('project');

        return $this->success('PROJECT_MODULE_TASKS_ADDED', 'Tasks added to module', $result, 200);
    }

    public function removeTask(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $taskPublicId = (string)($params['task_public_id'] ?? '');
        $result = $this->service()->removeTask($publicId, $taskPublicId, ($this->user())['user'] ?? []);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        // Invalidate cache
        $this->invalidateCache('task');
        $this->invalidateCache('project');

        return $this->success('PROJECT_MODULE_TASK_REMOVED', 'Task removed from module', [], 200);
    }

    // ── Members ──

    public function members(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->members($publicId, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_MEMBERS', 'Module members', ['items' => $result]);
    }

    public function addMembers(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $result = $this->service()->addMembers($publicId, $input, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_MEMBERS_ADDED', 'Members added to module', $result, 200);
    }

    public function removeMember(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $userPublicId = (string)($params['user_public_id'] ?? '');
        $result = $this->service()->removeMember($publicId, $userPublicId, ($this->user())['user'] ?? []);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_MEMBER_REMOVED', 'Member removed from module', [], 200);
    }

    // ── Links ──

    public function links(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->links($publicId, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_LINKS', 'Module links', ['items' => $result]);
    }

    public function addLink(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $result = $this->service()->addLink($publicId, $input, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_LINK_ADDED', 'Link added to module', $result, 200);
    }

    public function updateLink(array $params): JsonResponse
    {
        $linkPublicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $result = $this->service()->updateLink($linkPublicId, $input, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_LINK_UPDATED', 'Link updated', $result);
    }

    public function deleteLink(array $params): JsonResponse
    {
        $linkPublicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->deleteLink($linkPublicId, ($this->user())['user'] ?? []);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_LINK_DELETED', 'Link deleted', [], 200);
    }

    // ── Summary ──

    public function summary(array $params): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $result = $this->service()->summary($publicId, ($this->user())['user'] ?? []);

        if (is_string($result) || $result === null) {
            return $this->mapError($result);
        }

        return $this->success('PROJECT_MODULE_SUMMARY', 'Project module summary', $result);
    }

    // ── Error mapping ──

    private function mapError(string|null $error): JsonResponse
    {
        $code = $error ?? 'PROJECT_MODULE_NOT_FOUND';
        $status = match ($code) {
            'PROJECT_MODULE_NOT_FOUND' => 404,
            'PROJECT_MODULE_FORBIDDEN' => 403,
            'PROJECT_MODULE_PROJECT_NOT_FOUND' => 404,
            'PROJECT_MODULE_LEAD_NOT_FOUND' => 404,
            'PROJECT_MODULE_TASK_NOT_FOUND' => 404,
            'PROJECT_MODULE_LINK_NOT_FOUND' => 404,
            'PROJECT_MODULE_TASK_ALREADY_EXISTS' => 409,
            'PROJECT_MODULE_MEMBER_ALREADY_EXISTS' => 409,
            'ROW_VERSION_CONFLICT' => 409,
            default => 422,
        };

        return $this->error($code, ucwords(strtolower(str_replace('_', ' ', $code))), $status);
    }
}
