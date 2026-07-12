<?php
declare(strict_types=1);

namespace Api\Controller\Comment;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\CommentDraftService;

final class CommentDraftController extends BaseController
{
    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CommentDraftService $service */
        $service = $this->container->get('service.comment_draft');
        $item = $service->get((string)$params['public_id'], $auth['user']);
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('task/messages.not_found'), 404);
        }

        return $this->success('COMMENT_DRAFT_DETAIL', $this->t('comment/messages.draft_detail'), [
            'draft' => $item,
        ]);
    }

    public function save(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $body = trim((string)($input['body'] ?? ''));
        if ($body === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'body' => [$this->t('comment/messages.draft_body_required')],
            ]);
        }

        /** @var CommentDraftService $service */
        $service = $this->container->get('service.comment_draft');
        $item = $service->save((string)$params['public_id'], $body, $auth['user']);
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('task/messages.not_found'), 404);
        }

        return $this->success('COMMENT_DRAFT_SAVED', $this->t('comment/messages.draft_saved'), [
            'draft' => $item,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CommentDraftService $service */
        $service = $this->container->get('service.comment_draft');
        $ok = $service->clear((string)$params['public_id'], $auth['user']);
        if ($ok === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('task/messages.not_found'), 404);
        }

        return $this->success('COMMENT_DRAFT_DELETED', $this->t('comment/messages.draft_deleted'), [
            'deleted' => (bool)$ok,
        ]);
    }
}
