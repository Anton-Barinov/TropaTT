<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class OpenAiCompatibleProviderClient implements AiProviderClientInterface
{
    public function completeText(array $provider, string $secret, array $payload): array
    {
        $startedAt = microtime(true);
        if (isset($payload['_deadline_at'])) $provider['_deadline_at'] = (float)$payload['_deadline_at'];
        $url = $this->completionUrl($provider);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $secret,
        ];

        $model = trim((string)($payload['model'] ?? $provider['default_model'] ?? ''));
        if (isset($payload['messages']) && is_array($payload['messages']) && $payload['messages'] !== []) {
            $messages = $payload['messages'];
        } else {
            $userContent = (string)($payload['user_prompt'] ?? '');
            if (!empty($payload['context'])) {
                $userContent .= "\n\nContext:\n" . json_encode((array)$payload['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $messages = [
                ['role' => 'system', 'content' => (string)($payload['system_prompt'] ?? '')],
                ['role' => 'user', 'content' => $userContent],
            ];
        }

        $request = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float)($payload['temperature'] ?? $provider['temperature'] ?? 0.2),
        ];
        if (isset($payload['tools']) && is_array($payload['tools']) && $payload['tools'] !== []) {
            $request['tools'] = $payload['tools'];
            if (isset($payload['tool_choice'])) {
                $request['tool_choice'] = $payload['tool_choice'];
            }
        }
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

        // TROPATTCRM-630: honor the caller's explicit timeout (payload["timeout_ms"])
        // — see runtimeConfig(): the larger of provider row / requested value wins.
        $timeout = max(3000, (int)($provider['timeout_ms'] ?? 240000), (int)($payload['timeout_ms'] ?? 0));
        $timeout = min($timeout, 300000);
        $response = $this->postJson($url, $headers, $request, $timeout, $provider);
        // Some thinking-mode providers require this protocol field on every replayed
        // assistant turn. Legacy sessions did not retain it; recover those once.
        if ((int)($response['http_status'] ?? 0) === 400
            && str_contains((string)($response['error_message'] ?? ''), 'reasoning_content')) {
            foreach ($request['messages'] as &$message) {
                if (($message['role'] ?? '') === 'assistant' && !isset($message['reasoning_content'])) {
                    $message['reasoning_content'] = '';
                }
            }
            unset($message);
            $response = $this->postJson($url, $headers, $request, $timeout, $provider);
        }
        $latencyMs = (int)round((microtime(true) - $startedAt) * 1000);
        if (!(bool)($response['ok'] ?? false)) {
            return $this->mapProviderError($response, $latencyMs);
        }

        $json = is_array($response['json'] ?? null) ? (array)$response['json'] : [];
        if (($json['choices'][0]['finish_reason'] ?? '') === 'length') {
            return ['ok' => false, 'code' => 'AI_PROVIDER_OUTPUT_TRUNCATED', 'message' => 'Provider output exceeded its token budget',
                'latency_ms' => $latencyMs, 'http_status' => (int)($response['http_status'] ?? 0),
                'request_tokens' => (int)($json['usage']['prompt_tokens'] ?? 0), 'response_tokens' => (int)($json['usage']['completion_tokens'] ?? 0)];
        }
        $text = $this->extractCompletionText($json);
        $toolCalls = $this->extractToolCalls($json);

        if ($toolCalls === [] && $text !== '') {
            $extractedFromText = $this->extractToolCallsFromText($text);
            if ($extractedFromText['tool_calls'] !== []) {
                $toolCalls = $extractedFromText['tool_calls'];
                $text = $extractedFromText['cleaned_text'];
            }
        }

        if ($text === '' && $toolCalls === []) {
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
            'tool_calls' => $toolCalls,
            ...(array_key_exists('reasoning_content', (array)($json['choices'][0]['message'] ?? []))
                ? ['reasoning_content' => (string)$json['choices'][0]['message']['reasoning_content']] : []),
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
            return $this->joinApiUrl($baseUrl, '/v1/models');
        }

        $normalized = '/' . ltrim($apiPath, '/');
        if (str_contains($normalized, '/chat/completions')) {
            $normalized = str_replace('/chat/completions', '/models', $normalized);
        } elseif (!str_ends_with($normalized, '/models')) {
            $normalized = '/v1/models';
        }

        return $this->joinApiUrl($baseUrl, $normalized);
    }

    /** @param array<string,mixed> $provider */
    private function completionUrl(array $provider): string
    {
        $baseUrl = rtrim((string)($provider['base_url'] ?? ''), '/');
        $apiPath = trim((string)($provider['api_path'] ?? ''));
        if ($apiPath === '') {
            return $this->joinApiUrl($baseUrl, '/v1/chat/completions');
        }
        if ($apiPath[0] !== '/') {
            $apiPath = '/' . $apiPath;
        }

        return $this->joinApiUrl($baseUrl, $apiPath);
    }

    private function joinApiUrl(string $baseUrl, string $path): string
    {
        // A base URL may already end in /v1 (the common SDK configuration).
        if (preg_match('~/(v[0-9]+)$~', (string)parse_url($baseUrl, PHP_URL_PATH), $match)
            && str_starts_with($path, '/' . $match[1] . '/')) {
            $path = substr($path, strlen($match[1]) + 1);
        }
        return $baseUrl . $path;
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
            $remainingMs = isset($provider['_deadline_at']) ? (int)(($provider['_deadline_at'] - microtime(true)) * 1000) : $runtime['timeout_ms'];
            if ($remainingMs <= 0) return ['ok' => false, 'http_status' => 0, 'error_code' => 'AI_PROVIDER_TIMEOUT', 'error_message' => 'Request time budget exhausted'];
            $lastResponse = $this->sendGetJson($url, $headers, min($runtime['timeout_ms'], max(1, $remainingMs)), $provider);
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
     * @param array<string,mixed> $provider
     * @return array{ok:bool,http_status?:int,json?:array<string,mixed>,error_code?:string,error_message?:string,error_type?:string}
     */
    private function sendGetJson(string $url, array $headers, int $timeoutMs, array $provider = []): array
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
                $this->applyProxy($ch, $provider);
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
        $runtime = $this->runtimeConfig($provider, $timeoutMs, $timeoutMs);
        $attempt = 0;
        $lastResponse = [
            'ok' => false,
            'error_code' => 'AI_PROVIDER_CONNECTION_FAILED',
            'error_message' => 'provider request failed',
            'http_status' => 0,
        ];

        while ($attempt < $runtime['max_attempts']) {
            $attempt++;
            $remainingMs = isset($provider['_deadline_at']) ? (int)(($provider['_deadline_at'] - microtime(true)) * 1000) : $runtime['timeout_ms'];
            if ($remainingMs <= 0) return ['ok' => false, 'http_status' => 0, 'error_code' => 'AI_PROVIDER_TIMEOUT', 'error_message' => 'Request time budget exhausted'];
            $lastResponse = $this->sendPostJson($url, $headers, $body, min($runtime['timeout_ms'], max(1, $remainingMs)), $provider);
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
    private function sendPostJson(string $url, array $headers, array $body, int $timeoutMs, array $provider = []): array
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
        $this->applyProxy($ch, $provider);

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
     * @param array<string,mixed> $json
     * @return list<array{id:string,type:string,function:array{name:string,arguments:string}}>
     */
    private function extractToolCalls(array $json): array
    {
        $choices = is_array($json['choices'] ?? null) ? (array)$json['choices'] : [];
        foreach ($choices as $choice) {
            if (!is_array($choice)) {
                continue;
            }
            $message = $choice['message'] ?? null;
            if (!is_array($message)) {
                continue;
            }
            $rawToolCalls = $message['tool_calls'] ?? null;
            if (!is_array($rawToolCalls) || $rawToolCalls === []) {
                continue;
            }
            $toolCalls = [];
            foreach ($rawToolCalls as $tc) {
                if (!is_array($tc)) {
                    continue;
                }
                $func = $tc['function'] ?? [];
                if (!is_array($func) || empty($func['name'])) {
                    continue;
                }
                $toolCalls[] = [
                    'id' => (string)($tc['id'] ?? ('call_' . bin2hex(random_bytes(6)))),
                    'type' => (string)($tc['type'] ?? 'function'),
                    'function' => [
                        'name' => (string)$func['name'],
                        'arguments' => is_string($func['arguments'] ?? null)
                            ? $func['arguments']
                            : (string)json_encode($func['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }
            if ($toolCalls !== []) {
                return $toolCalls;
            }
        }

        return [];
    }

    /**
     * Fallback for models (like DeepSeek, Qwen, etc.) that emit tool calls in DSML or XML tags within content.
     *
     * @return array{tool_calls:list<array{id:string,type:string,function:array{name:string,arguments:string}}>,cleaned_text:string}
     */
    private function extractToolCallsFromText(string $text): array
    {
        $toolCalls = [];
        $cleaned = $text;

        // 1. DeepSeek DSML format: <｜｜DSML｜｜ invoke name="...">...
        if (preg_match_all('/<[|｜]{1,2}DSML[|｜]{1,2}\s*invoke\s+name=[\'"]([^\'"]+)[\'"]>([\s\S]*?)<\/[|｜]{1,2}DSML[|｜]{1,2}\s*invoke>/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $toolName = trim((string)$m[1]);
                $body = (string)$m[2];
                $args = [];
                if (preg_match_all('/<[|｜]{1,2}DSML[|｜]{1,2}\s*parameter\s+name=[\'"]([^\'"]+)[\'"](?:\s+[^>]*)?>([\s\S]*?)<\/[|｜]{1,2}DSML[|｜]{1,2}\s*parameter>/u', $body, $paramMatches, PREG_SET_ORDER)) {
                    foreach ($paramMatches as $pm) {
                        $pName = trim((string)$pm[1]);
                        $pVal = trim((string)$pm[2]);
                        $decoded = json_decode($pVal, true);
                        $args[$pName] = (json_last_error() === JSON_ERROR_NONE && !is_numeric($pVal)) ? $decoded : $pVal;
                    }
                }
                $toolCalls[] = [
                    'id' => 'call_' . bin2hex(random_bytes(6)),
                    'type' => 'function',
                    'function' => [
                        'name' => $toolName,
                        'arguments' => (string)json_encode($args, JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }
            $cleaned = (string)preg_replace('/<[|｜]{1,2}DSML[|｜]{1,2}\s*calls>[\s\S]*?<\/[|｜]{1,2}DSML[|｜]{1,2}\s*calls>/u', '', $cleaned);
            $cleaned = (string)preg_replace('/<[|｜]{1,2}DSML[|｜]{1,2}\s*invoke[\s\S]*?<\/[|｜]{1,2}DSML[|｜]{1,2}\s*invoke>/u', '', $cleaned);
            return ['tool_calls' => $toolCalls, 'cleaned_text' => trim($cleaned)];
        }

        // 2. Standard <tool_call> JSON format
        if (preg_match_all('/<tool_call>\s*(\{[\s\S]*?\})\s*<\/tool_call>/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $rawJson = trim((string)$m[1]);
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded) && !empty($decoded['name'])) {
                    $args = $decoded['arguments'] ?? [];
                    $toolCalls[] = [
                        'id' => 'call_' . bin2hex(random_bytes(6)),
                        'type' => 'function',
                        'function' => [
                            'name' => (string)$decoded['name'],
                            'arguments' => is_string($args) ? $args : (string)json_encode($args, JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
            }
            if ($toolCalls !== []) {
                $cleaned = (string)preg_replace('/<tool_call>[\s\S]*?<\/tool_call>/i', '', $cleaned);
                return ['tool_calls' => $toolCalls, 'cleaned_text' => trim($cleaned)];
            }
        }

        // 3. XML <invoke name="..."> format
        if (preg_match_all('/<invoke\s+name=[\'"]([^\'"]+)[\'"]>([\s\S]*?)<\/invoke>/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $toolName = trim((string)$m[1]);
                $body = (string)$m[2];
                $args = [];
                if (preg_match_all('/<parameter\s+name=[\'"]([^\'"]+)[\'"](?:\s+[^>]*)?>([\s\S]*?)<\/parameter>/i', $body, $paramMatches, PREG_SET_ORDER)) {
                    foreach ($paramMatches as $pm) {
                        $pName = trim((string)$pm[1]);
                        $pVal = trim((string)$pm[2]);
                        $decoded = json_decode($pVal, true);
                        $args[$pName] = (json_last_error() === JSON_ERROR_NONE && !is_numeric($pVal)) ? $decoded : $pVal;
                    }
                }
                $toolCalls[] = [
                    'id' => 'call_' . bin2hex(random_bytes(6)),
                    'type' => 'function',
                    'function' => [
                        'name' => $toolName,
                        'arguments' => (string)json_encode($args, JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }
            $cleaned = (string)preg_replace('/<invoke[\s\S]*?<\/invoke>/i', '', $cleaned);
            return ['tool_calls' => $toolCalls, 'cleaned_text' => trim($cleaned)];
        }

        return ['tool_calls' => [], 'cleaned_text' => $text];
    }

    /**
     * @return array{timeout_ms:int,max_attempts:int,backoff_ms:int}
     */
    private function runtimeConfig(array $provider, int $fallbackTimeoutMs, ?int $requestedTimeoutMs = null): array
    {
        $payload = $this->providerPayload($provider);

        // TROPATTCRM-630: a caller-passed timeout (payload["timeout_ms"], e.g. the
        // idea interview asking for 60s) used to be silently capped by a stale
        // provider row (demo had 30000) — long completions died mid-stream and
        // surfaced as AI_PARSE_FAILED. The larger of the two wins; the 300s
        // absolute ceiling below still applies either way.
        $providerTimeoutMs = (int)($provider['timeout_ms'] ?? ($payload['timeout_ms'] ?? $fallbackTimeoutMs));
        $timeoutMs = max($providerTimeoutMs, max(0, (int)($requestedTimeoutMs ?? 0)));
        $timeoutMs = max(1000, min(300000, $timeoutMs));

        $phpMaxExecutionSeconds = (int)ini_get('max_execution_time');
        if ($phpMaxExecutionSeconds > 0) {
            $safeLimitMs = max(1000, ($phpMaxExecutionSeconds * 1000) - 3000);
            if ($safeLimitMs > 1000) {
                $timeoutMs = min($timeoutMs, $safeLimitMs);
            }
        }

        $attempts = (int)($provider['retry_attempts'] ?? ($payload['retry_attempts'] ?? 2));
        $attempts = max(1, min(5, $attempts));

        $backoffMs = (int)($provider['retry_backoff_ms'] ?? ($payload['retry_backoff_ms'] ?? 500));
        $backoffMs = max(0, min(3000, $backoffMs));

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

    /** @param mixed $ch @param array<string,mixed> $provider */
    private function applyProxy(mixed $ch, array $provider): void
    {
        AiProxyUrl::applyToCurl($ch, $this->providerPayload($provider));
    }

    /** @param array{error_code?:string,http_status?:int} $response */
    private function isRetryable(array $response): bool
    {
        $status = (int)($response['http_status'] ?? 0);
        if ($status >= 500 || $status === 429) {
            return true;
        }

        $code = (string)($response['error_code'] ?? '');
        return in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_CONNECTION_FAILED', 'AI_PROVIDER_CLIENT_UNAVAILABLE', 'AI_PROVIDER_INVALID_RESPONSE'], true);
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
            'max_tokens' => 512,
            'temperature' => 0.0,
        ];
        $startedAt = microtime(true);
        $result = $this->completeText($provider, $secret, $payload);
        if (($result['code'] ?? '') === 'AI_PROVIDER_OUTPUT_TRUNCATED') {
            $payload['max_tokens'] = 2048;
            $result = $this->completeText($provider, $secret, $payload);
        }
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
