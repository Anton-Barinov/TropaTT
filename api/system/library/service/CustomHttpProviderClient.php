<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class CustomHttpProviderClient implements AiProviderClientInterface
{
    public function completeText(array $provider, string $secret, array $payload): array
    {
        $client = new OpenAiCompatibleProviderClient();
        return $client->completeText($provider, $secret, $payload);
    }

    public function testConnection(array $provider, string $secret): array
    {
        $startedAt = microtime(true);
        $headers = $this->buildHeaders($secret);
        $response = $this->getJson($this->testUrl($provider), $headers, (int)($provider['timeout_ms'] ?? 30000), $provider);
        if (!(bool)($response['ok'] ?? false)) {
            return $this->mapProviderError($response, (int)round((microtime(true) - $startedAt) * 1000));
        }

        return [
            'ok' => true,
            'latency_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'http_status' => (int)($response['http_status'] ?? 0),
            'message' => 'ok',
        ];
    }

    public function listModels(array $provider, string $secret): array
    {
        $headers = $this->buildHeaders($secret);
        $response = $this->getJson($this->modelsUrl($provider), $headers, (int)($provider['timeout_ms'] ?? 30000), $provider);
        if (!(bool)($response['ok'] ?? false)) {
            return $this->mapProviderError($response, 0);
        }

        $payload = $response['json'] ?? null;
        $rows = [];
        if (is_array($payload['items'] ?? null)) {
            $rows = (array)$payload['items'];
        } elseif (is_array($payload['models'] ?? null)) {
            $rows = (array)$payload['models'];
        } elseif (is_array($payload['data'] ?? null)) {
            $rows = (array)$payload['data'];
        } elseif (is_array($payload)) {
            $rows = $payload;
        }

        $items = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $id = trim($row);
                if ($id === '') {
                    continue;
                }
                $items[] = ['id' => $id, 'title' => $id];
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)($row['id'] ?? $row['name'] ?? $row['model'] ?? ''));
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
    private function testUrl(array $provider): string
    {
        $baseUrl = rtrim((string)($provider['base_url'] ?? ''), '/');
        $apiPath = trim((string)($provider['api_path'] ?? ''));
        if ($apiPath === '') {
            return $baseUrl . '/health';
        }

        return $baseUrl . '/' . ltrim($apiPath, '/');
    }

    /**
     * @param array<string,mixed> $provider
     */
    private function modelsUrl(array $provider): string
    {
        $baseUrl = rtrim((string)($provider['base_url'] ?? ''), '/');
        $apiPath = trim((string)($provider['api_path'] ?? ''));
        if ($apiPath === '') {
            return $baseUrl . '/models';
        }

        $normalized = '/' . ltrim($apiPath, '/');
        if (!str_ends_with($normalized, '/models')) {
            $normalized = rtrim($normalized, '/') . '/models';
        }

        return $baseUrl . $normalized;
    }

    /** @return list<string> */
    private function buildHeaders(string $secret): array
    {
        $headers = ['Accept: application/json'];
        $secret = trim($secret);
        if ($secret !== '') {
            $headers[] = 'Authorization: Bearer ' . $secret;
        }

        return $headers;
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
     * @return array{ok:bool,http_status?:int,json?:array<string,mixed>,error_code?:string,error_message?:string}
     */
    private function sendGetJson(string $url, array $headers, int $timeoutMs): array
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
        curl_close($ch);

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
            return [
                'ok' => false,
                'error_code' => 'AI_PROVIDER_HTTP_ERROR',
                'error_message' => 'provider responded with non-2xx status',
                'http_status' => $status,
            ];
        }

        return [
            'ok' => true,
            'json' => is_array($json) ? $json : [],
            'http_status' => $status,
        ];
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
            $safeLimitMs = ($phpMaxExecutionSeconds * 1000) - 5000;
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
        } elseif ($code === 'AI_PROVIDER_TIMEOUT') {
            $code = 'AI_PROVIDER_TIMEOUT';
        } else {
            $code = 'AI_PROVIDER_TEST_FAILED';
        }

        return [
            'ok' => false,
            'code' => $code,
            'message' => 'Provider request failed',
            'latency_ms' => max(0, $latencyMs),
            'http_status' => $status,
        ];
    }
}
