<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class OpenAiCompatibleProviderClient implements AiProviderClientInterface
{
    public function completeText(array $provider, string $secret, array $payload): array
    {
        $startedAt = microtime(true);
        $url = $this->completionUrl($provider);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $secret,
        ];

        $model = trim((string)($payload['model'] ?? $provider['default_model'] ?? ''));
        $request = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => (string)($payload['system_prompt'] ?? '')],
                ['role' => 'user', 'content' => (string)($payload['user_prompt'] ?? '') . "\n\nContext:\n" . json_encode((array)($payload['context'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
            'temperature' => (float)($payload['temperature'] ?? $provider['temperature'] ?? 0.2),
        ];
        if (isset($payload['response_format']) && is_array($payload['response_format'])) {
            $request['response_format'] = $payload['response_format'];
        }
        $maxTokens = max(64, (int)($payload['max_tokens'] ?? $provider['max_tokens'] ?? 1200));
        if ($this->usesMaxCompletionTokens($model)) {
            $request['max_completion_tokens'] = $maxTokens;
        } else {
            $request['max_tokens'] = $maxTokens;
        }
        if ($request['model'] === '') {
            unset($request['model']);
        }

        $timeout = max(3000, (int)($provider['timeout_ms'] ?? 120000));
        $timeout = min($timeout, 300000);
        $response = $this->postJson($url, $headers, $request, $timeout, $provider);
        $latencyMs = (int)round((microtime(true) - $startedAt) * 1000);
        if (!(bool)($response['ok'] ?? false)) {
            return $this->mapProviderError($response, $latencyMs);
        }

        $json = is_array($response['json'] ?? null) ? (array)$response['json'] : [];
        $text = $this->extractCompletionText($json);
        if ($text === '') {
            return [
                'ok' => false,
                'code' => 'AI_PROVIDER_INVALID_RESPONSE',
                'message' => 'Provider request failed',
                'latency_ms' => $latencyMs,
                'http_status' => (int)($response['http_status'] ?? 0),
            ];
        }
        $usage = is_array($json['usage'] ?? null) ? (array)$json['usage'] : [];

        return [
            'ok' => true,
            'text' => $text,
            'request_tokens' => (int)($usage['prompt_tokens'] ?? 0),
            'response_tokens' => (int)($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int)($usage['total_tokens'] ?? 0),
            'latency_ms' => $latencyMs,
            'http_status' => (int)($response['http_status'] ?? 0),
        ];
    }

    private function usesMaxCompletionTokens(string $model): bool
    {
        $normalized = strtolower(trim($model));
        if ($normalized === '') {
            return false;
        }

        return str_starts_with($normalized, 'gpt-5')
            || str_starts_with($normalized, 'o3')
            || str_starts_with($normalized, 'o4');
    }

    public function testConnection(array $provider, string $secret): array
    {
        $startedAt = microtime(true);
        $url = $this->modelsUrl($provider);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $secret,
        ];

        $response = $this->getJson($url, $headers, (int)($provider['timeout_ms'] ?? 30000), $provider);
        if (!(bool)($response['ok'] ?? false)) {
            return $this->mapProviderError($response, (int)round((microtime(true) - $startedAt) * 1000));
        }

        $completionProbe = $this->completionProbe($provider, $secret);
        if (!(bool)($completionProbe['ok'] ?? false)) {
            return $completionProbe;
        }

        return [
            'ok' => true,
            'latency_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'http_status' => (int)($response['http_status'] ?? 0),
            'message' => 'ok',
            'completion_http_status' => (int)($completionProbe['completion_http_status'] ?? 0),
        ];
    }

    public function listModels(array $provider, string $secret): array
    {
        $url = $this->modelsUrl($provider);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $secret,
        ];

        $response = $this->getJson($url, $headers, (int)($provider['timeout_ms'] ?? 30000), $provider);
        if (!(bool)($response['ok'] ?? false)) {
            return $this->mapProviderError($response, 0);
        }

        $payload = $response['json'] ?? null;
        $rows = [];
        if (is_array($payload['data'] ?? null)) {
            $rows = (array)$payload['data'];
        } elseif (is_array($payload['items'] ?? null)) {
            $rows = (array)$payload['items'];
        } elseif (is_array($payload['models'] ?? null)) {
            $rows = (array)$payload['models'];
        } elseif (is_array($payload)) {
            $rows = $payload;
        }
        $items = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $id = trim($row);
                if ($id !== '') {
                    $items[] = ['id' => $id, 'title' => $id];
                }
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)($row['id'] ?? $row['model'] ?? $row['name'] ?? $row['value'] ?? ''));
            if ($id === '') {
                $title = trim((string)($row['title'] ?? $row['label'] ?? ''));
                if ($title !== '' && strtolower($title) !== 'unknown') {
                    $id = $title;
                }
            }
            if ($id === '') {
                continue;
            }
            $title = trim((string)($row['title'] ?? $row['label'] ?? $id));
            $items[] = ['id' => $id, 'title' => $title !== '' ? $title : $id];
        }

        return [
            'ok' => true,
            'items' => $items,
            'http_status' => (int)($response['http_status'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $provider
     */
    private function modelsUrl(array $provider): string
    {
        $baseUrl = rtrim((string)($provider['base_url'] ?? ''), '/');
        $apiPath = trim((string)($provider['api_path'] ?? ''));
        if ($apiPath === '') {
            return $baseUrl . '/v1/models';
        }

        $normalized = '/' . ltrim($apiPath, '/');
        if (str_contains($normalized, '/chat/completions')) {
            $normalized = str_replace('/chat/completions', '/models', $normalized);
        } elseif (!str_ends_with($normalized, '/models')) {
            $normalized = '/v1/models';
        }

        return $baseUrl . $normalized;
    }

    /** @param array<string,mixed> $provider */
    private function completionUrl(array $provider): string
    {
        $baseUrl = rtrim((string)($provider['base_url'] ?? ''), '/');
        $apiPath = trim((string)($provider['api_path'] ?? ''));
        if ($apiPath === '') {
            return $baseUrl . '/v1/chat/completions';
        }
        if ($apiPath[0] !== '/') {
            $apiPath = '/' . $apiPath;
        }

        return $baseUrl . $apiPath;
    }

    /**
     * @param list<string> $headers
     * @return array{ok:bool,http_status?:int,json?:array<string,mixed>,error_code?:string,error_message?:string}
     */
    private function getJson(string $url, array $headers, int $timeoutMs, array $provider = []): array
    {
        $runtime = $this->runtimeConfig($provider, $timeoutMs);
        $attempt = 0;
        $lastResponse = [
            'ok' => false,
            'error_code' => 'AI_PROVIDER_CONNECTION_FAILED',
            'error_message' => 'provider request failed',
            'http_status' => 0,
        ];

        while ($attempt < $runtime['max_attempts']) {
            $attempt++;
            $lastResponse = $this->sendGetJson($url, $headers, $runtime['timeout_ms']);
            if ((bool)($lastResponse['ok'] ?? false)) {
                return $lastResponse;
            }
            if (!$this->isRetryable($lastResponse) || $attempt >= $runtime['max_attempts']) {
                return $lastResponse;
            }
            if ($runtime['backoff_ms'] > 0) {
                usleep($runtime['backoff_ms'] * 1000);
            }
        }

        return $lastResponse;
    }

    /**
     * @param list<string> $headers
     * @return array{ok:bool,http_status?:int,json?:array<string,mixed>,error_code?:string,error_message?:string,error_type?:string}
     */
    private function sendGetJson(string $url, array $headers, int $timeoutMs): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                $raw = curl_exec($ch);
                $curlErrno = curl_errno($ch);
                $curlError = curl_error($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($curlErrno !== 0) {
                    return [
                        'ok' => false,
                        'error_code' => $curlErrno === 28 ? 'AI_PROVIDER_TIMEOUT' : 'AI_PROVIDER_CONNECTION_FAILED',
                        'error_message' => $curlError !== '' ? $curlError : 'provider request failed',
                        'http_status' => $status,
                    ];
                }

                $json = json_decode(is_string($raw) ? $raw : '', true);
                if (!is_array($json) && $status >= 200 && $status < 300) {
                    return [
                        'ok' => false,
                        'error_code' => 'AI_PROVIDER_INVALID_RESPONSE',
                        'error_message' => 'provider response is not valid json',
                        'http_status' => $status,
                    ];
                }

                if ($status < 200 || $status >= 300) {
                    $errorDetails = $this->extractProviderError($json);
                    return [
                        'ok' => false,
                        'error_code' => 'AI_PROVIDER_HTTP_ERROR',
                        'error_message' => $errorDetails['message'] !== '' ? $errorDetails['message'] : 'provider responded with non-2xx status',
                        'error_type' => $errorDetails['type'],
                        'http_status' => $status,
                    ];
                }

                return [
                    'ok' => true,
                    'json' => is_array($json) ? $json : [],
                    'http_status' => $status,
                ];
            }
        }

        return [
            'ok' => false,
            'error_code' => 'AI_PROVIDER_CLIENT_UNAVAILABLE',
            'error_message' => 'curl is not available',
            'http_status' => 0,
        ];
    }

    /**
     * @param list<string> $headers
     * @param array<string,mixed> $body
     * @return array{ok:bool,http_status?:int,json?:array<string,mixed>,error_code?:string,error_message?:string,error_type?:string}
     */
    private function postJson(string $url, array $headers, array $body, int $timeoutMs, array $provider = []): array
    {
        $runtime = $this->runtimeConfig($provider, $timeoutMs);
        $attempt = 0;
        $lastResponse = [
            'ok' => false,
            'error_code' => 'AI_PROVIDER_CONNECTION_FAILED',
            'error_message' => 'provider request failed',
            'http_status' => 0,
        ];

        while ($attempt < $runtime['max_attempts']) {
            $attempt++;
            $lastResponse = $this->sendPostJson($url, $headers, $body, $runtime['timeout_ms']);
            if ((bool)($lastResponse['ok'] ?? false)) {
                return $lastResponse;
            }
            if (!$this->isRetryable($lastResponse) || $attempt >= $runtime['max_attempts']) {
                return $lastResponse;
            }
            if ($runtime['backoff_ms'] > 0) {
                usleep($runtime['backoff_ms'] * 1000);
            }
        }

        return $lastResponse;
    }

    /**
     * @param list<string> $headers
     * @param array<string,mixed> $body
     * @return array{ok:bool,http_status?:int,json?:array<string,mixed>,error_code?:string,error_message?:string,error_type?:string}
     */
    private function sendPostJson(string $url, array $headers, array $body, int $timeoutMs): array
    {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'error_code' => 'AI_PROVIDER_CLIENT_UNAVAILABLE',
                'error_message' => 'curl is not available',
                'http_status' => 0,
            ];
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'error_code' => 'AI_PROVIDER_CONNECTION_FAILED',
                'error_message' => 'unable to init provider client',
                'http_status' => 0,
            ];
        }
        $rawBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($rawBody)) {
            $rawBody = '{}';
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, min($timeoutMs, 3000));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $raw = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($curlErrno !== 0) {
            return [
                'ok' => false,
                'error_code' => $curlErrno === 28 ? 'AI_PROVIDER_TIMEOUT' : 'AI_PROVIDER_CONNECTION_FAILED',
                'error_message' => $curlError !== '' ? $curlError : 'provider request failed',
                'http_status' => $status,
            ];
        }
        $json = json_decode(is_string($raw) ? $raw : '', true);
        if (!is_array($json) && $status >= 200 && $status < 300) {
            return [
                'ok' => false,
                'error_code' => 'AI_PROVIDER_INVALID_RESPONSE',
                'error_message' => 'provider response is not valid json',
                'http_status' => $status,
            ];
        }
        if ($status < 200 || $status >= 300) {
            $errorDetails = $this->extractProviderError($json);
            return [
                'ok' => false,
                'error_code' => 'AI_PROVIDER_HTTP_ERROR',
                'error_message' => $errorDetails['message'] !== '' ? $errorDetails['message'] : 'provider responded with non-2xx status',
                'error_type' => $errorDetails['type'],
                'http_status' => $status,
            ];
        }

        return [
            'ok' => true,
            'json' => is_array($json) ? $json : [],
            'http_status' => $status,
        ];
    }

    /** @param array<string,mixed> $json */
    private function extractCompletionText(array $json): string
    {
        $choices = is_array($json['choices'] ?? null) ? (array)$json['choices'] : [];
        foreach ($choices as $choice) {
            if (!is_array($choice)) {
                continue;
            }
            $content = trim((string)($choice['message']['content'] ?? $choice['text'] ?? ''));
            if ($content !== '') {
                return $content;
            }
            $reasoning = trim((string)($choice['message']['reasoning'] ?? ''));
            if ($reasoning !== '') {
                return $reasoning;
            }
            $reasoningDetails = $choice['message']['reasoning_details'] ?? null;
            if (is_array($reasoningDetails)) {
                foreach ($reasoningDetails as $detail) {
                    if (is_array($detail) && isset($detail['text'])) {
                        $text = trim((string)$detail['text']);
                        if ($text !== '') {
                            return $text;
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * @return array{timeout_ms:int,max_attempts:int,backoff_ms:int}
     */
    private function runtimeConfig(array $provider, int $fallbackTimeoutMs): array
    {
        $payload = $this->providerPayload($provider);

        $timeoutMs = (int)($provider['timeout_ms'] ?? ($payload['timeout_ms'] ?? $fallbackTimeoutMs));
        $timeoutMs = max(1000, min(120000, $timeoutMs));

        $phpMaxExecutionSeconds = (int)ini_get('max_execution_time');
        if ($phpMaxExecutionSeconds > 0) {
            $safeLimitMs = max(150000, ($phpMaxExecutionSeconds * 1000) - 3000);
            if ($safeLimitMs > 1000) {
                $timeoutMs = min($timeoutMs, $safeLimitMs);
            }
        }

        $attempts = (int)($provider['retry_attempts'] ?? ($payload['retry_attempts'] ?? 1));
        $attempts = max(1, min(5, $attempts));

        $backoffMs = (int)($provider['retry_backoff_ms'] ?? ($payload['retry_backoff_ms'] ?? 150));
        $backoffMs = max(0, min(2000, $backoffMs));

        return [
            'timeout_ms' => $timeoutMs,
            'max_attempts' => $attempts,
            'backoff_ms' => $backoffMs,
        ];
    }

    /** @param array<string,mixed> $provider @return array<string,mixed> */
    private function providerPayload(array $provider): array
    {
        $payload = $provider['provider_payload'] ?? null;
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /** @param array{error_code?:string,http_status?:int} $response */
    private function isRetryable(array $response): bool
    {
        $status = (int)($response['http_status'] ?? 0);
        if ($status >= 500 || $status === 429) {
            return true;
        }

        $code = (string)($response['error_code'] ?? '');
        return in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_CONNECTION_FAILED', 'AI_PROVIDER_CLIENT_UNAVAILABLE'], true);
    }

    /**
     * @param array{error_code?:string,error_message?:string,http_status?:int} $response
     * @return array{ok:false,code:string,message:string,latency_ms:int,http_status:int}
     */
    private function mapProviderError(array $response, int $latencyMs): array
    {
        $status = (int)($response['http_status'] ?? 0);
        $code = (string)($response['error_code'] ?? 'AI_PROVIDER_ERROR');
        if ($status === 401 || $status === 403) {
            $code = 'AI_PROVIDER_AUTH_FAILED';
        } elseif ($status === 402) {
            $code = 'AI_PROVIDER_INSUFFICIENT_CREDITS';
        } elseif ($status === 429) {
            $code = 'AI_PROVIDER_RATE_LIMITED';
        } elseif ($status >= 500) {
            $code = 'AI_PROVIDER_SERVER_ERROR';
        } elseif ($code === 'AI_PROVIDER_TIMEOUT' || $code === 'AI_PROVIDER_CONNECTION_FAILED' || $code === 'AI_PROVIDER_HTTP_ERROR' || $code === 'AI_PROVIDER_INVALID_RESPONSE') {
            // keep original code
        } else {
            $code = $code !== '' ? $code : 'AI_PROVIDER_UNAVAILABLE';
        }

        return [
            'ok' => false,
            'code' => $code,
            'message' => trim((string)($response['error_message'] ?? '')) !== '' ? trim((string)$response['error_message']) : 'Provider request failed',
            'provider_error_type' => (string)($response['error_type'] ?? ''),
            'latency_ms' => max(0, $latencyMs),
            'http_status' => $status,
        ];
    }

    /**
     * Validate that completion endpoint is also working (not only /models auth).
     * @param array<string,mixed> $provider
     * @return array{ok:bool,code?:string,message?:string,latency_ms?:int,http_status?:int,completion_http_status?:int,provider_error_type?:string}
     */
    private function completionProbe(array $provider, string $secret): array
    {
        $payload = [
            'intent_code' => 'provider_test_probe',
            'system_prompt' => 'Return exactly: ok',
            'user_prompt' => 'ping',
            'context' => [],
            'model' => trim((string)($provider['default_model'] ?? '')),
            'max_tokens' => 16,
            'temperature' => 0.0,
        ];
        $startedAt = microtime(true);
        $result = $this->completeText($provider, $secret, $payload);
        if (!(bool)($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'code' => (string)($result['code'] ?? 'AI_PROVIDER_TEST_FAILED'),
                'message' => trim((string)($result['message'] ?? 'Provider request failed')),
                'provider_error_type' => (string)($result['provider_error_type'] ?? ''),
                'latency_ms' => (int)($result['latency_ms'] ?? round((microtime(true) - $startedAt) * 1000)),
                'http_status' => (int)($result['http_status'] ?? 0),
            ];
        }

        return [
            'ok' => true,
            'completion_http_status' => (int)($result['http_status'] ?? 0),
        ];
    }

    /**
     * @param mixed $json
     * @return array{type:string,message:string}
     */
    private function extractProviderError(mixed $json): array
    {
        if (!is_array($json)) {
            return ['type' => '', 'message' => ''];
        }
        $error = $json['error'] ?? null;
        if (!is_array($error)) {
            return ['type' => '', 'message' => ''];
        }
        $type = trim((string)($error['type'] ?? ''));
        $message = trim((string)($error['message'] ?? ''));
        if ($message !== '' && function_exists('mb_substr')) {
            $message = mb_substr($message, 0, 240);
        } elseif ($message !== '') {
            $message = substr($message, 0, 240);
        }
        return ['type' => $type, 'message' => $message];
    }
}
