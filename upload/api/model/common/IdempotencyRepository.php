<?php
declare(strict_types=1);

namespace Api\Model\Common;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class IdempotencyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByKeyHash(string $keyHash): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('idempotency_keys')
            ->select([
                'public_id',
                'key_hash',
                'route',
                'response_payload',
                'actor_public_id',
                'organization_public_id',
                'request_hash',
                'status',
                'created_at',
                'completed_at',
            ])
            ->where('key_hash', '=', $keyHash)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Atomically reserve the key by inserting an in_flight row.
     * Returns null when another request already holds or holds the key.
     *
     * @return array{public_id:string,status:string,created_at:string}|null
     */
    public function tryClaim(
        string $keyHash,
        string $route,
        ?string $actorPublicId,
        ?string $organizationPublicId,
        string $requestHash,
        string $createdAt
    ): ?array {
        $publicId = 'idm_' . strtoupper(bin2hex(random_bytes(8)));

        try {
            (new QueryBuilder($this->pdo))
                ->from('idempotency_keys')
                ->insert([
                    'public_id' => $publicId,
                    'key_hash' => $keyHash,
                    'route' => $route,
                    'response_payload' => null,
                    'actor_public_id' => $actorPublicId,
                    'organization_public_id' => $organizationPublicId,
                    'request_hash' => $requestHash,
                    'status' => 'in_flight',
                    'completed_at' => null,
                    'created_at' => $createdAt,
                ]);
        } catch (\PDOException $e) {
            $sqlState = (string)$e->getCode();
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($sqlState === '23000' || $sqlState === '23505' || $driverCode === 1062) {
                return null;
            }

            throw $e;
        }

        return [
            'public_id' => $publicId,
            'status' => 'in_flight',
            'created_at' => $createdAt,
        ];
    }

    /** Publish a claimed key: the claim becomes a completed replayable record. */
    public function completeClaim(string $publicId, string $responsePayload, string $completedAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE idempotency_keys
             SET response_payload = :response_payload, status = :status, completed_at = :completed_at
             WHERE public_id = :public_id AND status = :expected'
        );
        $stmt->execute([
            'response_payload' => $responsePayload,
            'status' => 'completed',
            'completed_at' => $completedAt,
            'public_id' => $publicId,
            'expected' => 'in_flight',
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Atomically take over a stale in_flight claim left behind by a crashed request. */
    public function takeOverStaleClaim(
        string $keyHash,
        ?string $actorPublicId,
        ?string $organizationPublicId,
        string $requestHash,
        string $staleBefore,
        string $now
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE idempotency_keys
             SET actor_public_id = :actor_public_id,
                 organization_public_id = :organization_public_id,
                 request_hash = :request_hash,
                 response_payload = NULL,
                 status = :status,
                 completed_at = NULL,
                 created_at = :now
             WHERE key_hash = :key_hash AND status = :expected AND created_at < :stale_before'
        );
        $stmt->execute([
            'actor_public_id' => $actorPublicId,
            'organization_public_id' => $organizationPublicId,
            'request_hash' => $requestHash,
            'status' => 'in_flight',
            'now' => $now,
            'key_hash' => $keyHash,
            'expected' => 'in_flight',
            'stale_before' => $staleBefore,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Retention: drop completed records past the TTL and abandoned in_flight claims. */
    public function purgeExpired(string $completedCutoff, string $inFlightCutoff, int $limit): int
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare(
            "SELECT id FROM idempotency_keys
             WHERE (status = :done AND created_at < :completed_cutoff)
                OR (status = :flight AND created_at < :inflight_cutoff)
             LIMIT {$limit}"
        );
        $stmt->execute([
            'done' => 'completed',
            'completed_cutoff' => $completedCutoff,
            'flight' => 'in_flight',
            'inflight_cutoff' => $inFlightCutoff,
        ]);
        $ids = array_map('intval', array_column($stmt->fetchAll() ?: [], 'id'));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $delete = $this->pdo->prepare("DELETE FROM idempotency_keys WHERE id IN ({$placeholders})");
        $delete->execute($ids);

        return $delete->rowCount();
    }
}
