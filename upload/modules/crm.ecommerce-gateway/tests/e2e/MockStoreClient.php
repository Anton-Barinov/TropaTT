<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Tests\E2e;

/**
 * Synthetic Mock Store Client for E-Commerce Gateway E2E testing (E-COM-14 §1).
 *
 * Emulates any CMS engine (OpenCart, WooCommerce, 1C-Bitrix, Custom)
 * with authentic HMAC-SHA256 signature generation, canonical string building,
 * headers (X-Store-Key, X-TropaTT-Timestamp, X-TropaTT-Nonce, X-TropaTT-Signature),
 * and an in-memory / callback receiver for Outbox status webhooks.
 */
final class MockStoreClient
{
    public const CMS_OPENCART = 'opencart';
    public const CMS_WOOCOMMERCE = 'woocommerce';
    public const CMS_BITRIX = 'bitrix';
    public const CMS_CUSTOM = 'custom';

    /**
     * @var list<array{headers: array<string,string>, body: string, payload: array<string,mixed>, timestamp: float}>
     */
    private array $receivedWebhooks = [];

    /**
     * @var callable|null
     */
    private $httpHandler = null;

    public function __construct(
        public readonly string $storeKey,
        public readonly string $storeSecret,
        public readonly string $cmsType = self::CMS_OPENCART,
        public readonly string $baseUrl = 'https://crm.local/api/index.php',
        public readonly string $webhookSecret = '',
        ?callable $httpHandler = null
    ) {
        $this->httpHandler = $httpHandler;
    }

    /**
     * Set a custom HTTP transport handler (e.g. for in-memory or mock testing).
     * Handler signature: function(string $method, string $url, array $headers, string $body): array{status: int, body: string}
     */
    public function setHttpHandler(callable $handler): void
    {
        $this->httpHandler = $handler;
    }

    /**
     * Generates canonical signature for an outgoing API request.
     */
    public function signRequest(
        string $method,
        string $requestPath,
        string $timestamp,
        string $nonce,
        string $rawBody
    ): string {
        $canonical = strtoupper($method)
            . "\n" . $requestPath
            . "\n" . $timestamp
            . "\n" . $nonce
            . "\n" . hash('sha256', $rawBody);

        return base64_encode(hash_hmac('sha256', $canonical, $this->storeSecret, true));
    }

    /**
     * Builds standard headers for an ingestion request.
     *
     * @return array<string,string>
     */
    public function buildHeaders(
        string $method,
        string $route,
        string $rawBody,
        ?string $customNonce = null,
        ?int $customTimestamp = null,
        ?string $idempotencyKey = null
    ): array {
        $timestamp = (string)($customTimestamp ?? time());
        $nonce = $customNonce ?? bin2hex(random_bytes(16));
        $signature = $this->signRequest($method, '/' . ltrim($route, '/'), $timestamp, $nonce, $rawBody);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Store-Key' => $this->storeKey,
            'X-TropaTT-Timestamp' => $timestamp,
            'X-TropaTT-Nonce' => $nonce,
            'X-TropaTT-Signature' => $signature,
        ];

        if ($idempotencyKey !== null) {
            $headers['X-TropaTT-Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * Sends a signed request to the gateway.
     *
     * @param array<string,mixed> $payload
     * @param array<string,string> $headerOverrides
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function send(
        string $method,
        string $endpoint,
        array $payload = [],
        array $headerOverrides = [],
        ?string $idempotencyKey = null
    ): array {
        $route = '_module/crm.ecommerce-gateway/v1/' . ltrim($endpoint, '/');
        $rawBody = $payload !== [] ? (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') : '';

        $headers = $this->buildHeaders($method, $route, $rawBody, null, null, $idempotencyKey);
        foreach ($headerOverrides as $k => $v) {
            $headers[$k] = $v;
        }

        $url = $this->baseUrl . '?route=' . urlencode($route);

        if ($this->httpHandler !== null) {
            $response = ($this->httpHandler)($method, $url, $headers, $rawBody);
        } else {
            $response = $this->curlRequest($method, $url, $headers, $rawBody);
        }

        $decoded = json_decode($response['body'], true);
        return [
            'status' => $response['status'],
            'body' => $response['body'],
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }

    /**
     * Helper: Ping test
     *
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function ping(): array
    {
        return $this->send('GET', 'ping');
    }

    /**
     * Helper: Ingest order
     *
     * @param array<string,mixed> $orderData
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function submitOrder(array $orderData, ?string $idempotencyKey = null, array $headers = []): array
    {
        return $this->send('POST', 'orders', $orderData, $headers, $idempotencyKey);
    }

    /**
     * Helper: Ingest quick order
     *
     * @param array<string,mixed> $quickOrderData
     * @param array<string,string> $headers
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function submitQuickOrder(array $quickOrderData, ?string $idempotencyKey = null, array $headers = []): array
    {
        return $this->send('POST', 'quick-orders', $quickOrderData, $headers, $idempotencyKey);
    }

    /**
     * Helper: Ingest callback request
     *
     * @param array<string,mixed> $callbackData
     * @param array<string,string> $headers
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function submitCallback(array $callbackData, ?string $idempotencyKey = null, array $headers = []): array
    {
        return $this->send('POST', 'callbacks', $callbackData, $headers, $idempotencyKey);
    }

    /**
     * Helper: Ingest feedback
     *
     * @param array<string,mixed> $feedbackData
     * @param array<string,string> $headers
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function submitFeedback(array $feedbackData, ?string $idempotencyKey = null, array $headers = []): array
    {
        return $this->send('POST', 'feedback', $feedbackData, $headers, $idempotencyKey);
    }

    /**
     * Helper: Ingest dynamic form / quiz
     *
     * @param array<string,mixed> $formData
     * @param array<string,string> $headers
     * @return array{status: int, body: string, data: array<string,mixed>}
     */
    public function submitForm(array $formData, ?string $idempotencyKey = null, array $headers = []): array
    {
        return $this->send('POST', 'forms', $formData, $headers, $idempotencyKey);
    }

    /**
     * Receives and verifies an incoming Outbox status webhook from CRM.
     *
     * @param array<string,string> $headers
     * @param string $rawBody
     * @return array{valid: bool, error: ?string, payload: array<string,mixed>}
     */
    public function receiveWebhook(array $headers, string $rawBody): array
    {
        $signature = $headers['X-TropaTT-Signature'] ?? $headers['x-tropatt-signature'] ?? '';
        $timestamp = $headers['X-TropaTT-Timestamp'] ?? $headers['x-tropatt-timestamp'] ?? '';
        $secret = $this->webhookSecret !== '' ? $this->webhookSecret : $this->storeSecret;

        $expectedSig = base64_encode(hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret, true));
        $valid = hash_equals($expectedSig, $signature);

        $payload = json_decode($rawBody, true) ?: [];

        $record = [
            'headers' => $headers,
            'body' => $rawBody,
            'payload' => $payload,
            'timestamp' => microtime(true),
        ];
        $this->receivedWebhooks[] = $record;

        return [
            'valid' => $valid,
            'error' => $valid ? null : 'Signature verification failed',
            'payload' => $payload,
        ];
    }

    /**
     * Returns all received status webhooks.
     *
     * @return list<array{headers: array<string,string>, body: string, payload: array<string,mixed>, timestamp: float}>
     */
    public function getReceivedWebhooks(): array
    {
        return $this->receivedWebhooks;
    }

    /**
     * Clears recorded webhooks.
     */
    public function clearReceivedWebhooks(): void
    {
        $this->receivedWebhooks = [];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status: int, body: string}
     */
    private function curlRequest(string $method, string $url, array $headers, string $body): array
    {
        $ch = curl_init();
        $formattedHeaders = [];
        foreach ($headers as $k => $v) {
            $formattedHeaders[] = "{$k}: {$v}";
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($body !== '' && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $resBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        return [
            'status' => $status,
            'body' => is_string($resBody) ? $resBody : '',
        ];
    }
}
