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

    public function findByKeyHash(string $keyHash): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('idempotency_keys')
            ->select(['public_id', 'key_hash', 'route', 'response_payload', 'created_at'])
            ->where('key_hash', '=', $keyHash)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function save(string $keyHash, string $route, string $responsePayload, string $createdAt): void
    {
        (new QueryBuilder($this->pdo))
            ->from('idempotency_keys')
            ->insert([
                'public_id' => 'idm_' . strtoupper(bin2hex(random_bytes(8))),
                'key_hash' => $keyHash,
                'route' => $route,
                'response_payload' => $responsePayload,
                'created_at' => $createdAt,
            ]);
    }
}
