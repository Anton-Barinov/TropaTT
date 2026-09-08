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
        // An explicit allow-list on the actor wins over role-derived codes. API
        // keys carry their scoped permission_codes here (set by
        // ApiClientService::authenticateByKey) so a restricted key can never
        // widen itself back up to the bound user's full role set. Session
        // actors always carry their own current codes too (AuthService builds
        // the list per request), so this path is equivalent for them.
        $explicit = $user['permission_codes'] ?? null;
        $scopeRestricted = (bool)($user['scope_restricted'] ?? false);
        if (is_array($explicit)) {
            $codes = array_values(array_unique(array_filter(array_map(
                static fn(mixed $code): string => trim((string)$code),
                $explicit
            ), static fn(string $code): bool => $code !== '')));
            if (in_array('*', $codes, true)) {
                return ['*'];
            }
            if ($codes !== []) {
                return $codes;
            }
            // A scope-restricted key with an empty intersection is a deliberate
            // deny, never a signal to fall back to the bound user's role set.
            if ($scopeRestricted) {
                return [];
            }
        }

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

        foreach ($requiredPermissions as $required) {
            $requiredCode = (string)$required;

            if (!in_array($requiredCode, $actual, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Any-of check: grants access when the user holds AT LEAST ONE of the
     * required permissions. Mirrors crmWebApiCheckAnyPermission() used by the
     * web page-shell gate (web/index.php) so menu visibility stays consistent
     * with page access: an item must show whenever its page can load, and vice
     * versa. An empty list is always granted (public item).
     */
    public function hasAnyPermissions(array $user, array $requiredPermissions): bool
    {
        if ($requiredPermissions === []) {
            return true;
        }

        if ((bool)($user['is_root'] ?? false) === true) {
            return true;
        }

        $actual = $this->permissionsForUser($user);

        foreach ($requiredPermissions as $required) {
            if (in_array((string)$required, $actual, true)) {
                return true;
            }
        }

        return false;
    }
}
