<?php
declare(strict_types=1);

namespace Api\Controller\Webhook;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\WebhookService;
use Api\System\Library\Validation\Validator;

final class WebhookController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        $result = $service->listSubscriptions($this->request()->allInput());

        return $this->success('WEBHOOK_LIST', $this->t('webhook/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        $item = $service->findSubscription((string)$params['public_id']);
        if (!$item) {
            return $this->error('WEBHOOK_NOT_FOUND', $this->t('webhook/messages.not_found'), 404);
        }

        return $this->success('WEBHOOK_DETAIL', $this->t('webhook/messages.detail'), ['webhook' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->require($input, 'endpoint', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('webhook/messages.max_title'))
            ->maxLen($input, 'endpoint', 2048, $this->t('webhook/messages.max_endpoint'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var WebhookService $service */
            $service = $this->container->get('service.webhook');
            $result = $service->createSubscription($input, $auth['user']);
            if (!$result['ok']) {
                $code = (string)$result['code'];
                $status = $this->isEndpointValidationCode($code) ? 422 : 403;
                return $this->error($code, $this->t('webhook/messages.create_failed'), $status, [
                    'webhook' => [$code],
                ]);
            }

            return $this->success('WEBHOOK_CREATED', $this->t('webhook/messages.created'), ['webhook' => $result['webhook']], 201);
        });
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        $result = $service->updateSubscription((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!$result['ok']) {
            $code = (string)$result['code'];
            $status = match (true) {
                $code === 'WEBHOOK_NOT_FOUND' => 404,
                $this->isEndpointValidationCode($code) => 422,
                default => 403,
            };
            return $this->error($code, $this->t('webhook/messages.update_failed'), $status, [
                'webhook' => [$code],
            ]);
        }

        return $this->success('WEBHOOK_UPDATED', $this->t('webhook/messages.updated'), ['webhook' => $result['webhook']]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        $result = $service->deleteSubscription((string)$params['public_id'], $auth['user']);
        if (!$result['ok']) {
            $status = (string)$result['code'] === 'WEBHOOK_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('webhook/messages.delete_failed'), $status, [
                'webhook' => [(string)$result['code']],
            ]);
        }

        return $this->success('WEBHOOK_DELETED', $this->t('webhook/messages.deleted'));
    }

    public function deliveries(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $filters = $this->request()->allInput();
        if (isset($params['public_id'])) {
            $filters['webhook_public_id'] = (string)$params['public_id'];
        }

        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        $result = $service->listDeliveries($filters);

        return $this->success('WEBHOOK_DELIVERIES', $this->t('webhook/messages.deliveries'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function test(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        $input = $this->request()->allInput();
        $async = (int)($input['async'] ?? 0) === 1 || (string)($input['async'] ?? '') === 'true';
        $result = $async
            ? $service->enqueueTestDelivery((string)$params['public_id'], $auth['user'])
            : $service->testDelivery((string)$params['public_id'], $auth['user']);
        if (!$result['ok']) {
            $status = match ((string)$result['code']) {
                'WEBHOOK_NOT_FOUND' => 404,
                'WEBHOOK_INACTIVE' => 409,
                default => 403,
            };
            return $this->error((string)$result['code'], $this->t('webhook/messages.test_failed'), $status, [
                'webhook' => [(string)$result['code']],
            ]);
        }

        return $this->success($async ? 'WEBHOOK_TEST_QUEUED' : 'WEBHOOK_TEST_DELIVERED', $this->t('webhook/messages.test_delivered'), ['delivery' => $result['delivery']], $async ? 202 : 200);
    }

    private function isEndpointValidationCode(string $code): bool
    {
        return str_starts_with($code, 'WEBHOOK_ENDPOINT_');
    }
}
