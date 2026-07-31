<?php
declare(strict_types=1);

namespace Api\Controller\Reaction;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ReactionService;

final class ReactionController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ReactionService $service */
        $service = $this->container->get('service.reaction');
        $result = $service->list($this->request()->allInput(), $auth['user']);
        if ($result === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('REACTION_LIST', $this->t('reaction/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function add(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $entityType = strtolower(trim((string)($input['entity_type'] ?? '')));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));
        $reaction = strtolower(trim((string)($input['reaction'] ?? '')));

        $allowedTypes = ['task', 'project', 'comment'];
        $allowedReactions = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'up'];
        $errors = [];
        if (!in_array($entityType, $allowedTypes, true)) {
            $errors['entity_type'][] = $this->t('reaction/messages.entity_type_invalid');
        }
        if ($entityPublicId === '') {
            $errors['entity_public_id'][] = $this->t('reaction/messages.entity_required');
        }
        if (!in_array($reaction, $allowedReactions, true)) {
            $errors['reaction'][] = $this->t('reaction/messages.reaction_invalid');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var ReactionService $service */
        $service = $this->container->get('service.reaction');
        $item = $service->add($input, $auth['user']);
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('REACTION_ADDED', $this->t('reaction/messages.added'), [
            'reaction' => $item,
        ], 201);
    }

    public function remove(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ReactionService $service */
        $service = $this->container->get('service.reaction');
        $ok = $service->remove((string)$params['public_id'], $auth['user']);
        if ($ok === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if (!$ok) {
            return $this->error('REACTION_NOT_FOUND', $this->t('reaction/messages.not_found'), 404);
        }

        return $this->success('REACTION_REMOVED', $this->t('reaction/messages.removed'));
    }
}
