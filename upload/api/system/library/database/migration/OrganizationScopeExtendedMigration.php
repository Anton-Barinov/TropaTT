<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Repair installs that ran an earlier body of the organization-scope migration.
 *
 * `OrganizationScopeMigration` (key 20260918_000001) was extended after it had
 * already been released: commit 94b30bce grew SCOPED_TABLES from ~11 tables to
 * ~51 without bumping the migration key. On any installation that had already
 * applied the earlier body, the ordered runner sees the key recorded and SKIPS
 * the migration, so the newly added tables never receive `organization_id`.
 * Queries on those tables then fail with SQLSTATE 42S22 ("Unknown column
 * organization_id"), which the API surfaces as "Database schema is outdated"
 * (the toast on the Projects and Counterparties pages).
 *
 * This migration re-runs the scope guarantee idempotently over the UNION of the
 * tables covered by the three scope migrations, so a partially-scoped install
 * converges regardless of which body it originally ran. Fresh installations
 * already carry the columns from the schema snapshot and have this migration
 * seeded as applied, so it is a no-op for them.
 */
final class OrganizationScopeExtendedMigration implements MigrationInterface
{
    /**
     * Union of OrganizationScopeMigration::SCOPED_TABLES,
     * OrganizationFunctionalScopeMigration::TABLES and
     * OrganizationDashboardWidgetScopeMigration::TABLES.
     *
     * @var string[]
     */
    private const SCOPED_TABLES = [
        // OrganizationScopeMigration (base + extension)
        'projects', 'tasks', 'task_relations', 'task_assignees', 'task_watchers',
        'task_status_history', 'subtasks', 'checklists', 'checklist_items',
        'comments', 'comment_drafts', 'clients', 'companies', 'contacts',
        'counterparties', 'teams', 'departments', 'chats', 'chat_participants',
        'chat_messages', 'chat_message_audit_logs', 'chat_read_markers', 'files',
        'notifications', 'reminders', 'calendar_events', 'work_logs',
        'task_templates', 'project_templates', 'recurring_rules',
        'recurring_instances', 'custom_fields', 'custom_field_values',
        'automation_rules', 'automation_runs', 'sla_policies',
        'approval_requests', 'approval_steps', 'milestones', 'task_dependencies',
        'saved_views', 'favorites', 'mentions', 'reactions', 'subscriptions',
        'recycle_bin', 'import_jobs', 'export_jobs', 'webhook_subscriptions',
        'webhook_deliveries', 'ai_jobs',
        // OrganizationFunctionalScopeMigration
        'task_relations_v2', 'task_activity_events', 'task_key_counters',
        'estimate_sets', 'estimate_options', 'task_estimates', 'project_modules',
        'project_module_tasks', 'project_module_members', 'project_module_links',
        'knowledge_page_versions', 'knowledge_drafts', 'knowledge_space_permissions',
        'knowledge_page_permissions', 'knowledge_entity_links', 'knowledge_search_index',
        'knowledge_templates', 'knowledge_page_views', 'knowledge_search_queries',
        'knowledge_comments', 'knowledge_page_properties', 'intake_item_activities',
        'sticky_notes', 'entity_tags', 'activity_feed', 'business_calendars',
        'holidays', 'working_hours', 'ai_suggestions', 'ai_usage_logs',
        // OrganizationDashboardWidgetScopeMigration
        'tags',
    ];

    public function key(): string
    {
        return '20260927_000001_organization_scope_extended';
    }

    public function description(): string
    {
        return 'Re-ensure organization_id on every workspace-scoped table (repairs installs that ran an earlier scope migration body)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'organizations')) {
            return;
        }
        $organizationId = $this->defaultOrganizationId($pdo);
        if ($organizationId === null) {
            return;
        }

        foreach (self::SCOPED_TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                continue;
            }
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            if (!IndexHelper::columnExists($pdo, $driver, $table, 'organization_id')) {
                continue;
            }

            $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL")
                ->execute(['organization_id' => $organizationId]);

            IndexHelper::createIndexIfNotExists(
                $pdo,
                $table,
                'idx_' . $table . '_organization',
                'organization_id',
                false,
                $driver
            );
        }
    }

    private function defaultOrganizationId(PDO $pdo): ?int
    {
        $id = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $id === false || $id === null ? null : (int)$id;
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
                $stmt->execute(['table' => $table]);
                return (bool)$stmt->fetchColumn();
            }
            $pdo->query("SELECT 1 FROM {$table} LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
