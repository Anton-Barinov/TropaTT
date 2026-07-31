<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleTableValidator
{
    /** @var array<int, string> */
    private array $coreTables = [
        'tasks', 'users', 'projects', 'clients', 'companies', 'contacts',
        'task_statuses', 'task_hierarchy', 'task_history', 'task_checklist_items',
        'comments', 'notifications', 'settings', 'module_migrations',
        'module_registry', 'module_errors', 'module_deprecations',
        'roles', 'permissions', 'role_permissions', 'user_roles',
        'statuses', 'priorities', 'tags', 'entity_tags', 'files',
        'calendar_events', 'reminders', 'work_logs', 'request_logs',
        'audit_logs', 'security_logs', 'activity_feed', 'feature_flags',
        'teams', 'departments', 'counterparties', 'subtasks', 'checklists',
        'checklist_items', 'task_templates', 'project_templates',
        'recurring_rules', 'recurring_instances', 'custom_fields',
        'custom_field_values', 'automation_rules', 'automation_runs',
        'sla_policies', 'approval_requests', 'approval_steps',
        'milestones', 'task_dependencies', 'saved_views', 'favorites',
        'mentions', 'reactions', 'subscriptions', 'recycle_bin',
        'import_jobs', 'export_jobs', 'webhook_subscriptions',
        'webhook_deliveries', 'idempotency_keys', 'sync_state',
        'business_calendars', 'holidays', 'working_hours',
        'organizations', 'invitations', 'password_reset_tokens',
        'two_factor_secrets', 'impersonation_audit',
        'user_sessions', 'api_clients', 'api_keys', 'install_state',
        'comment_drafts', 'notification_push_subscriptions',
    ];

    /**
     * Check if a module is attempting to modify a core table.
     */
    public function validateTableName(string $tableName, string $moduleName): bool
    {
        $normalized = strtolower($tableName);
        if (in_array($normalized, $this->coreTables, true)) {
            error_log("[ModuleTableValidator] Module '{$moduleName}' attempted to modify core table: {$tableName}");
            return false;
        }
        return true;
    }

    /**
     * Validate SQL migration for core table modifications.
     */
    public function validateMigration(string $sql, string $moduleName): bool
    {
        $upper = strtoupper($sql);

        foreach ($this->coreTables as $table) {
            $upperTable = strtoupper($table);
            if (
                str_contains($upper, "ALTER TABLE {$upperTable}")
                || str_contains($upper, "DROP TABLE {$upperTable}")
                || str_contains($upper, "INSERT INTO {$upperTable}")
                || str_contains($upper, "UPDATE {$upperTable}")
                || str_contains($upper, "DELETE FROM {$upperTable}")
                || str_contains($upper, "TRUNCATE {$upperTable}")
            ) {
                error_log("[ModuleTableValidator] Module '{$moduleName}' tried to modify core table '{$table}'");
                return false;
            }
        }

        return true;
    }
}
