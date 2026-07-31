<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\ApiClient\ApiClientRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class ApiClientService
{
    public function __construct(
        private readonly ApiClientRepository $repository,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger
    ) {
    }

    public function listClients(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repository->listClients($filters);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function getClient(string $publicId): ?array
    {
        return $this->normalizeClient($this->repository->findClientByPublicId($publicId));
    }

    public function createClient(array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('apc');
        $this->repository->createClient([
            'public_id' => $publicId,
            'title' => trim((string)($input['title'] ?? '')),
            'scopes' => json_encode($this->normalizeScopes($input['scopes'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => (int)($input['is_active'] ?? 1) === 1 ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'api_client_create',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_client',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'client' => $this->getClient($publicId)];
    }

    public function updateClient(string $publicId, array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $current = $this->repository->findClientByPublicId($publicId);
        if (!$current) {
            return ['ok' => false, 'code' => 'API_CLIENT_NOT_FOUND'];
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = (int)(((string)$input['is_active'] === '1' || (string)$input['is_active'] === 'true') ? 1 : 0);
        }
        if (array_key_exists('scopes', $input)) {
            $set['scopes'] = json_encode($this->normalizeScopes($input['scopes']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->repository->updateClientByPublicId($publicId, $set);

        $this->logger->audit([
            'action' => 'api_client_update',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_client',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'client' => $this->getClient($publicId)];
    }

    public function deleteClient(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $client = $this->repository->findClientByPublicId($publicId);
        if (!$client) {
            return ['ok' => false, 'code' => 'API_CLIENT_NOT_FOUND'];
        }

        $activeKeys = $this->repository->activeKeyCountByClientId((int)$client['id']);
        if ($activeKeys > 0) {
            return ['ok' => false, 'code' => 'API_CLIENT_HAS_ACTIVE_KEYS'];
        }

        $this->repository->deleteClientByPublicId($publicId);

        $this->logger->audit([
            'action' => 'api_client_delete',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_client',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true];
    }

    public function listKeys(string $clientPublicId): array
    {
        $client = $this->repository->findClientByPublicId($clientPublicId);
        if (!$client) {
            return ['ok' => false, 'code' => 'API_CLIENT_NOT_FOUND'];
        }

        return [
            'ok' => true,
            'client' => $this->normalizeClient($client),
            'items' => $this->repository->listKeysByClientId((int)$client['id']),
        ];
    }

    public function issueKey(string $clientPublicId, array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $client = $this->repository->findClientByPublicId($clientPublicId);
        if (!$client) {
            return ['ok' => false, 'code' => 'API_CLIENT_NOT_FOUND'];
        }
        if ((int)($client['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'API_CLIENT_INACTIVE'];
        }

        $scopes = array_key_exists('scopes', $input) ? $this->normalizeScopes($input['scopes']) : (array)($client['scopes'] ?? []);
        $expiresAt = trim((string)($input['expires_at'] ?? ''));
        if ($expiresAt === '') {
            $expiresAt = null;
        }

        $plain = 'apk_' . $this->tokens->generate(32);
        $keyPublicId = Ulid::generate('apk');
        $now = gmdate('Y-m-d H:i:s');
        $this->repository->createKey([
            'public_id' => $keyPublicId,
            'client_id' => (int)$client['id'],
            'user_id' => (int)($actor['id'] ?? 0) > 0 ? (int)$actor['id'] : null,
            'key_hash' => $this->tokens->hash($plain),
            'scopes' => json_encode($scopes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'created_at' => $now,
        ]);

        $key = $this->repository->findKeyByPublicId($keyPublicId);

        $this->logger->audit([
            'action' => 'api_key_issue',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_key',
            'entity_public_id' => $keyPublicId,
            'client_public_id' => $clientPublicId,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'api_key_issue',
            'ip' => null,
            'user_agent' => null,
            'details' => ['key_public_id' => $keyPublicId, 'client_public_id' => $clientPublicId],
        ]);

        return ['ok' => true, 'key' => $key, 'plain_key' => $plain];
    }

    public function rotateKey(string $keyPublicId, array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $current = $this->repository->findKeyByPublicId($keyPublicId);
        if (!$current) {
            return ['ok' => false, 'code' => 'API_KEY_NOT_FOUND'];
        }
        if (!empty($current['revoked_at'])) {
            return ['ok' => false, 'code' => 'API_KEY_REVOKED'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->repository->revokeKey($keyPublicId, $now);

        $scopes = array_key_exists('scopes', $input) ? $this->normalizeScopes($input['scopes']) : (array)($current['scopes'] ?? []);
        $expiresAt = trim((string)($input['expires_at'] ?? (string)($current['expires_at'] ?? '')));
        if ($expiresAt === '') {
            $expiresAt = null;
        }

        $plain = 'apk_' . $this->tokens->generate(32);
        $newPublicId = Ulid::generate('apk');
        $this->repository->createKey([
            'public_id' => $newPublicId,
            'client_id' => (int)$current['client_id'],
            'user_id' => (int)($actor['id'] ?? 0) > 0 ? (int)$actor['id'] : null,
            'key_hash' => $this->tokens->hash($plain),
            'scopes' => json_encode($scopes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'created_at' => $now,
        ]);

        $new = $this->repository->findKeyByPublicId($newPublicId);

        $this->logger->audit([
            'action' => 'api_key_rotate',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_key',
            'entity_public_id' => $newPublicId,
            'previous_key_public_id' => $keyPublicId,
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'api_key_rotate',
            'ip' => null,
            'user_agent' => null,
            'details' => ['previous_key_public_id' => $keyPublicId, 'new_key_public_id' => $newPublicId],
        ]);

        return ['ok' => true, 'key' => $new, 'plain_key' => $plain];
    }

    public function revokeKey(string $keyPublicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $current = $this->repository->findKeyByPublicId($keyPublicId);
        if (!$current) {
            return ['ok' => false, 'code' => 'API_KEY_NOT_FOUND'];
        }
        if (!empty($current['revoked_at'])) {
            return ['ok' => false, 'code' => 'API_KEY_REVOKED'];
        }

        $this->repository->revokeKey($keyPublicId, gmdate('Y-m-d H:i:s'));

        $this->logger->audit([
            'action' => 'api_key_revoke',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_key',
            'entity_public_id' => $keyPublicId,
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'api_key_revoke',
            'ip' => null,
            'user_agent' => null,
            'details' => ['key_public_id' => $keyPublicId],
        ]);

        return ['ok' => true, 'key' => $this->repository->findKeyByPublicId($keyPublicId)];
    }

    public function usage(string $keyPublicId, int $limit = 50): array
    {
        $key = $this->repository->findKeyByPublicId($keyPublicId);
        if (!$key) {
            return ['ok' => false, 'code' => 'API_KEY_NOT_FOUND'];
        }

        return [
            'ok' => true,
            'key' => $key,
            'logs' => $this->repository->listKeyLogs($keyPublicId, $limit),
        ];
    }

    /** @return list<string> */
    private function normalizeScopes(mixed $value): array
    {
        if (!is_array($value)) {
            $raw = trim((string)$value);
            if ($raw === '') {
                return [];
            }

            $json = json_decode($raw, true);
            if (is_array($json)) {
                $value = $json;
            } else {
                $value = explode(',', $raw);
            }
        }

        $out = [];
        foreach ($value as $item) {
            $scope = trim((string)$item);
            if ($scope === '') {
                continue;
            }
            if (strlen($scope) > 128) {
                $scope = substr($scope, 0, 128);
            }
            $out[] = $scope;
        }

        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private function normalizeClient(?array $row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'public_id' => (string)$row['public_id'],
            'title' => (string)($row['title'] ?? ''),
            'scopes' => is_array($row['scopes'] ?? null) ? array_values($row['scopes']) : [],
            'is_active' => (int)($row['is_active'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
