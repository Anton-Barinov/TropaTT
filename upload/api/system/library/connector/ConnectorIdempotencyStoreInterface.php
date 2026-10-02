<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

/**
 * Contract for managing connector delivery idempotency and receipt tracking.
 * Scoped strictly to module, workspace, and source.
 */
interface ConnectorIdempotencyStoreInterface
{
    /**
     * Atomically claim an idempotency key.
     * Returns true if successfully claimed (first attempt or retried stale attempt).
     * Returns false if already claimed and active/completed.
     */
    public function claim(
        string $moduleName,
        int $organizationId,
        string $source,
        string $idempotencyKey,
        ?string $requestHash = null,
        int $ttlSeconds = 86400 * 30
    ): bool;

    /**
     * Get the stored receipt / execution record for an idempotency key.
     *
     * @return array{
     *     status: string,
     *     response_code: int,
     *     response_payload: array|null,
     *     resource_type: string|null,
     *     resource_public_id: string|null,
     *     request_hash: string|null,
     *     created_at: string
     * }|null
     */
    public function get(string $moduleName, int $organizationId, string $source, string $idempotencyKey): ?array;

    /**
     * Complete the idempotency record with execution results.
     */
    public function complete(
        string $moduleName,
        int $organizationId,
        string $source,
        string $idempotencyKey,
        int $statusCode,
        array $payload,
        ?string $resourceType = null,
        ?string $resourcePublicId = null
    ): void;

    /**
     * Mark the idempotency record as failed so retries can occur or failure is recorded.
     */
    public function fail(
        string $moduleName,
        int $organizationId,
        string $source,
        string $idempotencyKey,
        string $errorMessage,
        int $statusCode = 500
    ): void;

    /**
     * Prune expired idempotency records.
     */
    public function prune(): int;
}
