<?php
declare(strict_types=1);

namespace Api\Controller\Subscription;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SubscriptionService;

final class SubscriptionController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SubscriptionService $service */
        $service = $this->container->get('service.subscription');
        $result = $service->list($this->request()->allInput(), $auth['user']);
        if ($result === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('SUBSCRIPTION_LIST', $this->t('subscription/messages.list'), [
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

        $allowedTypes = ['task', 'project', 'comment'];
        $errors = [];
        if (!in_array($entityType, $allowedTypes, true)) {
            $errors['entity_type'][] = $this->t('subscription/messages.entity_type_invalid');
        }
        if ($entityPublicId === '') {
            $errors['entity_public_id'][] = $this->t('subscription/messages.entity_required');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var SubscriptionService $service */
        $service = $this->container->get('service.subscription');
        $item = $service->create($input, $auth['user']);
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('SUBSCRIPTION_CREATED', $this->t('subscription/messages.created'), [
            'subscription' => $item,
        ], 201);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SubscriptionService $service */
        $service = $this->container->get('service.subscription');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if ($ok === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        if (!$ok) {
            return $this->error('SUBSCRIPTION_NOT_FOUND', $this->t('subscription/messages.not_found'), 404);
        }

        return $this->success('SUBSCRIPTION_DELETED', $this->t('subscription/messages.deleted'));
    }
}
