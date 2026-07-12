<?php
declare(strict_types=1);

namespace Api\Model\ApiClient;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ApiClientRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listClients(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildClientsListQuery($filters)->count();
        $rows = $this->buildClientsListQuery($filters)
            ->select(['public_id', 'title', 'scopes', 'is_active', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
        foreach ($rows as &$row) {
            $row['scopes'] = $this->decodeScopes($row['scopes'] ?? null);
            $row['is_active'] = (int)($row['is_active'] ?? 0);
        }
        unset($row);

        return [$rows, $total, $page, $limit];
    }

    private function buildClientsListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('api_clients');

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->whereRaw('(title LIKE ? OR public_id LIKE ?)', [$search, $search]);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where(
                'is_active',
                '=',
                (int)(((string)$filters['is_active'] === '1' || (string)$filters['is_active'] === 'true') ? 1 : 0)
            );
        }

        return $query;
    }

    public function findClientByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('api_clients')
            ->select(['*'])
            ->where('public_id', '=', $publicId)
            ->first();
        if (!$row) {
            return null;
        }

        $row['scopes'] = $this->decodeScopes($row['scopes'] ?? null);
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        return $row;
    }

    public function createClient(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('api_clients')
            ->insert($payload);
    }

    public function updateClientByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('api_clients')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteClientByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('api_clients')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function listKeysByClientId(int $clientId): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('api_keys')
            ->select(['public_id', 'scopes', 'expires_at', 'revoked_at', 'created_at'])
            ->where('client_id', '=', $clientId)
            ->orderBy('created_at', 'DESC')
            ->get();
        foreach ($rows as &$row) {
            $row['scopes'] = $this->decodeScopes($row['scopes'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public function activeKeyCountByClientId(int $clientId): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('api_keys')
            ->where('client_id', '=', $clientId)
            ->whereNull('revoked_at')
            ->count();
    }

    public function createKey(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('api_keys')
            ->insert($payload);
    }

    public function findKeyByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('api_keys k')
            ->join('api_clients c', 'c.id', '=', 'k.client_id')
            ->select(['k.*', 'c.public_id AS client_public_id', 'c.title AS client_title'])
            ->where('k.public_id', '=', $publicId)
            ->first();
        if (!$row) {
            return null;
        }

        $row['scopes'] = $this->decodeScopes($row['scopes'] ?? null);
        return $row;
    }

    public function revokeKey(string $publicId, string $revokedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('api_keys')
            ->where('public_id', '=', $publicId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) > 0;
    }

    public function listKeyLogs(string $keyPublicId, int $limit = 50): array
    {
        $limit = min(200, max(1, $limit));

        $auditRows = (new QueryBuilder($this->pdo))
            ->from('audit_logs')
            ->select(['public_id', 'created_at', 'action', 'details'])
            ->where('entity_type', '=', 'api_key')
            ->where('entity_public_id', '=', $keyPublicId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        $securityRows = (new QueryBuilder($this->pdo))
            ->from('security_logs')
            ->select(['public_id', 'created_at', 'event_type', 'details'])
            ->where('event_type', 'LIKE', 'api_key_%')
            ->where('details', 'LIKE', '%"' . $keyPublicId . '"%')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        return [
            'audit' => $auditRows,
            'security' => $securityRows,
        ];
    }

    /** @return list<string> */
    private function decodeScopes(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $value), static fn(string $v): bool => $v !== ''));
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $json), static fn(string $v): bool => $v !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== ''));
    }
}
