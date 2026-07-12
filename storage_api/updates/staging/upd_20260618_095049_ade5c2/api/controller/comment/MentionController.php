<?php
declare(strict_types=1);

namespace Api\Controller\Comment;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\MentionService;

final class MentionController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var MentionService $service */
        $service = $this->container->get('service.mention');
        $result = $service->list($this->request()->allInput(), $auth['user']);
        if ($result === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('MENTION_LIST', $this->t('comment/messages.mention_list'), [
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
        $mentionedUserPublicId = trim((string)($input['mentioned_user_public_id'] ?? ''));

        $allowedTypes = ['task', 'project', 'comment'];
        $errors = [];
        if (!in_array($entityType, $allowedTypes, true)) {
            $errors['entity_type'][] = $this->t('comment/messages.mention_entity_type_invalid');
        }
        if ($entityPublicId === '') {
            $errors['entity_public_id'][] = $this->t('comment/messages.mention_entity_required');
        }
        if ($mentionedUserPublicId === '') {
            $errors['mentioned_user_public_id'][] = $this->t('comment/messages.mentioned_user_required');
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var MentionService $service */
        $service = $this->container->get('service.mention');
        $item = $service->add($input, $auth['user']);
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if ($item === 'MENTIONED_USER_NOT_FOUND') {
            return $this->error('MENTIONED_USER_NOT_FOUND', $this->t('comment/messages.mentioned_user_not_found'), 404);
        }

        return $this->success('MENTION_ADDED', $this->t('comment/messages.mention_added'), [
            'mention' => $item,
        ], 201);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var MentionService $service */
        $service = $this->container->get('service.mention');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);

        if ($ok === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if (!$ok) {
            return $this->error('MENTION_NOT_FOUND', $this->t('comment/messages.mention_not_found'), 404);
        }

        return $this->success('MENTION_DELETED', $this->t('comment/messages.mention_deleted'));
    }
}
