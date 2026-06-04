<?php
declare(strict_types=1);

namespace Api\Controller\View;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SavedViewService;

final class ViewController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('VIEW_LIST', $this->t('view/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $entityType = strtolower(trim((string)($input['entity_type'] ?? '')));
        $title = trim((string)($input['title'] ?? ''));

        $allowedEntityTypes = ['task', 'project', 'client', 'company', 'contact', 'dashboard', 'analytics', 'admin_user'];
        $errors = [];
        if (!in_array($entityType, $allowedEntityTypes, true)) {
            $errors['entity_type'][] = $this->t('view/messages.entity_type_invalid');
        }
        if ($title === '') {
            $errors['title'][] = $this->t('view/messages.title_required');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->create($input, $auth['user']);

        return $this->success('VIEW_CREATED', $this->t('view/messages.created'), [
            'view' => $item,
        ], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->update((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if ($item === null) {
            return $this->error('VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        return $this->success('VIEW_UPDATED', $this->t('view/messages.updated'), [
            'view' => $item,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if ($ok === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if (!$ok) {
            return $this->error('VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        return $this->success('VIEW_DELETED', $this->t('view/messages.deleted'));
    }
}
