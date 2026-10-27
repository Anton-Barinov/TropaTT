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

    public const DEFAULT_MAX_EXECUTION_SECONDS = 20.0;

    /**
     * Delivers pending outbox events up to limit with execution time guard (E-COM-13 §2).
     *
     * @param int $limit Max items per batch (default 20)
     * @param float $maxSeconds Max execution time before graceful stop (default 20.0)
     * @return array{processed: int, delivered: int, failed: int, stopped_by_timeout: bool, elapsed_ms: float}
     */
    public function processPending(int $limit = 20, float $maxSeconds = self::DEFAULT_MAX_EXECUTION_SECONDS): array
    {
        $startTime = microtime(true);
        $events = $this->outboxRepo->claimPendingEvents($limit);
        $delivered = 0;
        $failed = 0;
        $stoppedByTimeout = false;

        $count = count($events);
        for ($i = 0; $i < $count; $i++) {
            $event = $events[$i];

            // Check execution time guard before starting next delivery
            if ((microtime(true) - $startTime) >= $maxSeconds) {
                $stoppedByTimeout = true;
                // Return this and all subsequent claimed events in batch back to pending immediately
                for ($j = $i; $j < $count; $j++) {
                    $this->outboxRepo->markPending((int)$events[$j]['id']);
                }
                break;
            }

            $result = $this->deliver($event);
            if ($result['success']) {
                $delivered++;
            } else {
                $failed++;
            }

            // Explicit cleanup to prevent memory accumulation in long iterations
            unset($events[$i], $event, $result);
        }

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        return [
            'processed' => $delivered + $failed,
            'delivered' => $delivered,
            'failed' => $failed,
            'stopped_by_timeout' => $stoppedByTimeout,
            'elapsed_ms' => round($elapsedMs, 2),
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
        return (new \Module\Crm\EcommerceGateway\Service\SafeWebhookHttpClient())->request(
            $url,
            'POST',
            $headers,
            $body,
            $timeout,
            65536
        );
    }
}
