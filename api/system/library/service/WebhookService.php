<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Webhook\WebhookRepository;
use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\UrlSafetyValidator;
use Api\System\Library\Support\Ulid;

final class WebhookService
{
    private UrlSafetyValidator $urlSafety;

    public function __construct(
        private readonly WebhookRepository $repository,
        private readonly JsonLogger $logger,
        private readonly Config $config
    ) {
        $this->urlSafety = new UrlSafetyValidator();
    }

    public function listSubscriptions(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repository->listSubscriptions($filters);

        return [
            'items' => $items,
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

    public function createSubscription(array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('whs');
        $secret = trim((string)($input['secret'] ?? ''));
        $endpoint = trim((string)($input['endpoint'] ?? ''));
        $endpointValidation = $this->validateEndpoint($endpoint);
        if (!$endpointValidation['ok']) {
            return $endpointValidation;
        }

        $this->repository->createSubscription([
            'public_id' => $publicId,
            'title' => trim((string)($input['title'] ?? '')),
            'endpoint' => $endpoint,
            'secret_hash' => $this->encodeSecret($secret),
            'events' => json_encode($this->normalizeEvents($input['events'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => (int)($input['is_active'] ?? 1) === 1 ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $item = $this->normalizeSubscription($this->repository->findSubscriptionByPublicId($publicId));
        $this->logger->audit([
            'action' => 'webhook_subscription_create',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'webhook_subscription',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'webhook' => $item];
    }

    public function findSubscription(string $publicId): ?array
    {
        $item = $this->repository->findSubscriptionByPublicId($publicId);
        return $item ? $this->normalizeSubscription($item) : null;
    }

    public function updateSubscription(string $publicId, array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $current = $this->repository->findSubscriptionByPublicId($publicId);
        if (!$current) {
            return ['ok' => false, 'code' => 'WEBHOOK_NOT_FOUND'];
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('endpoint', $input)) {
            $endpoint = trim((string)$input['endpoint']);
            $endpointValidation = $this->validateEndpoint($endpoint);
            if (!$endpointValidation['ok']) {
                return $endpointValidation;
            }
            $set['endpoint'] = $endpoint;
        }
        if (array_key_exists('events', $input)) {
            $set['events'] = json_encode($this->normalizeEvents($input['events']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = (int)(((string)$input['is_active'] === '1' || (string)$input['is_active'] === 'true') ? 1 : 0);
        }
        if (array_key_exists('secret', $input)) {
            $secret = trim((string)$input['secret']);
            $set['secret_hash'] = $this->encodeSecret($secret);
        }
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->repository->updateSubscriptionByPublicId($publicId, $set);

        $this->logger->audit([
            'action' => 'webhook_subscription_update',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'webhook_subscription',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'webhook' => $this->normalizeSubscription($this->repository->findSubscriptionByPublicId($publicId))];
    }

    public function deleteSubscription(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $existing = $this->repository->findSubscriptionByPublicId($publicId);
        if (!$existing) {
            return ['ok' => false, 'code' => 'WEBHOOK_NOT_FOUND'];
        }

        $this->repository->deleteSubscriptionByPublicId($publicId);
        $this->logger->audit([
            'action' => 'webhook_subscription_delete',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'webhook_subscription',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true];
    }

    public function listDeliveries(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repository->listDeliveries($filters);

        return [
            'items' => $items,
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

    public function testDelivery(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $webhook = $this->repository->findSubscriptionByPublicId($publicId);
        if (!$webhook) {
            return ['ok' => false, 'code' => 'WEBHOOK_NOT_FOUND'];
        }
        if ((int)($webhook['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'WEBHOOK_INACTIVE'];
        }

        $payload = [
            'event' => 'webhook.test',
            'sent_at' => gmdate('c'),
            'request_id' => Ulid::generate('rid'),
        ];

        $attempts = max(1, (int)$this->config->get('security.webhook.retry_attempts', 3));
        $backoffMs = max(0, (int)$this->config->get('security.webhook.retry_backoff_ms', 200));
        $disableThreshold = max(1, (int)$this->config->get('security.webhook.auto_disable_after_failures', 3));

        $signature = null;
        $secret = $this->decodeSecret((string)($webhook['secret_hash'] ?? ''));
        if ($secret !== null && $secret !== '') {
            $signature = hash_hmac('sha256', $payload['sent_at'] . '.' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $secret);
        }

        [$status, $responseCode, $attemptCount] = $this->sendWithRetries(
            endpoint: (string)$webhook['endpoint'],
            payload: $payload,
            eventCode: 'webhook.test',
            signature: $signature,
            maxAttempts: $attempts,
            backoffMs: $backoffMs
        );

        $this->repository->createDelivery([
            'public_id' => Ulid::generate('whd'),
            'webhook_id' => (int)$webhook['id'],
            'event_code' => 'webhook.test',
            'status' => $status,
            'response_code' => $responseCode,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $autoDisabled = false;
        if ($status !== 'sent') {
            $statuses = $this->repository->recentDeliveryStatuses((int)$webhook['id'], max(20, $disableThreshold + 2));
            $consecutiveFailures = 0;
            foreach ($statuses as $s) {
                if ($s === 'sent') {
                    break;
                }
                if ($s === 'failed' || $s === 'error') {
                    $consecutiveFailures++;
                }
            }

            if ($consecutiveFailures >= $disableThreshold) {
                $autoDisabled = $this->repository->updateSubscriptionByPublicId((string)$webhook['public_id'], [
                    'is_active' => 0,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->logger->audit([
            'action' => 'webhook_delivery_test',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'webhook_subscription',
            'entity_public_id' => $publicId,
            'delivery_status' => $status,
            'response_code' => $responseCode,
            'attempts' => $attemptCount,
            'signed' => $signature !== null,
            'auto_disabled' => $autoDisabled,
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'webhook_test_delivery',
            'ip' => null,
            'user_agent' => null,
            'details' => [
                'webhook_public_id' => $publicId,
                'status' => $status,
                'response_code' => $responseCode,
                'attempts' => $attemptCount,
                'signed' => $signature !== null,
                'auto_disabled' => $autoDisabled,
            ],
        ]);

        return [
            'ok' => true,
            'delivery' => [
                'status' => $status,
                'response_code' => $responseCode,
                'attempts' => $attemptCount,
                'signed' => $signature !== null,
                'auto_disabled' => $autoDisabled,
            ],
        ];
    }

    public function enqueueTestDelivery(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $webhook = $this->repository->findSubscriptionByPublicId($publicId);
        if (!$webhook) {
            return ['ok' => false, 'code' => 'WEBHOOK_NOT_FOUND'];
        }
        if ((int)($webhook['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'WEBHOOK_INACTIVE'];
        }

        $payload = [
            'event' => 'webhook.test',
            'sent_at' => gmdate('c'),
            'request_id' => Ulid::generate('rid'),
        ];
        $signature = $this->signatureForWebhookPayload($webhook, $payload);
        $deliveryPublicId = Ulid::generate('whd');
        $now = gmdate('Y-m-d H:i:s');

        $this->repository->createDelivery([
            'public_id' => $deliveryPublicId,
            'webhook_id' => (int)$webhook['id'],
            'event_code' => 'webhook.test',
            'status' => 'queued',
            'response_code' => null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'signature' => $signature,
            'attempts' => 0,
            'next_run_at' => $now,
            'locked_at' => null,
            'last_error' => null,
            'dead_letter' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'webhook_delivery_enqueued',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'webhook_subscription',
            'entity_public_id' => $publicId,
            'delivery_public_id' => $deliveryPublicId,
            'event_code' => 'webhook.test',
            'signed' => $signature !== null,
        ]);

        return [
            'ok' => true,
            'delivery' => [
                'public_id' => $deliveryPublicId,
                'status' => 'queued',
                'response_code' => null,
                'attempts' => 0,
                'signed' => $signature !== null,
                'queued' => true,
            ],
        ];
    }

    /** @return array{processed:int,completed:int,retried:int,dead_lettered:int,failed:int,errors:array<int,array<string,string>>} */
    public function runQueued(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));
        $processed = 0;
        $completed = 0;
        $retried = 0;
        $deadLettered = 0;
        $failed = 0;
        $errors = [];
        $maxAttempts = max(1, (int)$this->config->get('security.webhook.retry_attempts', 3));
        $backoffMs = max(0, (int)$this->config->get('security.webhook.retry_backoff_ms', 200));

        for ($i = 0; $i < $limit; $i++) {
            $now = gmdate('Y-m-d H:i:s');
            $delivery = $this->repository->claimNextRunnableDelivery($now);
            if (!is_array($delivery)) {
                break;
            }

            $processed++;
            $deliveryPublicId = (string)($delivery['public_id'] ?? '');
            $attempt = (int)($delivery['attempts'] ?? 0) + 1;

            try {
                if ((int)($delivery['is_active'] ?? 0) !== 1) {
                    throw new \RuntimeException('WEBHOOK_INACTIVE');
                }

                $payload = json_decode((string)($delivery['payload_json'] ?? ''), true);
                if (!is_array($payload)) {
                    $payload = [
                        'event' => (string)($delivery['event_code'] ?? 'webhook.event'),
                        'sent_at' => gmdate('c'),
                        'request_id' => Ulid::generate('rid'),
                    ];
                }

                [$status, $responseCode, $attemptCount] = $this->sendWithRetries(
                    endpoint: (string)($delivery['endpoint'] ?? ''),
                    payload: $payload,
                    eventCode: (string)($delivery['event_code'] ?? 'webhook.event'),
                    signature: (string)($delivery['signature'] ?? '') !== '' ? (string)$delivery['signature'] : null,
                    maxAttempts: 1,
                    backoffMs: 0
                );

                $attempt = max($attempt, (int)($delivery['attempts'] ?? 0) + $attemptCount);
                if ($status === 'sent') {
                    $this->repository->updateDeliveryByPublicId($deliveryPublicId, [
                        'status' => 'sent',
                        'response_code' => $responseCode,
                        'attempts' => $attempt,
                        'locked_at' => null,
                        'last_error' => null,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    $completed++;
                    continue;
                }

                if ($attempt >= $maxAttempts) {
                    $this->repository->updateDeliveryByPublicId($deliveryPublicId, [
                        'status' => $status,
                        'response_code' => $responseCode,
                        'attempts' => $attempt,
                        'locked_at' => null,
                        'last_error' => $status,
                        'dead_letter' => 1,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                    $deadLettered++;
                    continue;
                }

                $this->repository->updateDeliveryByPublicId($deliveryPublicId, [
                    'status' => 'queued',
                    'response_code' => $responseCode,
                    'attempts' => $attempt,
                    'next_run_at' => gmdate('Y-m-d H:i:s', time() + max(1, (int)ceil($backoffMs / 1000)) * $attempt),
                    'locked_at' => null,
                    'last_error' => $status,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $retried++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['public_id' => $deliveryPublicId, 'error' => $e->getMessage()];
                $this->repository->updateDeliveryByPublicId($deliveryPublicId, [
                    'status' => $attempt >= $maxAttempts ? 'error' : 'queued',
                    'attempts' => $attempt,
                    'next_run_at' => $attempt >= $maxAttempts ? null : gmdate('Y-m-d H:i:s', time() + max(1, (int)ceil($backoffMs / 1000)) * $attempt),
                    'locked_at' => null,
                    'last_error' => $e->getMessage(),
                    'dead_letter' => $attempt >= $maxAttempts ? 1 : 0,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($attempt >= $maxAttempts) {
                    $deadLettered++;
                } else {
                    $retried++;
                }
            }
        }

        return [
            'processed' => $processed,
            'completed' => $completed,
            'retried' => $retried,
            'dead_lettered' => $deadLettered,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    public function summary(): array
    {
        return $this->repository->summary();
    }

    private function normalizeSubscription(?array $row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'public_id' => (string)$row['public_id'],
            'title' => (string)($row['title'] ?? ''),
            'endpoint' => (string)($row['endpoint'] ?? ''),
            'events' => array_values((array)($row['events'] ?? [])),
            'is_active' => (int)($row['is_active'] ?? 0),
            'has_secret' => trim((string)($row['secret_hash'] ?? '')) !== '',
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    /** @return array{0:string,1:int,2:int} */
    private function sendWithRetries(
        string $endpoint,
        array $payload,
        string $eventCode,
        ?string $signature,
        int $maxAttempts,
        int $backoffMs
    ): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['error', 0, 1];
        }

        $status = 'failed';
        $code = 0;
        $attemptCount = 0;
        $timestamp = (string)($payload['sent_at'] ?? gmdate('c'));
        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $eventCode,
            'X-Webhook-Timestamp: ' . $timestamp,
        ];
        if ($signature !== null && $signature !== '') {
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $attemptCount = $i;
            $endpointValidation = $this->validateEndpoint($endpoint);
            if (!$endpointValidation['ok']) {
                return ['error', 0, $attemptCount];
            }

            $code = $this->sendOnce($endpoint, $body, $headers);

            if ($code >= 200 && $code < 300) {
                $status = 'sent';
                break;
            }

            $status = $code === 0 ? 'error' : 'failed';
            if ($i < $maxAttempts && $backoffMs > 0) {
                usleep($backoffMs * 1000 * $i);
            }
        }

        return [$status, $code, $attemptCount];
    }

    private function signatureForWebhookPayload(array $webhook, array $payload): ?string
    {
        $secret = $this->decodeSecret((string)($webhook['secret_hash'] ?? ''));
        if ($secret === null || $secret === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            (string)($payload['sent_at'] ?? gmdate('c')) . '.' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $secret
        );
    }

    /** @return list<string> */
    private function normalizeEvents(mixed $events): array
    {
        if (!is_array($events)) {
            $raw = trim((string)$events);
            if ($raw === '') {
                return [];
            }

            $json = json_decode($raw, true);
            if (is_array($json)) {
                $events = $json;
            } else {
                $events = explode(',', $raw);
            }
        }

        $result = [];
        foreach ($events as $event) {
            $value = trim((string)$event);
            if ($value === '') {
                continue;
            }
            $result[] = substr($value, 0, 128);
        }

        $result = array_values(array_unique($result));
        sort($result);
        return $result;
    }

    private function encodeSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        $key = $this->webhookCryptoKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!is_string($ciphertext) || $ciphertext === '') {
            throw new \RuntimeException('Failed to encrypt webhook secret');
        }

        return 'v2:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decodeSecret(string $stored): ?string
    {
        $value = trim($stored);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'v2:')) {
            $blob = base64_decode(substr($value, 3), true);
            if (!is_string($blob) || strlen($blob) < 29) {
                return null;
            }

            $iv = substr($blob, 0, 12);
            $tag = substr($blob, 12, 16);
            $ciphertext = substr($blob, 28);
            $plain = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->webhookCryptoKey(),
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return is_string($plain) && $plain !== '' ? $plain : null;
        }

        if (str_starts_with($value, 'v1:')) {
            $decoded = base64_decode(substr($value, 3), true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
            return null;
        }

        return null;
    }

    private function sendOnce(string $endpoint, string $body, array $headers): int
    {
        $timeout = max(1, (int)$this->config->get('security.webhook.timeout_sec', 5));

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
                if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
                if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
                curl_exec($ch);
                return (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        @file_get_contents($endpoint, false, $context);
        $responseHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : null;
        if (is_array($responseHeaders) && isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', (string)$responseHeaders[0], $m)) {
            return (int)$m[1];
        }

        return 0;
    }

    /**
     * @return array{ok:bool,code:string}
     */
    private function validateEndpoint(string $endpoint): array
    {
        $isProduction = in_array(strtolower((string)$this->config->get('default.app.env', 'prod')), ['prod', 'production'], true);
        $blockPrivateNetworks = (bool)$this->config->get('security.webhook.block_private_networks_in_production', true);
        $strict = $blockPrivateNetworks || $isProduction;
        $allowedSchemes = (array)$this->config->get('security.webhook.allowed_schemes', ['https']);
        $validated = $this->urlSafety->validateProviderUrl($endpoint, $strict, $allowedSchemes);
        if (!(bool)($validated['ok'] ?? false)) {
            return ['ok' => false, 'code' => $this->mapEndpointValidationCode((string)($validated['code'] ?? 'AI_PROVIDER_URL_INVALID'))];
        }

        $scheme = strtolower((string)(parse_url($endpoint, PHP_URL_SCHEME) ?: ''));
        if ($scheme === 'http' && !$this->allowInsecureLocalDevEndpoint($endpoint, $strict)) {
            return ['ok' => false, 'code' => 'WEBHOOK_ENDPOINT_SCHEME_NOT_ALLOWED'];
        }

        return ['ok' => true, 'code' => 'OK'];
    }

    private function allowInsecureLocalDevEndpoint(string $endpoint, bool $strict): bool
    {
        if ($strict || !(bool)$this->config->get('security.webhook.allow_insecure_local_dev_urls', false)) {
            return false;
        }

        $host = strtolower((string)(parse_url($endpoint, PHP_URL_HOST) ?: ''));
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost');
    }

    private function mapEndpointValidationCode(string $code): string
    {
        return match ($code) {
            'AI_PROVIDER_URL_REQUIRED' => 'WEBHOOK_ENDPOINT_REQUIRED',
            'AI_PROVIDER_URL_INVALID' => 'WEBHOOK_ENDPOINT_INVALID',
            'AI_PROVIDER_URL_SCHEME_NOT_ALLOWED' => 'WEBHOOK_ENDPOINT_SCHEME_NOT_ALLOWED',
            'AI_PROVIDER_URL_LOCALHOST_FORBIDDEN' => 'WEBHOOK_ENDPOINT_LOCALHOST_FORBIDDEN',
            'AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN' => 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN',
            default => 'WEBHOOK_ENDPOINT_INVALID',
        };
    }

    private function webhookCryptoKey(): string
    {
        $configured = trim((string)$this->config->get('security.webhook.secret_key', ''));
        if ($configured !== '') {
            return hash('sha256', $configured, true);
        }

        $secretsDir = rtrim((string)$this->config->get('default.storage.secrets', ''), '/\\');
        if ($secretsDir === '') {
            $secretsDir = rtrim((string)$this->config->get('default.storage.base', dirname(__DIR__, 3) . '/../storage_api'), '/\\') . '/secrets';
        }
        if (!is_dir($secretsDir)) {
            @mkdir($secretsDir, 0700, true);
        }

        $path = $secretsDir . '/webhook.key';
        if (is_file($path)) {
            $raw = trim((string)@file_get_contents($path));
            if ($raw !== '') {
                return hash('sha256', $raw, true);
            }
        }

        $generated = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        @file_put_contents($path, $generated);
        @chmod($path, 0600);

        return hash('sha256', $generated, true);
    }
}
