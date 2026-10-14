<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AppCapabilitiesService
{
    /** @var list<string> */
    private const IDEMPOTENCY_CONFLICT_CODES = [
        'IDEMPOTENCY_IN_PROGRESS',
        'IDEMPOTENCY_PAYLOAD_MISMATCH',
        'IDEMPOTENCY_ORG_MISMATCH',
    ];

    /**
     * Minimal authenticated manifest for the mobile client: actor identity,
     * active workspace, effective permissions and the supported protocol
     * contract. Contains no secrets and no server internals.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed>|null $organization
     * @param list<string> $permissions
     * @return array<string,mixed>
     */
    public function manifest(array $user, ?array $organization, array $permissions, int $sessionTtlSeconds): array
    {
        return [
            'api_version' => 'v1',
            'contract' => [
                'idempotency' => true,
                'idempotency_key_max_length' => 255,
                'idempotency_conflict_codes' => self::IDEMPOTENCY_CONFLICT_CODES,
                'row_version' => true,
                'workspace_scope' => true,
                'workspace_header' => 'X-Organization-Public-Id',
            ],
            'actor' => [
                'public_id' => (string)($user['public_id'] ?? ''),
                'login' => (string)($user['login'] ?? ''),
                'full_name' => (string)($user['full_name'] ?? ''),
                'locale' => (string)($user['locale'] ?? ''),
                'is_root' => (bool)($user['is_root'] ?? false),
                'is_active' => (bool)($user['is_active'] ?? true),
                'is_external' => (bool)($user['is_external'] ?? false),
                'external_role' => (string)($user['external_role'] ?? 'observer'),
                'roles' => array_values(array_map('strval', (array)($user['roles'] ?? []))),
            ],
            'workspace' => $organization === null ? null : [
                'public_id' => (string)($organization['public_id'] ?? ''),
                'title' => (string)($organization['title'] ?? ''),
            ],
            'permissions' => array_values(array_map('strval', $permissions)),
            'limits' => [
                'session_token_ttl_seconds' => max(60, $sessionTtlSeconds),
            ],
        ];
    }
}
