<?php
declare(strict_types=1);

namespace Api\Controller\Template;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\TemplateService;
use Api\System\Library\Validation\Validator;

final class TemplateController extends BaseController
{
    public function taskList(): \Api\System\Library\Http\JsonResponse
    {
        return $this->listByKind('task', 'TASK_TEMPLATE_LIST', $this->t('template/messages.task_list'));
    }

    public function taskCreate(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createByKind('task', 'TASK_TEMPLATE_CREATED', $this->t('template/messages.task_created'));
    }

    public function taskGet(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->getByKind('task', (string)$params['public_id'], 'TASK_TEMPLATE_DETAIL', $this->t('template/messages.task_detail'));
    }

    public function taskUpdate(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->updateByKind('task', (string)$params['public_id'], 'TASK_TEMPLATE_UPDATED', $this->t('template/messages.task_updated'));
    }

    public function taskDelete(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->deleteByKind('task', (string)$params['public_id'], 'TASK_TEMPLATE_DELETED', $this->t('template/messages.task_deleted'));
    }

    public function projectList(): \Api\System\Library\Http\JsonResponse
    {
        return $this->listByKind('project', 'PROJECT_TEMPLATE_LIST', $this->t('template/messages.project_list'));
    }

    public function projectCreate(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createByKind('project', 'PROJECT_TEMPLATE_CREATED', $this->t('template/messages.project_created'));
    }

    public function projectGet(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->getByKind('project', (string)$params['public_id'], 'PROJECT_TEMPLATE_DETAIL', $this->t('template/messages.project_detail'));
    }

    public function projectUpdate(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->updateByKind('project', (string)$params['public_id'], 'PROJECT_TEMPLATE_UPDATED', $this->t('template/messages.project_updated'));
    }

    public function projectDelete(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->deleteByKind('project', (string)$params['public_id'], 'PROJECT_TEMPLATE_DELETED', $this->t('template/messages.project_deleted'));
    }

    public function taskListAlias(): \Api\System\Library\Http\JsonResponse { return $this->taskList(); }
    public function taskCreateAlias(): \Api\System\Library\Http\JsonResponse { return $this->taskCreate(); }
    public function projectListAlias(): \Api\System\Library\Http\JsonResponse { return $this->projectList(); }
    public function projectCreateAlias(): \Api\System\Library\Http\JsonResponse { return $this->projectCreate(); }

    private function listByKind(string $kind, string $code, string $message): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $result = $service->list($kind, $this->request()->allInput(), $authUser['user']);

        return $this->success($code, $message, ['items' => $result['items']], meta: $result['meta']);
    }

    private function createByKind(string $kind, string $code, string $message): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('template/messages.max_255'));
        if (isset($input['payload']) && !is_array($input['payload'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'payload' => [$this->t('template/messages.payload_object')],
            ]);
        }
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $item = $service->create($kind, $input, $authUser['user']);

        return $this->success($code, $message, ['template' => $item], 201);
    }

    private function getByKind(string $kind, string $publicId, string $code, string $message): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $item = $service->get($kind, $publicId, $authUser['user']);
        if (!$item) {
            return $this->error('TEMPLATE_NOT_FOUND', $this->t('template/messages.not_found'), 404, [
                'template' => [$this->t('template/messages.not_found')],
            ]);
        }

        return $this->success($code, $message, ['template' => $item]);
    }

    private function updateByKind(string $kind, string $publicId, string $code, string $message): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        if (isset($input['payload']) && !is_array($input['payload'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'payload' => [$this->t('template/messages.payload_object')],
            ]);
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $item = $service->update($kind, $publicId, $input, $authUser['user']);
        if (!$item) {
            return $this->error('TEMPLATE_NOT_FOUND', $this->t('template/messages.not_found'), 404, [
                'template' => [$this->t('template/messages.not_found')],
            ]);
        }

        return $this->success($code, $message, ['template' => $item]);
    }

    public function taskApply(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->applyByKind('task', (string)($params['public_id'] ?? ''), 'TASK_CREATED_FROM_TEMPLATE', 'Task created from template');
    }

    public function projectApply(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->applyByKind('project', (string)($params['public_id'] ?? ''), 'PROJECT_CREATED_FROM_TEMPLATE', 'Project created from template');
    }

    private function applyByKind(string $kind, string $publicId, string $code, string $message): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $result = $service->apply($kind, $publicId, $authUser['user']);
        if (!$result) return $this->error('TEMPLATE_NOT_FOUND', $this->t('common/messages.not_found'), 404);

        return $this->success($code, $message, ['entity' => $result], 201);
    }

    private function deleteByKind(string $kind, string $publicId, string $code, string $message): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $ok = $service->delete($kind, $publicId, $authUser['user']);
        if (!$ok) {
            return $this->error('TEMPLATE_NOT_FOUND', $this->t('template/messages.not_found'), 404, [
                'template' => [$this->t('template/messages.not_found')],
            ]);
        }

        return $this->success($code, $message, []);
    }
}
