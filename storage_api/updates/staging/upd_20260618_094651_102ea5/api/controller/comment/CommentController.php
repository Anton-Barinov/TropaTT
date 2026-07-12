<?php
declare(strict_types=1);

namespace Api\Controller\Comment;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\CommentService;
use Api\System\Library\Validation\Validator;

final class CommentController extends BaseController
{
    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'body', 8000, $this->t('comment/messages.max_8000'));
        $v->enum($input, 'visibility', ['internal', 'client'], $this->t('comment/messages.invalid_visibility'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CommentService $service */
        $service = $this->container->get('service.comment');
        $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        if (!$item) {
            return $this->error('COMMENT_NOT_FOUND', $this->t('comment/messages.not_found'), 404, [
                'comment' => [$this->t('comment/messages.not_found')],
            ]);
        }

        return $this->success('COMMENT_UPDATED', $this->t('comment/messages.updated'), [
            'comment' => $item,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CommentService $service */
        $service = $this->container->get('service.comment');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('COMMENT_NOT_FOUND', $this->t('comment/messages.not_found'), 404, [
                'comment' => [$this->t('comment/messages.not_found')],
            ]);
        }

        return $this->success('COMMENT_DELETED', $this->t('comment/messages.deleted'));
    }
}
