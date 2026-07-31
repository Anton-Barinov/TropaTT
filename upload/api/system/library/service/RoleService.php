<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Role\RoleRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class RoleService
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly JsonLogger $logger
    ) {
    }

    public function list(array $filters): array
    {
        return ['items' => $this->roles->list($filters)];
    }

    public function create(array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $code = trim((string)$input['code']);
        if ($this->roles->findByCode($code)) {
            return ['ok' => false, 'code' => 'ROLE_CODE_EXISTS'];
        }

        $publicId = Ulid::generate('rol');
        $now = gmdate('Y-m-d H:i:s');
        $this->roles->create([
            'public_id' => $publicId,
            'code' => $code,
            'title' => trim((string)$input['title']),
            'is_system' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'role_create',
            'actor_public_id' => $actor['public_id'],
            'entity_type' => 'role',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'role' => $this->roles->findByPublicId($publicId)];
    }

    public function update(string $publicId, array $input, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $role = $this->roles->findByPublicId($publicId);
        if (!$role) {
            return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
        }

        if ((int)$role['is_system'] === 1 && !$actor['is_root']) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('code', $input)) {
            $newCode = trim((string)$input['code']);
            if ($newCode !== (string)$role['code'] && $this->roles->findByCode($newCode)) {
                return ['ok' => false, 'code' => 'ROLE_CODE_EXISTS'];
            }
            $set['code'] = $newCode;
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->roles->updateByPublicId($publicId, $set);

        $this->logger->audit([
            'action' => 'role_update',
            'actor_public_id' => $actor['public_id'],
            'entity_type' => 'role',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'role' => $this->roles->findByPublicId($publicId)];
    }

    public function delete(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $role = $this->roles->findByPublicId($publicId);
        if (!$role) {
            return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
        }

        if ((int)$role['is_system'] === 1) {
            return ['ok' => false, 'code' => 'ROLE_SYSTEM_PROTECTED'];
        }

        if ($this->roles->roleHasUsers((int)$role['id'])) {
            return ['ok' => false, 'code' => 'ROLE_HAS_USERS'];
        }

        $ok = $this->roles->deleteByPublicId($publicId);
        return ['ok' => $ok, 'code' => $ok ? 'OK' : 'ROLE_NOT_FOUND'];
    }
}
