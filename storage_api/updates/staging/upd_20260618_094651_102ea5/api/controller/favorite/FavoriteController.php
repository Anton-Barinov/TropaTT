<?php
declare(strict_types=1);

namespace Api\Controller\Favorite;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\FavoriteService;

final class FavoriteController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FavoriteService $service */
        $service = $this->container->get('service.favorite');
        $result = $service->list($this->request()->allInput(), $auth['user']);
        if ($result === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('FAVORITE_LIST', $this->t('favorite/messages.list'), [
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
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));

        $allowed = ['task', 'project', 'comment'];
        $errors = [];
        if (!in_array($entityType, $allowed, true)) {
            $errors['entity_type'][] = $this->t('favorite/messages.entity_type_invalid');
        }
        if ($entityPublicId === '') {
            $errors['entity_public_id'][] = $this->t('favorite/messages.entity_required');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var FavoriteService $service */
        $service = $this->container->get('service.favorite');
        $item = $service->create($input, $auth['user']);
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('FAVORITE_CREATED', $this->t('favorite/messages.created'), [
            'favorite' => $item,
        ], 201);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FavoriteService $service */
        $service = $this->container->get('service.favorite');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if ($ok === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if (!$ok) {
            return $this->error('FAVORITE_NOT_FOUND', $this->t('favorite/messages.not_found'), 404);
        }

        return $this->success('FAVORITE_DELETED', $this->t('favorite/messages.deleted'));
    }
}
