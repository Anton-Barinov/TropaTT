<?php
declare(strict_types=1);

namespace Api\Model\Ai;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class AiProviderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters)->count();
        $items = $this->buildListQuery($filters)
            ->select([
                'id',
                'public_id',
                'provider_code',
                'title',
                'base_url',
                'api_path',
                'default_model',
                'timeout_ms',
                'max_tokens',
                'temperature',
                'extra_headers',
                'provider_payload',
                'is_active',
                'is_default',
                'created_at',
                'updated_at',
            ])
            ->orderBy('is_default', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('public_id', '=', $publicId)
            ->where('deleted_at', 'IS', null)
            ->first();
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('id', '=', $id)
            ->where('deleted_at', 'IS', null)
            ->first();
    }

    public function findDefaultActive(): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('is_active', '=', 1)
            ->where('is_default', '=', 1)
            ->where('deleted_at', 'IS', null)
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    public function findAnyActive(): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('is_active', '=', 1)
            ->where('deleted_at', 'IS', null)
            ->orderBy('is_default', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    public function create(array $payload): string
    {
        $publicId = Ulid::generate('aip');
        $payload['public_id'] = $publicId;

        (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->insert($payload);

        return $publicId;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('public_id', '=', $publicId)
            ->where('deleted_at', 'IS', null)
            ->update($set) > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $now, int $actorUserId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('public_id', '=', $publicId)
            ->where('deleted_at', 'IS', null)
            ->update([
                'is_active' => 0,
                'is_default' => 0,
                'deleted_at' => $now,
                'updated_at' => $now,
                'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ]) > 0;
    }

    public function unsetDefaultForOthers(string $exceptPublicId): void
    {
        (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('public_id', '<>', $exceptPublicId)
            ->where('deleted_at', 'IS', null)
            ->update([
                'is_default' => 0,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
    }

    public function setSecret(int $providerId, string $encryptedSecret, string $keyHint, int $actorUserId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->secretByProviderId($providerId);
        if ($existing) {
            (new QueryBuilder($this->pdo))
                ->from('ai_provider_secrets')
                ->where('provider_id', '=', $providerId)
                ->update([
                    'secret_encrypted' => $encryptedSecret,
                    'key_hint' => $keyHint,
                    'rotated_at' => $now,
                    'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'updated_at' => $now,
                ]);
            return;
        }

        (new QueryBuilder($this->pdo))
            ->from('ai_provider_secrets')
            ->insert([
                'public_id' => Ulid::generate('ais'),
                'provider_id' => $providerId,
                'secret_encrypted' => $encryptedSecret,
                'key_hint' => $keyHint,
                'rotated_at' => $now,
                'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function deleteSecret(int $providerId, int $actorUserId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        (new QueryBuilder($this->pdo))
            ->from('ai_provider_secrets')
            ->where('provider_id', '=', $providerId)
            ->update([
                'secret_encrypted' => '',
                'key_hint' => null,
                'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'updated_at' => $now,
            ]);
    }

    public function hasSecret(int $providerId): bool
    {
        $row = $this->secretByProviderId($providerId);
        if (!$row) {
            return false;
        }

        return trim((string)($row['secret_encrypted'] ?? '')) !== '';
    }

    public function encryptedSecretByProviderId(int $providerId): ?string
    {
        $row = $this->secretByProviderId($providerId);
        if (!$row) {
            return null;
        }

        $secret = trim((string)($row['secret_encrypted'] ?? ''));
        return $secret !== '' ? $secret : null;
    }

    private function secretByProviderId(int $providerId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_provider_secrets')
            ->where('provider_id', '=', $providerId)
            ->first();
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('ai_providers')
            ->where('deleted_at', 'IS', null);

        if (!empty($filters['search'])) {
            $needle = '%' . LikeEscaper::escape(trim((string)$filters['search'])) . '%';
            $query->whereRaw('(title LIKE ? OR provider_code LIKE ? OR base_url LIKE ?)', [$needle, $needle, $needle]);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', '=', (int)(((string)$filters['is_active'] === '1' || (string)$filters['is_active'] === 'true') ? 1 : 0));
        }

        return $query;
    }
}
