<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Idempotency store for external connector events and webhooks.
 * Prevents duplicate intake items, transactions, or tasks when providers retry deliveries.
 */
final class ConnectorIdempotencyStore implements ConnectorIdempotencyStoreInterface
{
    private const STALE_PROCESSING_SECONDS = 300;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'connector_idempotency',
    ) {
    }

    public function claim(
        string $moduleName,
        int $organizationId,
        string $source,
        string $idempotencyKey,
        ?string $requestHash = null,
        int $ttlSeconds = 86400 * 30
    ): bool {
        $this->validateScope($moduleName, $organizationId, $source, $idempotencyKey);

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + max(3600, $ttlSeconds));

        // Attempt direct insert first
        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} 
                (module_name, organization_id, source, idempotency_key, status, request_hash, response_code, created_at, updated_at, expires_at)
                VALUES (:module, :org_id, :source, :idemp_key, 'processing', :hash, 200, :created_at, :updated_at, :expires)");

            $stmt->execute([
                'module' => $moduleName,
                'org_id' => $organizationId,
                'source' => $source,
                'idemp_key' => $idempotencyKey,
                'hash' => $requestHash,
                'created_at' => $now,
                'updated_at' => $now,
                'expires' => $expiresAt,
            ]);

            return true;
        } catch (PDOException $e) {
            // Unique key collision or other error
        }

        // Row already exists — check state
        $existing = $this->getRaw($moduleName, $organizationId, $source, $idempotencyKey);
        if (!$existing) {
            return false;
        }

        $status = (string)$existing['status'];
        if ($status === 'completed') {
            return false;
        }

        $updatedAt = strtotime((string)$existing['updated_at']);
        $age = time() - $updatedAt;

        // If stale processing (e.g. process died) or previously failed, attempt atomic reclaim
        if ($status === 'failed' || ($status === 'processing' && $age > self::STALE_PROCESSING_SECONDS)) {
            $stmt = $this->pdo->prepare("UPDATE {$this->tableName} 
                SET status = 'processing', request_hash = :hash, updated_at = :now, expires_at = :expires
                WHERE module_name = :module AND organization_id = :org_id AND source = :source AND idempotency_key = :idemp_key AND status != 'completed'");

            $stmt->execute([
                'hash' => $requestHash,
                'now' => $now,
                'expires' => $expiresAt,
                'module' => $moduleName,
                'org_id' => $organizationId,
                'source' => $source,
                'idemp_key' => $idempotencyKey,
            ]);

            return $stmt->rowCount() > 0;
        }

        return false;
    }

    public function get(string $moduleName, int $organizationId, string $source, string $idempotencyKey): ?array
    {
        $raw = $this->getRaw($moduleName, $organizationId, $source, $idempotencyKey);
        if (!$raw) {
            return null;
        }

        $payload = null;
        if (!empty($raw['response_payload'])) {
            $payload = json_decode((string)$raw['response_payload'], true);
            if (!is_array($payload)) {
                $payload = null;
            }
        }

        return [
            'status' => (string)$raw['status'],
            'response_code' => (int)$raw['response_code'],
            'response_payload' => $payload,
            'resource_type' => $raw['resource_type'] !== null ? (string)$raw['resource_type'] : null,
            'resource_public_id' => $raw['resource_public_id'] !== null ? (string)$raw['resource_public_id'] : null,
            'request_hash' => $raw['request_hash'] !== null ? (string)$raw['request_hash'] : null,
            'created_at' => (string)$raw['created_at'],
        ];
    }

    public function complete(
        string $moduleName,
        int $organizationId,
        string $source,
        string $idempotencyKey,
        int $statusCode,
        array $payload,
        ?string $resourceType = null,
        ?string $resourcePublicId = null
    ): void {
        $this->validateScope($moduleName, $organizationId, $source, $idempotencyKey);

        $now = date('Y-m-d H:i:s');
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} 
            SET status = 'completed', response_code = :code, response_payload = :payload, resource_type = :res_type, resource_public_id = :res_id, updated_at = :now
            WHERE module_name = :module AND organization_id = :org_id AND source = :source AND idempotency_key = :idemp_key");

        $stmt->execute([
            'code' => $statusCode,
            'payload' => $payloadJson,
            'res_type' => $resourceType,
            'res_id' => $resourcePublicId,
            'now' => $now,
            'module' => $moduleName,
            'org_id' => $organizationId,
            'source' => $source,
            'idemp_key' => $idempotencyKey,
        ]);
    }

    public function fail(
        string $moduleName,
        int $organizationId,
        string $source,
        string $idempotencyKey,
        string $errorMessage,
        int $statusCode = 500
    ): void {
        $this->validateScope($moduleName, $organizationId, $source, $idempotencyKey);

        $now = date('Y-m-d H:i:s');
        $payloadJson = json_encode(['error' => $errorMessage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} 
            SET status = 'failed', response_code = :code, response_payload = :payload, updated_at = :now
            WHERE module_name = :module AND organization_id = :org_id AND source = :source AND idempotency_key = :idemp_key");

        $stmt->execute([
            'code' => $statusCode,
            'payload' => $payloadJson,
            'now' => $now,
            'module' => $moduleName,
            'org_id' => $organizationId,
            'source' => $source,
            'idemp_key' => $idempotencyKey,
        ]);
    }

    public function prune(): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at IS NOT NULL AND expires_at < :now");
        $stmt->execute(['now' => $now]);

        return $stmt->rowCount();
    }

    private function getRaw(string $moduleName, int $organizationId, string $source, string $idempotencyKey): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} 
            WHERE module_name = :module AND organization_id = :org_id AND source = :source AND idempotency_key = :idemp_key");

        $stmt->execute([
            'module' => $moduleName,
            'org_id' => $organizationId,
            'source' => $source,
            'idemp_key' => $idempotencyKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function validateScope(string $moduleName, int $organizationId, string $source, string $idempotencyKey): void
    {
        if ($moduleName === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,189}$/i', $moduleName)) {
            throw new InvalidArgumentException('Invalid module name for idempotency store');
        }
        if ($organizationId <= 0) {
            throw new InvalidArgumentException('Idempotency must be scoped to an authoritative workspace');
        }
        if ($source === '' || strlen($source) > 64) {
            throw new InvalidArgumentException('Invalid source for idempotency store');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 190) {
            throw new InvalidArgumentException('Invalid idempotency key');
        }
    }
}
