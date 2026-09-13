<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Job;

use Api\System\Library\Container;
use Api\System\Library\Support\Ulid;
use Module\Crm\EcommerceGateway\Repository\OutboxRepository;
use Module\Crm\EcommerceGateway\Service\EncryptionService;
use Module\Crm\EcommerceGateway\Service\SignatureService;

/**
 * Background job delivering asynchronous webhooks to CMS storefronts (E-COM-04 §3).
 *
 * Implements exponential backoff: base_delay * 2^retry.
 * Signs each delivery with HMAC-SHA256 via SignatureService::signOutbound.
 */
final class EcommerceWebhookJob
{
    public const DEFAULT_BASE_DELAY = 60;
    public const DEFAULT_MAX_ATTEMPTS = 8;
    public const DEFAULT_TIMEOUT_SECONDS = 10;

    /** @var (callable(string, array<string,string>, string, int): array{status: int, body: string, error: ?string})|null */
    private $httpTransport = null;

    public function __construct(
        private readonly OutboxRepository $outboxRepo,
        private readonly array $config = [],
        ?callable $httpTransport = null
    ) {
        $this->httpTransport = $httpTransport;
    }

    /**
     * Entry point for ModuleJobDispatcher background invocation.
     *
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload = []): void
    {
        if (isset($payload['outbox_id'])) {
            $this->processEvent((int)$payload['outbox_id']);
            return;
        }

        $limit = (int)($payload['limit'] ?? ($this->config['outbox_batch_size'] ?? 20));
        $this->processPending($limit);
    }

    /**
     * Delivers a single outbox event by ID.
     *
     * @return array{success: bool, status: string, http_code: ?int, error: ?string}
     */
    public function processEvent(int $eventId): array
    {
        $events = $this->outboxRepo->findPendingEvents(100);
        $target = null;
        foreach ($events as $event) {
            if ((int)$event['id'] === $eventId) {
                $target = $event;
                break;
            }
        }

        if ($target === null) {
            return [
                'success' => false,
                'status' => 'not_found_or_not_pending',
                'http_code' => null,
                'error' => 'Event not found or not in pending state',
            ];
        }

        return $this->deliver($target);
    }

    /**
     * Delivers pending outbox events up to limit.
     *
     * @return array{processed: int, delivered: int, failed: int}
     */
    public function processPending(int $limit = 20): array
    {
        $events = $this->outboxRepo->findPendingEvents($limit);
        $delivered = 0;
        $failed = 0;

        foreach ($events as $event) {
            $result = $this->deliver($event);
            if ($result['success']) {
                $delivered++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => count($events),
            'delivered' => $delivered,
            'failed' => $failed,
        ];
    }

    /**
     * @param array<string,mixed> $event
     * @return array{success: bool, status: string, http_code: ?int, error: ?string}
     */
    public function deliver(array $event): array
    {
        $eventId = (int)$event['id'];
        $storeId = (int)$event['store_id'];
        $store = $this->outboxRepo->getStore($storeId);

        if ($store === null || empty($store['webhook_url'])) {
            $this->outboxRepo->markFailed($eventId, 1, 1, 0, null, 'Store not found or webhook_url not configured');
            return [
                'success' => false,
                'status' => 'store_missing_webhook_url',
                'http_code' => null,
                'error' => 'Store missing webhook_url',
            ];
        }

        if (($store['status'] ?? '') === 'disabled') {
            $this->outboxRepo->markFailed($eventId, 1, 1, 0, null, 'Store is disabled');
            return [
                'success' => false,
                'status' => 'store_disabled',
                'http_code' => null,
                'error' => 'Store is disabled',
            ];
        }

        $webhookUrl = (string)$store['webhook_url'];
        $secretEncrypted = (string)($store['webhook_secret_encrypted'] ?? $store['api_secret_encrypted'] ?? '');
        $secret = EncryptionService::decrypt($secretEncrypted) ?? '';

        $payloadJson = (string)$event['payload_json'];
        $timestamp = (string)time();
        $deliveryId = Ulid::generate('del');

        // Generate HMAC signature
        $signature = SignatureService::signOutbound($secret, $timestamp, $payloadJson);

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-TropaTT-Signature' => $signature,
            'X-TropaTT-Event' => (string)($event['event_type'] ?? 'order.status_changed'),
            'X-TropaTT-Delivery-ID' => $deliveryId,
            'X-TropaTT-Timestamp' => $timestamp,
            'User-Agent' => 'TropaTT-EcommerceGateway/1.1 (+https://tropatt.com)',
        ];

        $this->outboxRepo->markDelivering($eventId);

        $timeout = (int)($this->config['request_timeout_seconds'] ?? self::DEFAULT_TIMEOUT_SECONDS);
        $response = $this->sendHttpRequest($webhookUrl, $headers, $payloadJson, $timeout);

        $httpCode = $response['status'];
        $responseBody = $response['body'];
        $error = $response['error'];

        // 2xx response counts as successful delivery
        if ($httpCode >= 200 && $httpCode < 300) {
            $this->outboxRepo->markDelivered($eventId, $httpCode, $responseBody);
            return [
                'success' => true,
                'status' => 'delivered',
                'http_code' => $httpCode,
                'error' => null,
            ];
        }

        // Failure handling with exponential backoff: base_delay * 2^retry
        $currentAttempts = (int)$event['attempts'] + 1;
        $maxAttempts = (int)($event['max_attempts'] ?: ($this->config['outbox_max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS));
        $baseDelay = (int)($this->config['outbox_base_delay_seconds'] ?? self::DEFAULT_BASE_DELAY);
        $delaySeconds = $baseDelay * (2 ** min(6, $currentAttempts));

        $errorMessage = $error ?? ('HTTP response status: ' . $httpCode);
        $this->outboxRepo->markFailed($eventId, $currentAttempts, $maxAttempts, $delaySeconds, $httpCode, $errorMessage, $responseBody);

        return [
            'success' => false,
            'status' => $currentAttempts >= $maxAttempts ? 'failed' : 'retry_scheduled',
            'http_code' => $httpCode,
            'error' => $errorMessage,
        ];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status: int, body: string, error: ?string}
     */
    private function sendHttpRequest(string $url, array $headers, string $body, int $timeout): array
    {
        if ($this->httpTransport !== null) {
            return ($this->httpTransport)($url, $headers, $body, $timeout);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $formattedHeaders = [];
            foreach ($headers as $k => $v) {
                $formattedHeaders[] = "{$k}: {$v}";
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $responseBody = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            return [
                'status' => $httpCode,
                'body' => is_string($responseBody) ? $responseBody : '',
                'error' => $curlError !== '' ? $curlError : null,
            ];
        }

        // Fallback for environments without curl
        $headerLines = '';
        foreach ($headers as $k => $v) {
            $headerLines .= "{$k}: {$v}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $level = error_reporting(0);
        $responseBody = file_get_contents($url, false, $context);
        error_reporting($level);

        $httpCode = 0;
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
            $httpCode = (int)$m[1];
        }

        return [
            'status' => $httpCode,
            'body' => is_string($responseBody) ? $responseBody : '',
            'error' => $httpCode === 0 ? 'Network or connection failed' : null,
        ];
    }
}
