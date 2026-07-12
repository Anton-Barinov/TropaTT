<?php
declare(strict_types=1);

namespace Api\Controller\ApiClient;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ApiClientService;
use Api\System\Library\Validation\Validator;

final class ApiClientController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->listClients($this->request()->allInput());

        return $this->success('API_CLIENT_LIST', $this->t('api_client/messages.list'), ['items' => $result['items']], meta: $result['meta']);
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
            ->maxLen($input, 'title', 255, $this->t('api_client/messages.max_255'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var ApiClientService $service */
            $service = $this->container->get('service.api_client');
            $result = $service->createClient($input, $auth['user']);
            if (!$result['ok']) {
                return $this->error((string)$result['code'], $this->t('api_client/messages.create_failed'), 403, [
                    'api_client' => [(string)$result['code']],
                ]);
            }

            return $this->success('API_CLIENT_CREATED', $this->t('api_client/messages.created'), ['api_client' => $result['client']], 201);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $client = $service->getClient((string)$params['public_id']);
        if (!$client) {
            return $this->error('API_CLIENT_NOT_FOUND', $this->t('api_client/messages.not_found'), 404, [
                'api_client' => [$this->t('api_client/messages.not_found')],
            ]);
        }

        return $this->success('API_CLIENT_GET', $this->t('api_client/messages.detail'), ['api_client' => $client]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->updateClient((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!$result['ok']) {
            $status = (string)$result['code'] === 'API_CLIENT_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('api_client/messages.update_failed'), $status, [
                'api_client' => [(string)$result['code']],
            ]);
        }

        return $this->success('API_CLIENT_UPDATED', $this->t('api_client/messages.updated'), ['api_client' => $result['client']]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->deleteClient((string)$params['public_id'], $auth['user']);
        if (!$result['ok']) {
            $status = match ((string)$result['code']) {
                'API_CLIENT_NOT_FOUND' => 404,
                'API_CLIENT_HAS_ACTIVE_KEYS' => 409,
                default => 403,
            };
            return $this->error((string)$result['code'], $this->t('api_client/messages.delete_failed'), $status, [
                'api_client' => [(string)$result['code']],
            ]);
        }

        return $this->success('API_CLIENT_DELETED', $this->t('api_client/messages.deleted'));
    }

    public function listKeys(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->listKeys((string)$params['public_id']);
        if (!$result['ok']) {
            return $this->error((string)$result['code'], $this->t('api_client/messages.not_found'), 404, [
                'api_client' => [$this->t('api_client/messages.not_found')],
            ]);
        }

        return $this->success('API_KEY_LIST', $this->t('api_client/messages.key_list'), [
            'api_client' => $result['client'],
            'items' => $result['items'],
        ]);
    }

    public function issueKey(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->issueKey((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!$result['ok']) {
            $status = match ((string)$result['code']) {
                'API_CLIENT_NOT_FOUND' => 404,
                'API_CLIENT_INACTIVE' => 409,
                default => 403,
            };
            return $this->error((string)$result['code'], $this->t('api_client/messages.key_issue_failed'), $status, [
                'api_key' => [(string)$result['code']],
            ]);
        }

        return $this->success('API_KEY_ISSUED', $this->t('api_client/messages.key_issued'), [
            'api_key' => $result['key'],
            'plain_key' => (string)$result['plain_key'],
        ], 201);
    }

    public function rotateKey(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->rotateKey((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!$result['ok']) {
            $status = (string)$result['code'] === 'API_KEY_NOT_FOUND' ? 404 : 409;
            if ((string)$result['code'] === 'FORBIDDEN') {
                $status = 403;
            }

            return $this->error((string)$result['code'], $this->t('api_client/messages.key_rotate_failed'), $status, [
                'api_key' => [(string)$result['code']],
            ]);
        }

        return $this->success('API_KEY_ROTATED', $this->t('api_client/messages.key_rotated'), [
            'api_key' => $result['key'],
            'plain_key' => (string)$result['plain_key'],
        ]);
    }

    public function revokeKey(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $result = $service->revokeKey((string)$params['public_id'], $auth['user']);
        if (!$result['ok']) {
            $status = (string)$result['code'] === 'API_KEY_NOT_FOUND' ? 404 : 409;
            if ((string)$result['code'] === 'FORBIDDEN') {
                $status = 403;
            }

            return $this->error((string)$result['code'], $this->t('api_client/messages.key_revoke_failed'), $status, [
                'api_key' => [(string)$result['code']],
            ]);
        }

        return $this->success('API_KEY_REVOKED', $this->t('api_client/messages.key_revoked'), ['api_key' => $result['key']]);
    }

    public function keyUsage(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $limit = max(1, min(200, (int)$this->request()->input('limit', 50)));
        $result = $service->usage((string)$params['public_id'], $limit);
        if (!$result['ok']) {
            return $this->error((string)$result['code'], $this->t('api_client/messages.key_not_found'), 404, [
                'api_key' => [$this->t('api_client/messages.key_not_found')],
            ]);
        }

        return $this->success('API_KEY_USAGE', $this->t('api_client/messages.key_usage'), [
            'api_key' => $result['key'],
            'logs' => $result['logs'],
        ]);
    }
}
