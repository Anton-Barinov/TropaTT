<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class MockAiProviderClient implements AiProviderClientInterface
{
    public function completeText(array $provider, string $secret, array $payload): array
    {
        $simulatedError = $this->simulatedError($provider, 'simulate_completion_error');
        if ($simulatedError !== null) {
            return $simulatedError;
        }

        $intent = trim((string)($payload['intent_code'] ?? 'ai_intent'));
        $responseFormat = is_array($payload['response_format'] ?? null) ? (array)$payload['response_format'] : [];
        $isStructured = strtolower(trim((string)($responseFormat['type'] ?? ''))) === 'json_object';
        $text = $isStructured
            ? (string)json_encode($this->mockStructuredPayload($intent), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : ('Mock completion for intent: ' . $intent);

        return [
            'ok' => true,
            'text' => $text,
            'request_tokens' => 0,
            'response_tokens' => 0,
            'total_tokens' => 0,
            'latency_ms' => 1,
            'http_status' => 200,
        ];
    }

    public function testConnection(array $provider, string $secret): array
    {
        $simulatedError = $this->simulatedError($provider, 'simulate_test_error');
        if ($simulatedError !== null) {
            return $simulatedError;
        }

        return [
            'ok' => true,
            'latency_ms' => 1,
            'http_status' => 200,
            'message' => 'ok',
        ];
    }

    public function listModels(array $provider, string $secret): array
    {
        $simulatedError = $this->simulatedError($provider, 'simulate_models_error');
        if ($simulatedError !== null) {
            return $simulatedError;
        }

        $payload = $this->providerPayload($provider);

        $rawModels = [];
        if (is_array($payload) && is_array($payload['mock_models'] ?? null)) {
            $rawModels = (array)$payload['mock_models'];
        }

        if ($rawModels === []) {
            $default = trim((string)($provider['default_model'] ?? ''));
            if ($default !== '') {
                $rawModels[] = $default;
            }
            $rawModels[] = 'mock-fast';
            $rawModels[] = 'mock-reasoning';
        }

        $items = [];
        foreach ($rawModels as $model) {
            $id = trim((string)$model);
            if ($id === '') {
                continue;
            }
            $items[] = ['id' => $id, 'title' => $id];
        }

        if ($items === []) {
            $items[] = ['id' => 'mock-default', 'title' => 'mock-default'];
        }

        return [
            'ok' => true,
            'items' => $items,
            'http_status' => 200,
        ];
    }

    /** @param array<string,mixed> $provider */
    private function providerPayload(array $provider): array
    {
        $payload = $provider['provider_payload'] ?? null;
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($payload) ? $payload : [];
    }

    /** @param array<string,mixed> $provider @return array{ok:false,code:string,http_status:int,message:string}|null */
    private function simulatedError(array $provider, string $payloadKey): ?array
    {
        $payload = $this->providerPayload($provider);
        $rawCode = strtolower(trim((string)($payload[$payloadKey] ?? '')));
        if ($rawCode === '') {
            return null;
        }

        return match ($rawCode) {
            'timeout' => [
                'ok' => false,
                'code' => 'AI_PROVIDER_TIMEOUT',
                'http_status' => 504,
                'message' => 'Provider request failed',
            ],
            'auth', 'auth_failed' => [
                'ok' => false,
                'code' => 'AI_PROVIDER_AUTH_FAILED',
                'http_status' => 401,
                'message' => 'Provider request failed',
            ],
            default => [
                'ok' => false,
                'code' => 'AI_PROVIDER_CONNECTION_FAILED',
                'http_status' => 0,
                'message' => 'Provider request failed',
            ],
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function mockStructuredPayload(string $intentCode): array
    {
        $base = [
            'summary' => 'Mock structured payload for intent: ' . $intentCode,
            'meta' => [
                'intent_code' => $intentCode,
                'mode' => 'mock',
            ],
        ];

        return match ($intentCode) {
            'client_summary' => $base + [
                'facts' => ['Карточка клиента обработана в mock-режиме.'],
                'inferences' => ['Требуется уточнение следующего шага с клиентом.'],
                'risks' => [],
                'suggested_actions' => [],
                'questions' => [],
            ],
            'client_meeting_prep' => $base + [
                'facts' => ['Подготовлен mock-черновик к встрече.'],
                'inferences' => ['Нужно подтвердить повестку встречи.'],
                'risks' => [],
                'suggested_actions' => [],
                'questions' => ['Какая главная цель встречи?'],
            ],
            'client_data_quality' => $base + [
                'problems' => [],
                'recommendations' => ['Проверить заполненность контактов клиента.'],
                'questions' => [],
                'suggested_actions' => [],
            ],
            'client_safe_report' => $base + [
                'report_draft' => 'Клиентский safe-черновик сформирован в mock-режиме.',
                'evidence' => ['Источник: CRM карточка клиента.'],
                'risks' => [],
                'questions' => [],
            ],
            default => $base,
        };
    }
}
