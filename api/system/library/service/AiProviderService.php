<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;
use Api\System\Library\Config;
use Api\System\Library\Http\Request;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\UrlSafetyValidator;

final class AiProviderService
{
    private UrlSafetyValidator $urlSafety;

    public function __construct(
        private readonly AiProviderRepository $providers,
        private readonly SettingService $settings,
        private readonly JsonLogger $logger,
        private readonly Config $config,
        private readonly AiProviderClientFactory $providerClientFactory,
        private readonly Request $request
    ) {
        $this->urlSafety = new UrlSafetyValidator();
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->providers->list($filters);

        return [
            'items' => array_map(fn(array $row): array => $this->normalize($row), $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function get(string $publicId): array
    {
        $row = $this->providers->findByPublicId($publicId);
        if (!$row) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        return ['ok' => true, 'provider' => $this->normalize($row)];
    }

    public function create(array $input, array $actor): array
    {
        $validation = $this->validateBaseUrl((string)($input['base_url'] ?? ''));
        if (!$validation['ok']) {
            return $validation;
        }
        $headersValidation = $this->validateCustomHeaders($input['extra_headers'] ?? []);
        if (!$headersValidation['ok']) {
            $this->logForbiddenHeaderAttempt($actor, $input['extra_headers'] ?? []);
            return $headersValidation;
        }

        $isDefault = $this->toBool($input['is_default'] ?? false);
        $now = gmdate('Y-m-d H:i:s');
        $actorId = (int)($actor['id'] ?? 0);
        $publicId = $this->providers->create([
            'provider_code' => trim((string)($input['provider_code'] ?? 'openai_compatible')),
            'title' => trim((string)($input['title'] ?? 'AI Provider')),
            'base_url' => trim((string)($input['base_url'] ?? '')),
            'api_path' => trim((string)($input['api_path'] ?? '/v1/chat/completions')),
            'default_model' => trim((string)($input['default_model'] ?? '')),
            'timeout_ms' => max(1000, (int)($input['timeout_ms'] ?? 30000)),
            'max_tokens' => max(1, (int)($input['max_tokens'] ?? 2000)),
            'temperature' => trim((string)($input['temperature'] ?? '0.2')),
            'extra_headers' => $this->encodeJson((array)($input['extra_headers'] ?? [])),
            'provider_payload' => $this->encodeJson((array)($input['provider_payload'] ?? [])),
            'is_active' => $this->toBool($input['is_active'] ?? true) ? 1 : 0,
            'is_default' => $isDefault ? 1 : 0,
            'created_by_user_id' => $actorId > 0 ? $actorId : null,
            'updated_by_user_id' => $actorId > 0 ? $actorId : null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        if ($isDefault) {
            $this->providers->unsetDefaultForOthers($publicId);
        }

        $provider = $this->providers->findByPublicId($publicId);
        $this->logger->audit([
            'action' => 'ai_provider_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_provider',
            'entity_public_id' => $publicId,
            'provider_code' => $provider['provider_code'] ?? '',
            'base_url' => $provider['base_url'] ?? '',
        ]);

        return ['ok' => true, 'provider' => $provider ? $this->normalize($provider) : null];
    }

    public function update(string $publicId, array $input, array $actor): array
    {
        $provider = $this->providers->findByPublicId($publicId);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        if (array_key_exists('base_url', $input)) {
            $validation = $this->validateBaseUrl((string)$input['base_url']);
            if (!$validation['ok']) {
                return $validation;
            }
        }
        if (array_key_exists('extra_headers', $input)) {
            $headersValidation = $this->validateCustomHeaders($input['extra_headers']);
            if (!$headersValidation['ok']) {
                $this->logForbiddenHeaderAttempt($actor, $input['extra_headers']);
                return $headersValidation;
            }
        }

        $set = [];
        foreach (['provider_code', 'title', 'base_url', 'api_path', 'default_model', 'temperature'] as $field) {
            if (array_key_exists($field, $input)) {
                $set[$field] = trim((string)$input[$field]);
            }
        }
        if (array_key_exists('timeout_ms', $input)) {
            $set['timeout_ms'] = max(1000, (int)$input['timeout_ms']);
        }
        if (array_key_exists('max_tokens', $input)) {
            $set['max_tokens'] = max(1, (int)$input['max_tokens']);
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = $this->toBool($input['is_active']) ? 1 : 0;
        }
        if (array_key_exists('is_default', $input)) {
            $set['is_default'] = $this->toBool($input['is_default']) ? 1 : 0;
        }
        if (array_key_exists('extra_headers', $input) && is_array($input['extra_headers'])) {
            $set['extra_headers'] = $this->encodeJson($input['extra_headers']);
        }
        if (array_key_exists('provider_payload', $input) && is_array($input['provider_payload'])) {
            $set['provider_payload'] = $this->encodeJson($input['provider_payload']);
        }
        if ($set === []) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NO_CHANGES'];
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $actorId = (int)($actor['id'] ?? 0);
        $set['updated_by_user_id'] = $actorId > 0 ? $actorId : null;

        $this->providers->updateByPublicId($publicId, $set);
        if (array_key_exists('base_url', $set) || array_key_exists('api_path', $set) || array_key_exists('default_model', $set) || array_key_exists('provider_code', $set)) {
            $payloadRaw = $provider['provider_payload'] ?? '{}';
            $payload = is_array($payloadRaw) ? (array)$payloadRaw : $this->decodeJson((string)$payloadRaw);
            $health = is_array($payload['health'] ?? null) ? (array)$payload['health'] : [];
            $health['needs_recheck'] = true;
            $health['config_changed_at'] = gmdate('c');
            $payload['health'] = $health;
            $this->providers->updateByPublicId($publicId, [
                'provider_payload' => $this->encodeJson($payload),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by_user_id' => $actorId > 0 ? $actorId : null,
            ]);
        }

        if (($set['is_default'] ?? 0) === 1) {
            $this->providers->unsetDefaultForOthers($publicId);
        }

        $updated = $this->providers->findByPublicId($publicId);
        $this->logger->audit([
            'action' => 'ai_provider_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_provider',
            'entity_public_id' => $publicId,
            'changes' => array_keys($set),
        ]);

        return ['ok' => true, 'provider' => $updated ? $this->normalize($updated) : null];
    }

    public function delete(string $publicId, array $actor): array
    {
        $provider = $this->providers->findByPublicId($publicId);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $ok = $this->providers->softDeleteByPublicId($publicId, gmdate('Y-m-d H:i:s'), $actorId);
        if (!$ok) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_DELETE_FAILED'];
        }

        $this->providers->deleteSecret((int)($provider['id'] ?? 0), $actorId);

        $this->logger->audit([
            'action' => 'ai_provider_deleted',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_provider',
            'entity_public_id' => $publicId,
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'ai_provider_secret_invalidated_on_delete',
            'ip' => null,
            'user_agent' => null,
            'details' => [
                'provider_public_id' => $publicId,
                'has_secret' => false,
            ],
        ]);

        return ['ok' => true];
    }

    public function upsertSecret(string $providerPublicId, string $secret, array $actor): array
    {
        $provider = $this->providers->findByPublicId($providerPublicId);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        $secret = trim($secret);
        if ($secret === '') {
            return ['ok' => false, 'code' => 'AI_PROVIDER_SECRET_REQUIRED'];
        }
        $last4 = $this->secretLast4($secret);

        $actorId = (int)($actor['id'] ?? 0);
        try {
            $encryptedSecret = $this->encryptSecret($secret);
            $keyHint = $this->secretKeyHint();
        } catch (\Throwable) {
            return ['ok' => false, 'code' => 'AI_SECRET_KEY_NOT_CONFIGURED'];
        }

        $this->providers->setSecret(
            providerId: (int)$provider['id'],
            encryptedSecret: $encryptedSecret,
            keyHint: $keyHint,
            actorUserId: $actorId
        );

        $this->logger->audit([
            'action' => 'ai_provider_secret_rotated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_provider',
            'entity_public_id' => $providerPublicId,
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'ai_provider_secret_update',
            'ip' => null,
            'user_agent' => null,
            'details' => [
                'provider_public_id' => $providerPublicId,
                'has_secret' => true,
            ],
        ]);

        return [
            'ok' => true,
            'credential' => [
                'provider_public_id' => $providerPublicId,
                'is_configured' => true,
                'masked_value' => '***',
                'credential_last4' => $last4,
            ],
        ];
    }

    public function deleteSecret(string $providerPublicId, array $actor): array
    {
        $provider = $this->providers->findByPublicId($providerPublicId);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        $this->providers->deleteSecret((int)$provider['id'], (int)($actor['id'] ?? 0));
        $this->logger->audit([
            'action' => 'ai_provider_secret_deleted',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_provider',
            'entity_public_id' => $providerPublicId,
        ]);

        return [
            'ok' => true,
            'credential' => [
                'provider_public_id' => $providerPublicId,
                'is_configured' => false,
                'masked_value' => null,
                'credential_last4' => null,
            ],
        ];
    }

    public function testConnection(string $providerPublicId, array $actor): array
    {
        $provider = $this->providers->findByPublicId($providerPublicId);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        $secret = $this->decryptedSecretByProvider((int)($provider['id'] ?? 0));
        if ($secret === null) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_SECRET_NOT_CONFIGURED'];
        }

        $client = $this->providerClientFactory->forProvider($provider);
        $result = $client->testConnection($provider, $secret);
        if (!(bool)($result['ok'] ?? false)) {
            $error = $this->sanitizeProviderError($result);
            $code = (string)$error['code'];
            $this->persistProviderHealthSnapshot($provider, [
                'status' => 'error',
                'last_checked_at' => gmdate('c'),
                'last_error_at' => gmdate('c'),
                'last_error_code' => $code,
                'last_latency_ms' => (int)($error['provider_error']['http_status'] ?? 0),
                'auth_check_ok' => false,
                'model_check_ok' => false,
                'completion_check_ok' => false,
                'needs_recheck' => false,
            ], $actor);
            $this->logger->security([
                'actor_public_id' => $actor['public_id'] ?? null,
                'event_type' => 'ai_provider_test_failed',
                'details' => [
                    'provider_public_id' => $providerPublicId,
                    'code' => $code,
                    'http_status' => (int)($error['provider_error']['http_status'] ?? 0),
                ],
            ]);
            return $error;
        }

        $this->logger->audit([
            'action' => 'ai_provider_test_success',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_provider',
            'entity_public_id' => $providerPublicId,
            'http_status' => (int)($result['http_status'] ?? 200),
            'latency_ms' => (int)($result['latency_ms'] ?? 0),
        ]);
        $this->persistProviderHealthSnapshot($provider, [
            'status' => 'ok',
            'last_checked_at' => gmdate('c'),
            'last_success_at' => gmdate('c'),
            'last_error_code' => null,
            'last_latency_ms' => (int)($result['latency_ms'] ?? 0),
            'auth_check_ok' => true,
            'model_check_ok' => true,
            'completion_check_ok' => true,
            'needs_recheck' => false,
        ], $actor);

        return [
            'ok' => true,
            'result' => [
                'provider_public_id' => $providerPublicId,
                'status' => 'ok',
                'http_status' => (int)($result['http_status'] ?? 200),
                'latency_ms' => (int)($result['latency_ms'] ?? 0),
                'message' => 'Provider is reachable',
            ],
        ];
    }

    public function listModels(?string $providerPublicId): array
    {
        $provider = null;
        if ($providerPublicId !== null && trim($providerPublicId) !== '') {
            $provider = $this->providers->findByPublicId($providerPublicId);
            if (!$provider) {
                return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
            }
        } else {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
            if (!$provider) {
                return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
            }
        }

        $secret = $this->decryptedSecretByProvider((int)($provider['id'] ?? 0));
        if ($secret === null) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_SECRET_NOT_CONFIGURED'];
        }

        $client = $this->providerClientFactory->forProvider($provider);
        $result = $client->listModels($provider, $secret);
        if (!(bool)($result['ok'] ?? false)) {
            return $this->sanitizeProviderError($result);
        }

        return [
            'ok' => true,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'items' => is_array($result['items'] ?? null) ? $result['items'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function completeText(?string $providerPublicId, array $payload): array
    {
        $runtimeMode = $this->runtimeMode();
        $provider = null;
        if ($providerPublicId !== null && trim($providerPublicId) !== '') {
            $provider = $this->providers->findByPublicId($providerPublicId);
            if (!$provider) {
                return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
            }
        } else {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
            if (!$provider) {
                return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
            }
        }
        if (!(bool)($provider['is_active'] ?? false)) {
            $this->logCompletionDiag($provider, $payload, 'failed_preflight', ['code' => 'AI_PROVIDER_NOT_CONFIGURED', 'http_status' => 0, 'latency_ms' => 0], false, false);
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $isMockProvider = $this->isMockProvider($provider);
        if ($runtimeMode === 'mock' && !$isMockProvider) {
            return ['ok' => false, 'code' => 'AI_MOCK_MODE_PROVIDER_REQUIRED'];
        }
        if ($runtimeMode === 'real' && $isMockProvider) {
            return ['ok' => false, 'code' => 'AI_REAL_MODE_PROVIDER_REQUIRED'];
        }

        $secret = $this->decryptedSecretByProvider((int)($provider['id'] ?? 0));
        if ($secret === null) {
            $this->logCompletionDiag($provider, $payload, 'failed_preflight', ['code' => 'AI_PROVIDER_SECRET_NOT_CONFIGURED', 'http_status' => 0, 'latency_ms' => 0], false, false);
            return ['ok' => false, 'code' => 'AI_PROVIDER_SECRET_NOT_CONFIGURED'];
        }

        $client = $this->providerClientFactory->forProvider($provider);
        $mockUsed = $isMockProvider;
        $result = $client->completeText($provider, $secret, $payload);
        $outboundAttempted = !$mockUsed;
        if (!(bool)($result['ok'] ?? false)) {
            $this->logCompletionDiag(
                $provider,
                $payload,
                'failed',
                [
                    'code' => (string)($result['code'] ?? ''),
                    'http_status' => (int)($result['http_status'] ?? 0),
                    'latency_ms' => (int)($result['latency_ms'] ?? 0),
                ],
                $outboundAttempted,
                $mockUsed
            );
            return $this->sanitizeProviderError($result);
        }

        $this->logCompletionDiag(
            $provider,
            $payload,
            'ok',
            [
                'code' => 'OK',
                'http_status' => (int)($result['http_status'] ?? 0),
                'latency_ms' => (int)($result['latency_ms'] ?? 0),
            ],
            $outboundAttempted,
            $mockUsed
        );
        if (!$mockUsed) {
            $this->persistProviderHealthSnapshot($provider, [
                'status' => 'ok',
                'last_real_ai_success_at' => gmdate('c'),
                'last_completion_success_at' => gmdate('c'),
                'last_real_ai_request_id' => (string)$this->request->requestId,
                'last_error_code' => null,
                'needs_recheck' => false,
            ], []);
        }

        return [
            'ok' => true,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'runtime_mode' => $runtimeMode,
            'text' => trim((string)($result['text'] ?? '')),
            'request_tokens' => (int)($result['request_tokens'] ?? 0),
            'response_tokens' => (int)($result['response_tokens'] ?? 0),
            'total_tokens' => (int)($result['total_tokens'] ?? 0),
            'latency_ms' => (int)($result['latency_ms'] ?? 0),
            'http_status' => (int)($result['http_status'] ?? 0),
        ];
    }

    private function validateBaseUrl(string $baseUrl): array
    {
        $strict = in_array(strtolower((string)$this->config->get('default.app.env', 'prod')), ['prod', 'production'], true)
            && (bool)$this->config->get('ai.provider.block_private_networks_in_production', true);

        $validated = $this->urlSafety->validateProviderUrl(
            $baseUrl,
            $strict,
            (array)$this->config->get('ai.provider.allowed_schemes', ['https', 'http'])
        );
        if (!(bool)($validated['ok'] ?? false)) {
            return $validated;
        }

        $scheme = strtolower((string)(parse_url(trim($baseUrl), PHP_URL_SCHEME) ?? ''));
        if ($scheme === 'http' && !$this->allowInsecureLocalDevUrl($baseUrl, $strict)) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_SCHEME_NOT_ALLOWED'];
        }

        return ['ok' => true, 'code' => 'OK'];
    }

    private function validateCustomHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            return ['ok' => true];
        }

        $forbidden = [
            'authorization',
            'proxy-authorization',
            'x-api-key',
            'cookie',
            'set-cookie',
            'host',
            'x-forwarded-for',
            'x-forwarded-host',
            'x-forwarded-proto',
            'x-real-ip',
            'forwarded',
        ];

        foreach (array_keys($headers) as $headerName) {
            $name = strtolower(trim((string)$headerName));
            if (in_array($name, $forbidden, true)) {
                return [
                    'ok' => false,
                    'code' => 'AI_PROVIDER_HEADERS_FORBIDDEN',
                    'field_errors' => [
                        ['field' => 'extra_headers', 'message' => 'Forbidden header: ' . $name],
                    ],
                ];
            }
        }

        return ['ok' => true];
    }

    private function logForbiddenHeaderAttempt(array $actor, mixed $headers): void
    {
        if (!is_array($headers)) {
            return;
        }

        $names = [];
        foreach (array_keys($headers) as $headerName) {
            $names[] = strtolower(trim((string)$headerName));
        }
        $names = array_values(array_unique(array_filter($names, static fn(string $name): bool => $name !== '')));

        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'ai_provider_forbidden_headers_rejected',
            'details' => [
                'header_names' => $names,
                'request_id' => (string)$this->request->requestId,
                'correlation_id' => (string)$this->request->correlationId,
            ],
        ]);
    }

    private function allowInsecureLocalDevUrl(string $baseUrl, bool $strictMode): bool
    {
        if ($strictMode) {
            return false;
        }

        $host = strtolower(trim((string)(parse_url(trim($baseUrl), PHP_URL_HOST) ?? '')));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function normalize(array $provider): array
    {
        $providerId = (int)($provider['id'] ?? 0);
        $hasSecret = $providerId > 0 ? $this->providers->hasSecret($providerId) : false;
        $decryptedSecret = $hasSecret ? $this->decryptedSecretByProvider($providerId) : null;
        $secretLast4 = $decryptedSecret !== null ? $this->secretLast4($decryptedSecret) : null;
        $providerPayload = $this->decodeJson((string)($provider['provider_payload'] ?? '{}'));
        $health = is_array($providerPayload['health'] ?? null) ? (array)$providerPayload['health'] : [];

        return [
            'public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'title' => (string)($provider['title'] ?? ''),
            'base_url' => (string)($provider['base_url'] ?? ''),
            'api_path' => (string)($provider['api_path'] ?? ''),
            'default_model' => (string)($provider['default_model'] ?? ''),
            'timeout_ms' => (int)($provider['timeout_ms'] ?? 0),
            'max_tokens' => (int)($provider['max_tokens'] ?? 0),
            'temperature' => (string)($provider['temperature'] ?? ''),
            'extra_headers' => $this->decodeJson((string)($provider['extra_headers'] ?? '{}')),
            'provider_payload' => $providerPayload,
            'is_active' => (int)($provider['is_active'] ?? 0) === 1,
            'is_default' => (int)($provider['is_default'] ?? 0) === 1,
            'credential_is_configured' => $hasSecret,
            'credential_masked_value' => $hasSecret ? '***' : null,
            'credential_last4' => $secretLast4,
            'provider_health' => [
                'status' => (string)($health['status'] ?? 'unchecked'),
                'needs_recheck' => (bool)($health['needs_recheck'] ?? false),
                'last_checked_at' => (string)($health['last_checked_at'] ?? ''),
                'last_success_at' => (string)($health['last_success_at'] ?? ''),
                'last_real_ai_success_at' => (string)($health['last_real_ai_success_at'] ?? ''),
                'last_completion_success_at' => (string)($health['last_completion_success_at'] ?? ''),
                'last_error_at' => (string)($health['last_error_at'] ?? ''),
                'last_error_code' => (string)($health['last_error_code'] ?? ''),
                'last_latency_ms' => (int)($health['last_latency_ms'] ?? 0),
                'last_real_ai_request_id' => (string)($health['last_real_ai_request_id'] ?? ''),
            ],
            'created_at' => (string)($provider['created_at'] ?? ''),
            'updated_at' => (string)($provider['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $updates
     * @param array<string,mixed> $actor
     */
    private function persistProviderHealthSnapshot(array $provider, array $updates, array $actor): void
    {
        $publicId = trim((string)($provider['public_id'] ?? ''));
        if ($publicId === '') {
            return;
        }

        $rawPayload = $provider['provider_payload'] ?? '{}';
        $payload = is_array($rawPayload) ? (array)$rawPayload : $this->decodeJson((string)$rawPayload);
        $health = is_array($payload['health'] ?? null) ? (array)$payload['health'] : [];

        foreach ($updates as $key => $value) {
            $health[(string)$key] = $value;
        }
        $payload['health'] = $health;

        $this->providers->updateByPublicId($publicId, [
            'provider_payload' => $this->encodeJson($payload),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'updated_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
        ]);
    }

    private function secretLast4(string $secret): ?string
    {
        $normalized = trim($secret);
        if ($normalized === '') {
            return null;
        }
        $len = strlen($normalized);
        return $len > 4 ? substr($normalized, -4) : $normalized;
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '{}';
    }

    private function decodeJson(string $raw): array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function toBool(mixed $value): bool
    {
        return (string)$value === '1' || (string)$value === 'true' || $value === true || (int)$value === 1;
    }

    /**
     * @param array<string,mixed> $result
     * @return array{
     *   ok:false,
     *   code:string,
     *   provider_error:array{category:string,retryable:bool,http_status:int}
     * }
     */
    private function sanitizeProviderError(array $result): array
    {
        $rawCode = strtoupper(trim((string)($result['code'] ?? '')));
        $httpStatus = max(0, (int)($result['http_status'] ?? 0));
        $originalCode = $rawCode !== '' ? $rawCode : 'AI_PROVIDER_UNAVAILABLE';

        $category = 'unavailable';
        $code = $originalCode;
        $retryable = true;
        if ($rawCode === 'AI_PROVIDER_SECRET_NOT_CONFIGURED' || $rawCode === 'AI_PROVIDER_NOT_CONFIGURED') {
            $category = 'configuration';
            $code = 'AI_PROVIDER_NOT_CONFIGURED';
            $retryable = false;
        } elseif ($rawCode === 'AI_PROVIDER_TIMEOUT' || $rawCode === 'AI_PROVIDER_CONNECTION_FAILED') {
            $category = 'network';
            $code = $rawCode;
            $retryable = true;
        } elseif ($rawCode === 'AI_PROVIDER_AUTH_FAILED') {
            $category = 'auth';
            $code = 'AI_PROVIDER_AUTH_FAILED';
            $retryable = false;
        } elseif ($rawCode === 'AI_PROVIDER_RATE_LIMITED') {
            $category = 'rate_limited';
            $code = 'AI_PROVIDER_RATE_LIMITED';
            $retryable = true;
        } elseif ($rawCode === 'AI_PROVIDER_INSUFFICIENT_CREDITS') {
            $category = 'billing';
            $code = 'AI_PROVIDER_INSUFFICIENT_CREDITS';
            $retryable = false;
        } elseif ($rawCode === 'AI_PROVIDER_SERVER_ERROR') {
            $category = 'provider_error';
            $code = 'AI_PROVIDER_SERVER_ERROR';
            $retryable = true;
        } elseif ($rawCode === 'AI_PROVIDER_HTTP_ERROR') {
            $category = 'http_error';
            $code = 'AI_PROVIDER_HTTP_ERROR';
            $retryable = true;
        } elseif ($rawCode === 'AI_PROVIDER_INVALID_RESPONSE') {
            $category = 'invalid_response';
            $code = 'AI_PROVIDER_INVALID_RESPONSE';
            $retryable = true;
        }

        return [
            'ok' => false,
            'code' => $code,
            'provider_error' => [
                'category' => $category,
                'retryable' => $retryable,
                'http_status' => $httpStatus,
            ],
            'message' => trim((string)($result['message'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $payload
     * @param array{code:string,http_status:int,latency_ms:int} $resultMeta
     */
    private function logCompletionDiag(array $provider, array $payload, string $status, array $resultMeta, bool $outboundAttempted, bool $mockUsed): void
    {
        $baseUrl = trim((string)($provider['base_url'] ?? ''));
        $host = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?? ''));
        $this->logger->audit([
            'action' => 'ai_provider_complete_text',
            'request_id' => $this->request->requestId,
            'correlation_id' => $this->request->correlationId,
            'intent_code' => (string)($payload['intent_code'] ?? ''),
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'model' => trim((string)($payload['model'] ?? '')),
            'endpoint_host' => $host,
            'status' => $status,
            'error_code' => (string)($resultMeta['code'] ?? ''),
            'http_status' => (int)($resultMeta['http_status'] ?? 0),
            'latency_ms' => (int)($resultMeta['latency_ms'] ?? 0),
            'outbound_attempted' => $outboundAttempted,
            'mock_used' => $mockUsed,
        ]);
    }

    /** @param array<string,mixed> $provider */
    private function isMockProvider(array $provider): bool
    {
        $code = strtolower(trim((string)($provider['provider_code'] ?? '')));
        return in_array($code, ['mock', 'fake'], true);
    }

    private function runtimeMode(): string
    {
        $row = $this->settings->get('ai_settings', 'runtime_mode');
        $mode = strtolower(trim((string)($row['value'] ?? 'staged')));
        return in_array($mode, ['mock', 'staged', 'real'], true) ? $mode : 'staged';
    }

    private function encryptSecret(string $secret): string
    {
        $key = $this->secretKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher) || $cipher === '') {
            throw new \RuntimeException('Failed to encrypt AI provider secret');
        }

        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    private function decryptedSecretByProvider(int $providerId): ?string
    {
        if ($providerId <= 0) {
            return null;
        }

        $encrypted = $this->providers->encryptedSecretByProviderId($providerId);
        if ($encrypted === null) {
            return null;
        }

        if (!str_starts_with($encrypted, 'v1:')) {
            return null;
        }

        $blob = base64_decode(substr($encrypted, 3), true);
        if (!is_string($blob) || strlen($blob) < 29) {
            return null;
        }

        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);
        try {
            $key = $this->secretKey();
        } catch (\Throwable) {
            return null;
        }
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if (!is_string($plain) || $plain === '') {
            return null;
        }

        return $plain;
    }

    private function secretKey(): string
    {
        $configured = trim((string)(
            $this->config->get('security.ai.secret_key', '')
            ?: $this->config->get('security.ai.encryption_key', '')
            ?: getenv('AI_ENCRYPTION_KEY')
            ?: ''
        ));
        if ($configured === '') {
            throw new \RuntimeException('AI secret key is not configured. Set AI_ENCRYPTION_KEY in .env');
        }

        return hash('sha256', $configured, true);
    }

    private function secretKeyHint(): string
    {
        return substr(hash('sha256', $this->secretKey()), 0, 12);
    }
}
