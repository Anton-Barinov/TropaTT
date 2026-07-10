<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiActionService;
use Api\System\Library\Service\AiActionTypeService;

final class AiActionController extends BaseController
{
    public function actionTypes(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiActionTypeService $service */
        $service = $this->container->get('service.ai_action_type');

        return $this->success('AI_ACTION_TYPES', $this->t('ai/messages.action_types'), [
            'items' => $service->enabledAllowlist(),
        ]);
    }

    public function execute(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $actionType = trim((string)($params['action_type'] ?? ''));
        if ($actionType === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'action_type' => [$this->t('ai/messages.action_type_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($actionType, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiActionService $service */
            $service = $this->container->get('service.ai_action');
            $result = $service->execute($actionType, $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_ACTION_FAILED'));
                $status = match ($code) {
                    'AI_DISABLED' => 409,
                    'AI_FEATURE_DISABLED', 'AI_INTENT_DISABLED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED' => 422,
                    'AI_PROVIDER_NOT_CONFIGURED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_BUSY' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.action_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_ACTION_RESULT', $this->t('ai/messages.action_result'), [
                'result' => $result['result'],
            ]);
        });
    }

    /** @param array<string,mixed> $result */
    private function aiErrorMeta(array $result, string $code): array
    {
        $meta = [];
        if (in_array($code, ['AI_RATE_LIMITED', 'AI_BUSY'], true)) {
            $retryAfter = (int)($result['retry_after'] ?? 0);
            if ($retryAfter > 0) {
                $meta['retry_after'] = $retryAfter;
            }
        }
        if (in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE'], true)) {
            $meta['provider_error'] = $this->providerErrorMeta($result, $code);
        }

        return $meta;
    }

    /** @param array<string,mixed> $result */
    private function providerErrorMeta(array $result, string $code): array
    {
        $providerError = $result['provider_error'] ?? null;
        if (is_array($providerError)) {
            $category = trim((string)($providerError['category'] ?? ''));
            $meta = [
                'category' => $category !== '' ? $category : $this->providerErrorCategoryByCode($code),
                'retryable' => (bool)($providerError['retryable'] ?? ($code !== 'AI_PROVIDER_AUTH_FAILED')),
            ];
            $httpStatus = max(0, (int)($providerError['http_status'] ?? 0));
            if ($httpStatus > 0) {
                $meta['http_status'] = $httpStatus;
            }

            return $meta;
        }

        return [
            'category' => $this->providerErrorCategoryByCode($code),
            'retryable' => $code !== 'AI_PROVIDER_AUTH_FAILED',
        ];
    }

    private function providerErrorCategoryByCode(string $code): string
    {
        return match ($code) {
            'AI_PROVIDER_TIMEOUT' => 'timeout',
            'AI_PROVIDER_AUTH_FAILED' => 'auth',
            default => 'unavailable',
        };
    }

    private function normalizeAiCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (str_starts_with($code, 'AI_PROVIDER_') && !in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_PROVIDER_UNAVAILABLE'], true)) {
            return 'AI_PROVIDER_UNAVAILABLE';
        }

        return $code !== '' ? $code : 'AI_ACTION_FAILED';
    }
}
