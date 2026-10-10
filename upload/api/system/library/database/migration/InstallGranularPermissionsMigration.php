<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Migration: seed the granular install permissions introduced after the
 * 2026-10 security audit.
 *
 * Background: installing a language pack and installing a module were both
 * gated only by `settings.manage`, a broad permission that also unlocks
 * unrelated settings screens. The audit's critical chain (language pack ->
 * AST validator bypass -> X-Locale inclusion) only needed that one code, so
 * installation is being split into its own grantable permissions:
 *
 *   - language_pack.install  POST /api/v1/admin/languages/install
 *   - module.install         POST /api/v1/modules/{name}/install,
 *                            /api/v1/modules/install-from-url,
 *                            /api/v1/modules/install-from-file,
 *                            /api/v1/marketplace/install
 *
 * Backwards compatibility: any role that already holds settings.manage today
 * effectively had both capabilities through the old routes, so it receives
 * both new codes. An administrator can then narrow them per role in the role
 * matrix without any functional regression. Root (is_root) bypasses
 * permission checks entirely and is unaffected.
 *
 * Idempotent: re-running only inserts missing permission rows and missing
 * role-permission links.
 */
final class InstallGranularPermissionsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261010_000001_install_granular_permissions';
    }

    public function description(): string
    {
        return 'Seed language_pack.install and module.install permissions; grant to settings.manage roles';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $codes = [
            'language_pack.install' => 'Install language packs from admin',
            'module.install'        => 'Install modules (marketplace, URL, file, register)',
        ];

        $select = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
        $insert = $pdo->prepare(
            'INSERT INTO permissions (public_id, code, title, created_at)
             VALUES (:public_id, :code, :title, :created_at)'
        );

        $permIds = [];
        foreach ($codes as $code => $title) {
            $select->execute([':code' => $code]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $permIds[$code] = (int)$row['id'];
                continue;
            }
            $publicId = 'prm_' . str_replace('.', '_', $code);
            $insert->execute([
                ':public_id' => $publicId,
                ':code' => $code,
                ':title' => $title,
                ':created_at' => $now,
            ]);
            $select->execute([':code' => $code]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $permIds[$code] = (int)$row['id'];
            }
        }

        if ($permIds === []) {
            return;
        }

        // Every role that can manage settings could install before this split,
        // so it keeps both capabilities until an admin narrows it.
        $settingsRoleId = $this->permissionIdByCode($pdo, 'settings.manage');
        if ($settingsRoleId === null) {
            return;
        }

        $roleStmt = $pdo->query(
            'SELECT rp.role_id FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = \'settings.manage\''
        );
        $roleIds = array_map('intval', $roleStmt ? $roleStmt->fetchAll(PDO::FETCH_COLUMN) : []);

        $check = $pdo->prepare(
            'SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id'
        );
        $link = $pdo->prepare(
            'INSERT INTO role_permissions (role_id, permission_id, created_at)
             VALUES (:role_id, :permission_id, :created_at)'
        );

        foreach (array_unique($roleIds) as $roleId) {
            foreach ($permIds as $permId) {
                $check->execute([':role_id' => $roleId, ':permission_id' => $permId]);
                if ($check->fetchColumn()) {
                    continue;
                }
                $link->execute([
                    ':role_id' => $roleId,
                    ':permission_id' => $permId,
                    ':created_at' => $now,
                ]);
            }
        }
    }

    private function permissionIdByCode(PDO $pdo, string $code): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }
}
