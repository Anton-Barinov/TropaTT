<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Permission\PermissionRepository;
use Api\Model\Permission\RolePermissionRepository;
use Api\Model\Role\RoleRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;

final class PermissionService
{
    use TranslatableTrait;

    public function __construct(
        private readonly PermissionRepository $permissions,
        private readonly RolePermissionRepository $rolePermissions,
        private readonly RoleRepository $roles,
        private readonly JsonLogger $logger,
        LanguageManager $lang
    )
    {
        $this->lang = $lang;
    }

    public function list(): array
    {
        $registry = [
            'user.view' => $this->t('permission/messages.perm_user_view'),
            'user.manage' => $this->t('permission/messages.perm_user_manage'),
            'role.view' => $this->t('permission/messages.perm_role_view'),
            'role.manage' => $this->t('permission/messages.perm_role_manage'),
            'project.manage' => $this->t('permission/messages.perm_project_manage'),
            'task.manage' => $this->t('permission/messages.perm_task_manage'),
            'team.manage' => $this->t('permission/messages.perm_team_manage'),
            'department.manage' => $this->t('permission/messages.perm_department_manage'),
            'company.manage' => $this->t('permission/messages.perm_company_manage'),
            'client.manage' => $this->t('permission/messages.perm_client_manage'),
            'counterparty.manage' => $this->t('permission/messages.perm_counterparty_manage'),
            'contact.manage' => $this->t('permission/messages.perm_contact_manage'),
            'logs.view' => $this->t('permission/messages.perm_logs_view'),
            'settings.manage' => $this->t('permission/messages.perm_settings_manage'),
            'approval.manage' => $this->t('permission/messages.perm_approval_manage'),
            'recycle_bin.manage' => $this->t('permission/messages.perm_recycle_bin_manage'),
            'import.manage' => $this->t('permission/messages.perm_import_manage'),
            'export.manage' => $this->t('permission/messages.perm_export_manage'),
            'api_client.view' => $this->t('permission/messages.perm_api_client_view'),
            'api_client.manage' => $this->t('permission/messages.perm_api_client_manage'),
            'webhook.manage' => $this->t('permission/messages.perm_webhook_manage'),
            'feature_flag.manage' => $this->t('permission/messages.perm_feature_flag_manage'),
            'organization.manage' => $this->t('permission/messages.perm_organization_manage'),
            'ai.use' => $this->t('permission/messages.perm_ai_use'),
            'ai.admin' => $this->t('permission/messages.perm_ai_admin'),
            'ai.use_sensitive_context' => $this->t('permission/messages.perm_ai_use_sensitive'),
            'ai.manage_prompts' => $this->t('permission/messages.perm_ai_manage_prompts'),
            'ai.view_audit' => $this->t('permission/messages.perm_ai_view_audit'),
            'ai.view_cron_results' => $this->t('permission/messages.perm_ai_view_cron'),
            'ai.manage_cron_jobs' => $this->t('permission/messages.perm_ai_manage_cron'),
            'intake.view' => $this->t('permission/messages.perm_intake_view', 'Intake: view items'),
            'intake.create' => $this->t('permission/messages.perm_intake_create', 'Intake: create items'),
            'intake.manage' => $this->t('permission/messages.perm_intake_manage', 'Intake: manage items'),
            'intake.accept' => $this->t('permission/messages.perm_intake_accept', 'Intake: accept to task'),
            'intake.delete' => $this->t('permission/messages.perm_intake_delete', 'Intake: soft delete items'),
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
