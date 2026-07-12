<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiProviderService;
use Api\System\Library\Service\AiRetentionPolicyService;
use Api\System\Library\Validation\Validator;

final class AiProviderController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->list($this->request()->allInput());

        return $this->success('AI_PROVIDER_LIST', $this->t('ai/messages.provider_list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->get((string)$params['public_id']);
        if (!(bool)($result['ok'] ?? false)) {
            return $this->error((string)$result['code'], $this->t('ai/messages.provider_not_found'), 404, [
                'provider' => [(string)$result['code']],
            ]);
        }

        return $this->success('AI_PROVIDER_GET', $this->t('ai/messages.provider_get'), ['provider' => $result['provider']]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'provider_code', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->require($input, 'base_url', $this->t('common/messages.field_required'))
            ->maxLen($input, 'provider_code', 64, $this->t('ai/messages.provider_code_too_long'))
            ->maxLen($input, 'title', 255, $this->t('ai/messages.provider_title_too_long'))
            ->maxLen($input, 'base_url', 2048, $this->t('ai/messages.provider_base_url_too_long'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiProviderService $service */
            $service = $this->container->get('service.ai_provider');
            $result = $service->create($input, $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? '');
                $status = (str_contains($code, 'URL_') || str_contains($code, 'HEADERS_')) ? 422 : 400;
                return $this->error((string)$result['code'], $this->t('ai/messages.provider_create_failed'), $status, [
                    'provider' => [(string)$result['code']],
                ]);
            }

            return $this->success('AI_PROVIDER_CREATED', $this->t('ai/messages.provider_created'), ['provider' => $result['provider']], 201);
        });
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->update((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_PROVIDER_UPDATE_FAILED');
            $status = match ($code) {
                'AI_PROVIDER_NOT_FOUND' => 404,
                'AI_PROVIDER_NO_CHANGES' => 422,
                default => ((str_contains($code, 'URL_') || str_contains($code, 'HEADERS_')) ? 422 : 400),
            };
            return $this->error($code, $this->t('ai/messages.provider_update_failed'), $status, [
                'provider' => [$code],
            ]);
        }

        return $this->success('AI_PROVIDER_UPDATED', $this->t('ai/messages.provider_updated'), ['provider' => $result['provider']]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->delete((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'AI_PROVIDER_NOT_FOUND' ? 404 : 400;
            return $this->error((string)$result['code'], $this->t('ai/messages.provider_delete_failed'), $status, [
                'provider' => [(string)$result['code']],
            ]);
        }

        return $this->success('AI_PROVIDER_DELETED', $this->t('ai/messages.provider_deleted'));
    }

    public function setSecret(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $secret = trim((string)$this->request()->input('secret', ''));
        if ($secret === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'secret' => [$this->t('ai/messages.provider_secret_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($params, $secret, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiProviderService $service */
            $service = $this->container->get('service.ai_provider');
            $result = $service->upsertSecret((string)$params['public_id'], $secret, $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $status = (string)($result['code'] ?? '') === 'AI_PROVIDER_NOT_FOUND' ? 404 : 422;
                return $this->error((string)$result['code'], $this->t('ai/messages.provider_secret_set_failed'), $status, [
                    'secret' => [(string)$result['code']],
                ]);
            }

            return $this->success('AI_PROVIDER_SECRET_UPDATED', $this->t('ai/messages.provider_secret_updated'), ['credential' => $result['credential']]);
        });
    }

    public function deleteSecret(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->deleteSecret((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'AI_PROVIDER_NOT_FOUND' ? 404 : 400;
            return $this->error((string)$result['code'], $this->t('ai/messages.provider_secret_delete_failed'), $status, [
                'secret' => [(string)$result['code']],
            ]);
        }

        return $this->success('AI_PROVIDER_SECRET_DELETED', $this->t('ai/messages.provider_secret_deleted'), ['credential' => $result['credential']]);
    }

    public function retentionList(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiRetentionPolicyService $service */
        $service = $this->container->get('service.ai_retention');
        return $this->success('AI_RETENTION_LIST', $this->t('ai/messages.retention_list'), [
            'items' => $service->getPolicies(),
        ]);
    }

    public function retentionUpdate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $days = (int)$this->request()->input('days', 0);
        /** @var AiRetentionPolicyService $service */
        $service = $this->container->get('service.ai_retention');
        $result = $service->updatePolicy((string)$params['policy_code'], $days, $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_RETENTION_UPDATE_FAILED');
            $status = $code === 'AI_RETENTION_POLICY_NOT_FOUND' ? 404 : 422;
            return $this->error($code, $this->t('ai/messages.retention_update_failed'), $status, [
                'policy' => [$code],
            ]);
        }

        return $this->success('AI_RETENTION_UPDATED', $this->t('ai/messages.retention_updated'), [
            'policy' => $result['policy'],
        ]);
    }

    public function test(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->testConnection((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_PROVIDER_TEST_FAILED');
            $status = match ($code) {
                'AI_PROVIDER_NOT_FOUND' => 404,
                'AI_PROVIDER_SECRET_NOT_CONFIGURED', 'AI_PROVIDER_NOT_CONFIGURED' => 409,
                'AI_PROVIDER_TIMEOUT' => 504,
                'AI_PROVIDER_RATE_LIMITED' => 429,
                'AI_PROVIDER_INSUFFICIENT_CREDITS' => 402,
                'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                default => 502,
            };
            return $this->error($code, $this->t('ai/messages.provider_test_failed'), $status, [
                'provider' => [$code],
            ], meta: $this->providerErrorMeta($result));
        }

        return $this->success('AI_PROVIDER_TEST_OK', $this->t('ai/messages.provider_test_ok'), [
            'result' => $result['result'],
        ]);
    }

    public function models(): \Api\System\Library\Http\JsonResponse
    {
        $providerPublicId = trim((string)$this->request()->input('provider_public_id', ''));

        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->listModels($providerPublicId !== '' ? $providerPublicId : null);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_PROVIDER_TEST_FAILED');
            $status = match ($code) {
                'AI_PROVIDER_NOT_FOUND' => 404,
                'AI_PROVIDER_NOT_CONFIGURED', 'AI_PROVIDER_SECRET_NOT_CONFIGURED' => 409,
                'AI_PROVIDER_TIMEOUT' => 504,
                'AI_PROVIDER_RATE_LIMITED' => 429,
                'AI_PROVIDER_INSUFFICIENT_CREDITS' => 402,
                'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                default => 502,
            };
            return $this->error($code, $this->t('ai/messages.models_list_failed'), $status, [
                'provider' => [$code],
            ], meta: $this->providerErrorMeta($result));
        }

        return $this->success('AI_MODELS_LIST', $this->t('ai/messages.models_list'), [
            'provider_public_id' => $result['provider_public_id'],
            'items' => $result['items'],
        ]);
    }

    public function syncModels(): \Api\System\Library\Http\JsonResponse
    {
        $providerPublicId = trim((string)$this->request()->input('provider_public_id', ''));

        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        $result = $service->listModels($providerPublicId !== '' ? $providerPublicId : null);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_PROVIDER_TEST_FAILED');
            $status = match ($code) {
                'AI_PROVIDER_NOT_FOUND' => 404,
                'AI_PROVIDER_NOT_CONFIGURED', 'AI_PROVIDER_SECRET_NOT_CONFIGURED' => 409,
                'AI_PROVIDER_TIMEOUT' => 504,
                'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                default => 502,
            };
            return $this->error($code, $this->t('ai/messages.models_sync_failed'), $status, [
                'provider' => [$code],
            ], meta: $this->providerErrorMeta($result));
        }

        return $this->success('AI_MODELS_SYNCED', $this->t('ai/messages.models_synced'), [
            'provider_public_id' => $result['provider_public_id'],
            'items' => $result['items'],
            'synced_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $result */
    private function providerErrorMeta(array $result): array
    {
        $providerError = $result['provider_error'] ?? null;
        $meta = [];
        $message = trim((string)($result['message'] ?? ''));
        if ($message !== '') {
            $meta['message'] = $message;
        }

        if (!is_array($providerError)) {
            return $meta;
        }

        $category = trim((string)($providerError['category'] ?? ''));
        $metaProviderError = [
            'category' => $category !== '' ? $category : 'unavailable',
            'retryable' => (bool)($providerError['retryable'] ?? false),
        ];
        $httpStatus = max(0, (int)($providerError['http_status'] ?? 0));
        if ($httpStatus > 0) {
            $metaProviderError['http_status'] = $httpStatus;
        }

        return array_merge($meta, ['provider_error' => $metaProviderError]);
    }
}
