<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Permission\PermissionRepository;
use Api\Model\Permission\RolePermissionRepository;
use Api\Model\Role\RoleRepository;
use Api\System\Library\Logger\JsonLogger;

final class PermissionService
{
    public function __construct(
        private readonly PermissionRepository $permissions,
        private readonly RolePermissionRepository $rolePermissions,
        private readonly RoleRepository $roles,
        private readonly JsonLogger $logger
    )
    {
    }

    public function list(): array
    {
        $registry = [
            'user.view' => 'Просмотр пользователей',
            'user.manage' => 'Управление пользователями',
            'role.view' => 'Просмотр ролей',
            'role.manage' => 'Управление ролями',
            'project.manage' => 'Управление проектами',
            'task.manage' => 'Управление задачами',
            'team.manage' => 'Управление командами',
            'department.manage' => 'Управление департаментами',
            'company.manage' => 'Управление компаниями',
            'client.manage' => 'Управление клиентами',
            'counterparty.manage' => 'Управление контрагентами',
            'contact.manage' => 'Управление контактами',
            'logs.view' => 'Просмотр логов',
            'settings.manage' => 'Управление настройками',
            'approval.manage' => 'Управление согласованиями',
            'recycle_bin.manage' => 'Управление корзиной и восстановлением',
            'import.manage' => 'Управление импортом данных',
            'export.manage' => 'Управление экспортом данных',
            'api_client.view' => 'Просмотр API-клиентов и ключей',
            'api_client.manage' => 'Управление API-клиентами и ключами',
            'webhook.manage' => 'Управление webhooks и доставками',
            'feature_flag.manage' => 'Управление feature flags',
            'organization.manage' => 'Управление организациями/рабочими пространствами',
            'ai.use' => 'Использование AI-действий',
            'ai.admin' => 'Управление AI-настройками и провайдерами',
            'ai.use_sensitive_context' => 'Использование AI с чувствительным контекстом',
            'ai.manage_prompts' => 'Управление AI prompt templates',
            'ai.view_audit' => 'Просмотр AI usage/audit',
            'ai.view_cron_results' => 'Просмотр результатов AI cron jobs',
            'ai.manage_cron_jobs' => 'Управление AI cron jobs',
        ];

        $this->permissions->ensureRegistry($registry);

        return ['items' => $this->permissions->list()];
    }

    public function listByRole(string $rolePublicId): array
    {
        $role = $this->roles->findByPublicId($rolePublicId);
        if (!$role) {
            return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
        }

        $this->list(); // ensure registry
        return [
            'ok' => true,
            'role' => [
                'public_id' => (string)$role['public_id'],
                'code' => (string)$role['code'],
                'title' => (string)$role['title'],
            ],
            'permissions' => $this->rolePermissions->codesByRolePublicId($rolePublicId),
        ];
    }

    public function setByRole(string $rolePublicId, array $permissionCodes, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $role = $this->roles->findByPublicId($rolePublicId);
        if (!$role) {
            return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
        }

        $this->list(); // ensure registry
        $codes = array_values(array_unique(array_map(static fn($v): string => trim((string)$v), $permissionCodes)));
        $codes = array_values(array_filter($codes, static fn(string $v): bool => $v !== ''));

        $ok = $this->rolePermissions->replaceByRolePublicId($rolePublicId, $codes);
        if (!$ok) {
            return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
        }

        $this->logger->audit([
            'action' => 'role_permissions_set',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'role',
            'entity_public_id' => $rolePublicId,
            'permissions' => $codes,
        ]);

        return [
            'ok' => true,
            'role' => [
                'public_id' => (string)$role['public_id'],
                'code' => (string)$role['code'],
                'title' => (string)$role['title'],
            ],
            'permissions' => $this->rolePermissions->codesByRolePublicId($rolePublicId),
        ];
    }

    public function rolesWithPermissions(): array
    {
        $this->list();
        $roles = $this->roles->list([]);
        $items = [];

        foreach ($roles as $role) {
            $publicId = (string)($role['public_id'] ?? '');
            if ($publicId === '') {
                continue;
            }

            $items[] = [
                'public_id' => $publicId,
                'code' => (string)($role['code'] ?? ''),
                'title' => (string)($role['title'] ?? ''),
                'is_system' => (int)($role['is_system'] ?? 0) === 1,
                'permission_codes' => $this->rolePermissions->codesByRolePublicId($publicId),
            ];
        }

        return $items;
    }
}
