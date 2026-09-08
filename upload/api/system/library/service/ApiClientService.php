<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\ApiClient\ApiClientRepository;
use Api\Model\Auth\AuthRepository;
use Api\Model\Permission\PermissionRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class ApiClientService
{
    public function __construct(
        private readonly ApiClientRepository $repository,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly ?AuthRepository $authRepository = null,
        private readonly ?PermissionRepository $permissionRepository = null
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
        if (!$this->actorCanManage($actor)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $scopeResult = $this->resolveStoredScopes($input, $actor, []);
        if (!$scopeResult['ok']) {
            return $scopeResult;
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('apc');
        $this->repository->createClient([
            'public_id' => $publicId,
            'title' => trim((string)($input['title'] ?? '')),
            'scopes' => json_encode($scopeResult['scopes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

        // Client row is fetched before the key insert so the raw id is used
        // for the auto-issued first key (never the normalized response).
        $rawClient = $this->repository->findClientByPublicId($publicId);

        $plain = 'apk_' . $this->tokens->generate(32);
        $keyPublicId = Ulid::generate('apk');
        $this->repository->createKey([
            'public_id' => $keyPublicId,
            'client_id' => (int)($rawClient['id'] ?? 0),
            'user_id' => (int)($actor['id'] ?? 0) > 0 ? (int)$actor['id'] : null,
            'name' => $this->normalizeName($input['key_name'] ?? null),
            'key_hash' => $this->tokens->hash($plain),
            'key_preview' => $this->keyPreview($plain),
            'scopes' => json_encode($scopeResult['scopes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $this->normalizeExpiresAt($input['key_expires_at'] ?? null),
            'revoked_at' => null,
            'created_at' => $now,
        ]);

        $key = $this->repository->findKeyByPublicId($keyPublicId);

        $this->logger->audit([
            'action' => 'api_key_issue',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'api_key',
            'entity_public_id' => $keyPublicId,
            'client_public_id' => $publicId,
            'scopes' => $scopeResult['scopes'],
        ]);
        $this->logger->security([
            'actor_public_id' => $actor['public_id'] ?? null,
            'event_type' => 'api_key_issue',
            'ip' => null,
            'user_agent' => null,
            'details' => ['key_public_id' => $keyPublicId, 'client_public_id' => $publicId],
        ]);

        // Re-read after the insert so keys_count/active_keys_count already
        // include the just-issued first key in the creation response.
        return ['ok' => true, 'client' => $this->getClient($publicId), 'key' => $key, 'plain_key' => $plain];
    }

    public function updateClient(string $publicId, array $input, array $actor): array
    {
        if (!$this->actorCanManage($actor)) {
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
            $scopeResult = $this->resolveStoredScopes($input, $actor, []);
            if (!$scopeResult['ok']) {
                return $scopeResult;
            }
            $set['scopes'] = json_encode($scopeResult['scopes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    public function deleteClient(string $publicId, array $actor, array $input = []): array
    {
        if (!$this->actorCanManage($actor)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $client = $this->repository->findClientByPublicId($publicId);
        if (!$client) {
            return ['ok' => false, 'code' => 'API_CLIENT_NOT_FOUND'];
        }

        $nonRevokedKeys = $this->repository->nonRevokedKeyCountByClientId((int)$client['id']);
        if ($nonRevokedKeys > 0) {
            $revokeKeys = $input['revoke_keys'] ?? false;
            $revokeKeys = $revokeKeys === true || $revokeKeys === 1 || $revokeKeys === '1' || $revokeKeys === 'true';
            if (!$revokeKeys) {
                return ['ok' => false, 'code' => 'API_CLIENT_HAS_ACTIVE_KEYS'];
            }

            // Cascade: the UI asks the admin to confirm, then sends revoke_keys=1
            // so deleting an integration removes every one of its keys in one step.
            $keys = $this->repository->listKeysByClientId((int)$client['id']);
            $revokedAt = gmdate('Y-m-d H:i:s');
            foreach ($keys as $keyRow) {
                if (!empty($keyRow['revoked_at'])) {
                    continue;
                }
                $keyPubId = (string)($keyRow['public_id'] ?? '');
                if ($keyPubId === '') {
                    continue;
                }
                $this->repository->revokeKey($keyPubId, $revokedAt);
                $this->logger->audit([
                    'action' => 'api_key_revoke',
                    'actor_public_id' => $actor['public_id'] ?? null,
                    'entity_type' => 'api_key',
                    'entity_public_id' => $keyPubId,
                    'client_public_id' => $publicId,
                    'reason' => 'client_delete_cascade',
                ]);
                $this->logger->security([
                    'actor_public_id' => $actor['public_id'] ?? null,
                    'event_type' => 'api_key_revoke',
                    'ip' => null,
                    'user_agent' => null,
                    'details' => ['key_public_id' => $keyPubId, 'client_public_id' => $publicId, 'reason' => 'client_delete_cascade'],
                ]);
            }
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
        if (!$this->actorCanManage($actor)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $client = $this->repository->findClientByPublicId($clientPublicId);
        if (!$client) {
            return ['ok' => false, 'code' => 'API_CLIENT_NOT_FOUND'];
        }
        if ((int)($client['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'API_CLIENT_INACTIVE'];
        }

        $clientScopes = is_array($client['scopes'] ?? null) ? $client['scopes'] : [];
        $scopeResult = $this->resolveStoredScopes($input, $actor, $clientScopes);
        if (!$scopeResult['ok']) {
            return $scopeResult;
        }

        $plain = 'apk_' . $this->tokens->generate(32);
        $keyPublicId = Ulid::generate('apk');
        $now = gmdate('Y-m-d H:i:s');
        $this->repository->createKey([
            'public_id' => $keyPublicId,
            'client_id' => (int)$client['id'],
            'user_id' => (int)($actor['id'] ?? 0) > 0 ? (int)$actor['id'] : null,
            'name' => $this->normalizeName($input['name'] ?? null),
            'key_hash' => $this->tokens->hash($plain),
            'key_preview' => $this->keyPreview($plain),
            'scopes' => json_encode($scopeResult['scopes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $this->normalizeExpiresAt($input['expires_at'] ?? null),
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
            'scopes' => $scopeResult['scopes'],
            'expires_at' => $scopeResult['expires_at'],
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
        if (!$this->actorCanManage($actor)) {
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

        $currentScopes = is_array($current['scopes'] ?? null) ? $current['scopes'] : [];
        $scopes = array_key_exists('scopes', $input)
            ? $this->normalizeScopes($input['scopes'])
            : $currentScopes;

        $plain = 'apk_' . $this->tokens->generate(32);
        $newPublicId = Ulid::generate('apk');
        $this->repository->createKey([
            'public_id' => $newPublicId,
            'client_id' => (int)$current['client_id'],
            'user_id' => (int)($actor['id'] ?? 0) > 0 ? (int)$actor['id'] : null,
            'name' => $this->normalizeName($input['name'] ?? ($current['name'] ?? null)),
            'key_hash' => $this->tokens->hash($plain),
            'key_preview' => $this->keyPreview($plain),
            'scopes' => json_encode($scopes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $this->normalizeExpiresAt($input['expires_at'] ?? ($current['expires_at'] ?? null)),
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
        if (!$this->actorCanManage($actor)) {
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

    public function authenticateByKey(string $accessToken): ?array
    {
        $hash = $this->tokens->hash($accessToken);
        $key = $this->repository->findValidKeyByHash($hash);

        if (!$key) {
            return null;
        }

        $expiresAt = trim((string)($key['expires_at'] ?? ''));
        if ($expiresAt !== '') {
            $expiresAtTime = strtotime($expiresAt . ' UTC');
            if ($expiresAtTime !== false && $expiresAtTime < time()) {
                return null;
            }
        }

        $userId = (int)($key['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $roleCodes = $this->authRepository !== null
            ? $this->authRepository->roleCodesByUserId($userId)
            : [];

        $isRoot = (bool)($key['is_root'] ?? false);
        if (!$isRoot && in_array('super_admin', $roleCodes, true)) {
            $isRoot = true;
        }

        $baseCodes = $isRoot
            ? ['*']
            : ($this->authRepository !== null
                ? $this->authRepository->permissionCodesByUserId($userId)
                : []);

        // Scope restriction: a key is limited to the intersection of the bound
        // user's own permissions and the stored client/key scope sets. Empty
        // scope sets mean "no restriction" (inherit the user's perms); a stored
        // '*' also means unrestricted (full access). The key can therefore
        // never exceed the account that issued it, only narrow it.
        $clientScopes = is_array($key['client_scopes'] ?? null) ? $key['client_scopes'] : [];
        $keyScopes = is_array($key['scopes'] ?? null) ? $key['scopes'] : [];
        $restricted = $this->restrictionApplied($clientScopes, $keyScopes);

        $effectiveCodes = $baseCodes;
        foreach ([$clientScopes, $keyScopes] as $restriction) {
            $codes = is_array($restriction) ? $restriction : [];
            if ($codes === [] || $codes === ['*']) {
                continue;
            }
            $effectiveCodes = $this->intersectCodes($effectiveCodes, $codes);
        }

        // A root-bound key stays root only when no scope restriction is stored.
        // A non-root-bound key is never root; scopes only narrow its codes.
        $effectiveRoot = $baseCodes === ['*'] && !$restricted;
        if ($baseCodes === ['*'] && $restricted) {
            $effectiveCodes = $effectiveCodes === ['*'] ? [] : $effectiveCodes;
        }

        $user = [
            'id' => $userId,
            'public_id' => (string)($key['user_public_id'] ?? ''),
            'login' => (string)($key['login'] ?? ''),
            'email' => (string)($key['email'] ?? ''),
            'full_name' => (string)($key['full_name'] ?? ''),
            'locale' => (string)($key['locale'] ?? 'en-gb'),
            // A scope-restricted key is never a root actor: financial data and
            // admin bypasses must not apply through a restricted credential.
            'is_root' => $effectiveRoot,
            'is_active' => (bool)($key['is_active'] ?? true),
            'is_external' => (bool)($key['is_external'] ?? false),
            'external_role' => (bool)($key['is_external'] ?? false)
                ? (((string)($key['external_role'] ?? 'observer')) === 'executor' ? 'executor' : 'observer')
                : 'observer',
            'roles' => $roleCodes,
            'permission_codes' => $effectiveCodes,
            // Lets AuthzService treat an empty explicit list as a real deny
            // (never falling back to the DB role set of the bound user).
            'scope_restricted' => $restricted,
        ];

        return [
            'session_public_id' => null,
            'expires_at' => null,
            'expires_in' => null,
            'user' => $user,
        ];
    }

    /**
     * Options used by the API-clients admin page: the permission catalog the
     * creator may grant (never exceeding their own rights) plus the explicit
     * allow-list of codes the current actor may grant (all catalog codes for
     * root, otherwise their own role-derived codes). The UI renders one
     * checkbox per grantable code, defaults every one to checked, and lets the
     * owner uncheck to narrow the client/key below their own rights.
     *
     * @return array{ok:bool,code?:string,catalog?:array<int,array{code:string,title:string}>,grantable_codes?:array<int,string>}
     */
    public function pageOptions(array $actor): array
    {
        $catalog = [];
        if ($this->permissionRepository !== null) {
            $rows = $this->permissionRepository->list();
            foreach ($rows as $row) {
                $code = (string)($row['code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $catalog[] = [
                    'code' => $code,
                    'title' => (string)($row['title'] ?? $code),
                ];
            }
        }

        $actorCodes = $this->actorPermissionCodes($actor);
        $isRoot = $actorCodes === ['*'] || (bool)($actor['is_root'] ?? false);
        if ($isRoot) {
            $grantable = array_values(array_map(
                static fn(array $row): string => $row['code'],
                $catalog
            ));
        } else {
            $grantable = array_values($actorCodes);
        }

        sort($grantable);

        return [
            'ok' => true,
            'catalog' => $catalog,
            'grantable_codes' => array_values(array_unique($grantable)),
        ];
    }

    /**
     * Resolve what scope codes may be stored for a new/updated client or key.
     *
     * Rules (fail-closed, never exceed the actor):
     *  - absent/empty input => no restriction ([]): the key inherits the bound
     *    user's own permission codes at authentication time;
     *  - explicit codes are intersected with the actor's own permission codes
     *    and (for keys) with the owning client's scope set;
     *  - unknown codes that survive neither the actor set nor the catalog are
     *    rejected so a typo can never silently lock a key out.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @param array<int,string>   $clientScopes
     * @return array{ok:bool,code?:string,scopes?:array<int,string>,expires_at?:?string}
     */
    private function resolveStoredScopes(array $input, array $actor, array $clientScopes): array
    {
        $requested = $this->normalizeScopes($input['scopes'] ?? []);

        if ($requested === []) {
            return [
                'ok' => true,
                'scopes' => [],
                'expires_at' => $this->normalizeExpiresAt($input['expires_at'] ?? ($input['key_expires_at'] ?? null)),
            ];
        }

        // The actor's own ceiling: never store a scope the creator cannot use.
        $allowed = $this->actorPermissionCodes($actor);
        if ($allowed !== ['*']) {
            $requested = array_values(array_intersect($requested, $allowed));
            if ($requested === []) {
                return ['ok' => false, 'code' => 'SCOPE_EXCEEDS_ACTOR'];
            }
        }

        // A key can never be broader than its owning client.
        if ($clientScopes !== []) {
            $requested = array_values(array_intersect($requested, $clientScopes));
            if ($requested === []) {
                return ['ok' => false, 'code' => 'SCOPE_EXCEEDS_CLIENT'];
            }
        }

        $requested = array_values(array_unique($requested));
        sort($requested);

        return [
            'ok' => true,
            'scopes' => $requested,
            'expires_at' => $this->normalizeExpiresAt($input['expires_at'] ?? ($input['key_expires_at'] ?? null)),
        ];
    }

    /**
     * @param array<int,string> $baseCodes
     * @param array<int,string> $codes
     * @return array<int,string>
     */
    private function intersectCodes(array $baseCodes, array $codes): array
    {
        if ($baseCodes === ['*']) {
            return array_values(array_unique($codes));
        }

        return array_values(array_unique(array_intersect($baseCodes, $codes)));
    }

    /** @param array<int,string> $clientScopes @param array<int,string> $keyScopes */
    private function restrictionApplied(array $clientScopes, array $keyScopes): bool
    {
        foreach ([$clientScopes, $keyScopes] as $restriction) {
            $codes = is_array($restriction) ? $restriction : [];
            if ($codes !== [] && $codes !== ['*']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Manage gate for API-client mutations. Root always passes; other actors
     * must hold the api_client.manage permission code (the same code the REST
     * route layer requires), so a scoped key or a delegated admin can never
     * widen itself beyond what api_client.manage already grants.
     *
     * @param array<string,mixed> $actor
     */
    private function actorCanManage(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $codes = $this->actorPermissionCodes($actor);
        return in_array('api_client.manage', $codes, true);
    }

    /**
     * Permission codes the current actor may grant. Root (or an explicit ['*'])
     * returns ['*'] (everything); other users return their own role codes.
     *
     * @param array<string,mixed> $actor
     * @return array<int,string>
     */
    private function actorPermissionCodes(array $actor): array
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return ['*'];
        }

        $explicit = $actor['permission_codes'] ?? null;
        if (is_array($explicit)) {
            $codes = array_values(array_filter(array_map(
                static fn($v): string => trim((string)$v),
                $explicit
            ), static fn(string $v): bool => $v !== ''));
            if (in_array('*', $codes, true)) {
                return ['*'];
            }
            if ($codes !== []) {
                return $codes;
            }
        }

        $userId = (int)($actor['id'] ?? 0);
        if ($userId > 0 && $this->authRepository !== null) {
            return $this->authRepository->permissionCodesByUserId($userId);
        }

        return [];
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

    private function normalizeName(mixed $name): ?string
    {
        $raw = trim((string)($name ?? ''));
        if ($raw === '') {
            return null;
        }

        return mb_substr($raw, 0, 255);
    }

    private function normalizeExpiresAt(mixed $value): ?string
    {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $raw);
        $ts = strtotime($normalized);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }

    /**
     * Generate a cosmetic preview of the plain key: first 7 + last 4 chars.
     * e.g. "apk_RIkQjaZNTxCo..." -> "apk_RIkQjaZ*****00A"
     */
    private function keyPreview(string $plain): string
    {
        if (strlen($plain) <= 14) {
            return $plain;
        }
        return substr($plain, 0, 8) . '*****' . substr($plain, -4);
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
            'keys_count' => (int)($row['keys_count'] ?? 0),
            'active_keys_count' => (int)($row['active_keys_count'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
