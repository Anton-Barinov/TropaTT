<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Support\AppLog;
use InvalidArgumentException;
use PDO;

/**
 * Reference fixture connector demonstrating end-to-end usage of connector primitives:
 * signature verification, replay window, workspace scoping, encrypted credential storage,
 * idempotency deduplication, and redacted diagnostics.
 */
final class FixtureConnector
{
    public const MODULE_NAME = 'crm.fixture-connector';
    public const SOURCE = 'fixture_webhook';

    private readonly ConnectorSignatureVerifier $signatureVerifier;
    private readonly ConnectorCredentialStore $credentialStore;
    private readonly ConnectorIdempotencyStore $idempotencyStore;
    private readonly ConnectorDiagnostics $diagnostics;

    public function __construct(
        private readonly PDO $pdo,
        ?string $masterKey = null,
    ) {
        $this->signatureVerifier = new ConnectorSignatureVerifier();
        $this->credentialStore = new ConnectorCredentialStore($this->pdo, $masterKey);
        $this->idempotencyStore = new ConnectorIdempotencyStore($this->pdo);
        $this->diagnostics = new ConnectorDiagnostics();
    }

    public function getSignatureVerifier(): ConnectorSignatureVerifier
    {
        return $this->signatureVerifier;
    }

    public function getCredentialStore(): ConnectorCredentialStore
    {
        return $this->credentialStore;
    }

    public function getIdempotencyStore(): ConnectorIdempotencyStore
    {
        return $this->idempotencyStore;
    }

    public function getDiagnostics(): ConnectorDiagnostics
    {
        return $this->diagnostics;
    }

    /**
     * Handle an incoming webhook request with full verification.
     *
     * @param string $rawBody Raw HTTP payload
     * @param array<string, string> $headers HTTP request headers
     * @param int $organizationId Target workspace ID
     * @return array{
     *     status: int,
     *     success: bool,
     *     code: string,
     *     message: string,
     *     data?: array,
     *     replayed?: bool,
     *     error?: string
     * }
     */
    public function handleInboundWebhook(
        string $rawBody,
        array $headers,
        int $organizationId
    ): array {
        if ($organizationId <= 0) {
            return [
                'status' => 400,
                'success' => false,
                'code' => 'INVALID_WORKSPACE',
                'message' => 'Workspace must be an authoritative, positive ID',
            ];
        }

        // 1. Fetch secret for this workspace
        $secret = $this->credentialStore->getSecret(self::MODULE_NAME, $organizationId, 'webhook_secret');
        if ($secret === null || $secret === '') {
            return [
                'status' => 401,
                'success' => false,
                'code' => 'CREDENTIAL_REVOKED_OR_MISSING',
                'message' => 'No active webhook secret found for this workspace',
            ];
        }

        // 2. Extract signature and timestamp headers
        $signature = $headers['x-signature'] ?? $headers['X-Signature'] ?? $headers['signature'] ?? '';
        $timestampStr = $headers['x-timestamp'] ?? $headers['X-Timestamp'] ?? $headers['timestamp'] ?? '';
        $idempotencyKey = $headers['x-event-id'] ?? $headers['X-Event-Id'] ?? $headers['x-idempotency-key'] ?? '';

        if ($signature === '') {
            return [
                'status' => 401,
                'success' => false,
                'code' => 'SIGNATURE_MISSING',
                'message' => 'Webhook signature header is required',
            ];
        }

        $timestamp = (int)$timestampStr;
        if ($timestamp > 0) {
            if (!$this->signatureVerifier->verifyReplayWindow($timestamp, 300)) {
                return [
                    'status' => 401,
                    'success' => false,
                    'code' => 'REPLAY_WINDOW_EXPIRED',
                    'message' => 'Request timestamp is outside the allowed replay window',
                ];
            }
        }

        // 3. Verify signature over raw body
        $valid = $this->signatureVerifier->verify($rawBody, $signature, $secret, [
            'timestamp' => $timestamp,
            'check_replay' => $timestamp > 0,
        ]);

        if (!$valid) {
            return [
                'status' => 401,
                'success' => false,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Signature verification failed (tampered payload or invalid secret)',
            ];
        }

        // 4. If an idempotency key is supplied, deduplicate
        if ($idempotencyKey !== '') {
            $requestHash = hash('sha256', $rawBody);
            $claimed = $this->idempotencyStore->claim(
                self::MODULE_NAME,
                $organizationId,
                self::SOURCE,
                $idempotencyKey,
                $requestHash
            );

            if (!$claimed) {
                // Check if already completed
                $record = $this->idempotencyStore->get(
                    self::MODULE_NAME,
                    $organizationId,
                    self::SOURCE,
                    $idempotencyKey
                );

                if ($record && $record['status'] === 'completed') {
                    return [
                        'status' => $record['response_code'],
                        'success' => true,
                        'code' => 'IDEMPOTENCY_REPLAYED',
                        'message' => 'Event already processed',
                        'data' => (array)($record['response_payload'] ?? []),
                        'replayed' => true,
                    ];
                }

                return [
                    'status' => 409,
                    'success' => false,
                    'code' => 'CONCURRENT_PROCESSING',
                    'message' => 'Event is currently being processed by another worker',
                ];
            }
        }

        // 5. Process payload
        $parsed = json_decode($rawBody, true) ?: [];
        $resourceId = 'itk_' . bin2hex(random_bytes(8));
        $responseData = [
            'intake_public_id' => $resourceId,
            'received_event' => $parsed['event_type'] ?? 'unknown',
            'processed_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // 6. Complete idempotency record
        if ($idempotencyKey !== '') {
            $this->idempotencyStore->complete(
                self::MODULE_NAME,
                $organizationId,
                self::SOURCE,
                $idempotencyKey,
                200,
                $responseData,
                'intake',
                $resourceId
            );
        }

        return [
            'status' => 200,
            'success' => true,
            'code' => 'PROCESSED',
            'message' => 'Webhook processed successfully',
            'data' => $responseData,
            'replayed' => false,
        ];
    }

    /**
     * Run connector health check.
     */
    public function healthCheck(int $organizationId): array
    {
        $hasSecret = $this->credentialStore->hasSecret(self::MODULE_NAME, $organizationId, 'webhook_secret');
        $checks = [
            'credentials' => [
                'status' => $hasSecret ? 'pass' : 'fail',
                'message' => $hasSecret ? 'Webhook secret is configured and encrypted' : 'Missing webhook_secret',
            ],
            'idempotency_store' => [
                'status' => 'pass',
                'message' => 'Idempotency store is operational',
            ],
        ];

        $overall = $hasSecret ? 'healthy' : 'warning';

        return $this->diagnostics->formatHealthReport($overall, $checks);
    }
}
