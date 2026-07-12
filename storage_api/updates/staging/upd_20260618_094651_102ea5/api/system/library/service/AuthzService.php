<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Permission\RolePermissionRepository;

final class AuthzService
{
    public function __construct(private readonly RolePermissionRepository $rolePermissions)
    {
    }

    public function permissionsForUser(array $user): array
    {
        if ((bool)($user['is_root'] ?? false) === true) {
            return ['*'];
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        return $this->rolePermissions->permissionCodesByUserId($userId);
    }

    public function hasPermissions(array $user, array $requiredPermissions): bool
    {
        if ($requiredPermissions === []) {
            return true;
        }

        if ((bool)($user['is_root'] ?? false) === true) {
            return true;
        }

        $actual = $this->permissionsForUser($user);
        if ($actual === []) {
            $actual = [];
        }

        $roles = is_array($user['roles'] ?? null) ? (array)$user['roles'] : [];
        $isAdminRole = in_array('admin', $roles, true);

        foreach ($requiredPermissions as $required) {
            $requiredCode = (string)$required;
            if ($requiredCode === 'ai.admin' && $isAdminRole) {
                continue;
            }

            if (!in_array($requiredCode, $actual, true)) {
                error_log('[KB PERM] DENIED: user=' . json_encode(['id' => $user['id'] ?? 0, 'is_root' => $user['is_root'] ?? null, 'roles' => $user['roles'] ?? null]) . ' required=' . $requiredCode . ' actual=' . json_encode($actual));
                return false;
            }
        }

        return true;
    }
}
