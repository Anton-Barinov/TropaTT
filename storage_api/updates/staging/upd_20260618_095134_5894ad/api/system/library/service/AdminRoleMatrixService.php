<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AdminRoleMatrixService
{
    public function __construct(
        private readonly PermissionService $permissions
    ) {
    }

    public function getMatrix(): array
    {
        $registry = (array)($this->permissions->list()['items'] ?? []);
        $permissionsByCode = [];
        foreach ($registry as $permission) {
            $code = (string)($permission['code'] ?? '');
            if ($code !== '') {
                $permissionsByCode[] = [
                    'code' => $code,
                    'title' => (string)($permission['title'] ?? $code),
                ];
            }
        }

        $roles = $this->permissions->rolesWithPermissions();
        $items = [];
        foreach ($roles as $role) {
            $codes = (array)($role['permission_codes'] ?? []);
            $codeSet = array_fill_keys($codes, true);
            $matrix = [];
            foreach ($permissionsByCode as $permission) {
                $code = (string)$permission['code'];
                $matrix[$code] = isset($codeSet[$code]);
            }

            $items[] = [
                'role_public_id' => (string)$role['public_id'],
                'role_code' => (string)$role['code'],
                'role_title' => (string)$role['title'],
                'is_system' => (bool)($role['is_system'] ?? false),
                'permission_codes' => $codes,
                'matrix' => $matrix,
            ];
        }

        return [
            'permissions' => $permissionsByCode,
            'roles' => $items,
        ];
    }

    public function setMatrix(array $roles, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $updated = [];
        foreach ($roles as $idx => $row) {
            if (!is_array($row)) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'field' => 'roles.' . $idx];
            }

            $rolePublicId = trim((string)($row['role_public_id'] ?? ''));
            if ($rolePublicId === '') {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'field' => 'roles.' . $idx . '.role_public_id'];
            }

            $codes = $row['permission_codes'] ?? [];
            if (!is_array($codes)) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'field' => 'roles.' . $idx . '.permission_codes'];
            }

            $result = $this->permissions->setByRole($rolePublicId, $codes, $actor);
            if (!(bool)($result['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'code' => (string)($result['code'] ?? 'ROLE_PERMISSION_UPDATE_FAILED'),
                    'field' => 'roles.' . $idx,
                    'role_public_id' => $rolePublicId,
                ];
            }

            $updated[] = [
                'role' => (array)($result['role'] ?? []),
                'permissions' => (array)($result['permissions'] ?? []),
            ];
        }

        return [
            'ok' => true,
            'updated' => $updated,
            'matrix' => $this->getMatrix(),
        ];
    }
}
