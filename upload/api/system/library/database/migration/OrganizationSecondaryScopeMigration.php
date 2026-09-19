<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Extends the first organization boundary to dependent business stores.
 *
 * This is a separate migration so installations that already applied the
 * initial scope migration receive the additional columns on upgrade.  Every
 * operation is optional and idempotent: old installations may not have a
 * module table yet, while a retry must never overwrite an existing scope.
 */
final class OrganizationSecondaryScopeMigration implements MigrationInterface
{
    /** @var list<string> */
    private const TABLES = [
        'task_relations', 'task_assignees', 'task_watchers', 'task_status_history',
        'subtasks', 'checklists', 'checklist_items', 'comments', 'comment_drafts',
        'departments', 'chat_participants', 'chat_messages', 'chat_message_audit_logs',
        'chat_read_markers', 'notifications', 'reminders', 'calendar_events',
        'work_logs', 'task_templates', 'project_templates', 'recurring_rules',
        'recurring_instances', 'custom_fields', 'custom_field_values',
        'automation_rules', 'automation_runs', 'sla_policies', 'approval_requests',
        'approval_steps', 'milestones', 'task_dependencies', 'saved_views',
        'favorites', 'mentions', 'reactions', 'subscriptions', 'recycle_bin',
        'webhook_subscriptions', 'webhook_deliveries',
    ];

    public function key(): string
    {
        return '20260918_000003_organization_secondary_scope';
    }

    public function description(): string
    {
        return 'Extend organization scope to dependent business stores';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            throw new \RuntimeException('Unable to resolve default organization for secondary scope migration');
        }
        $organizationId = (int)$default;

        foreach (self::TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                continue;
            }
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            if (!IndexHelper::columnExists($pdo, $driver, $table, 'organization_id')) {
                throw new \RuntimeException("Unable to add {$table}.organization_id");
            }
            // Existing installations have one legacy workspace.  Rows written
            // before this migration therefore belong to that workspace; any
            // explicit scope written during a resumed update is preserved.
            $stmt = $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL");
            $stmt->execute(['organization_id' => $organizationId]);
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

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        if ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare("SELECT TOP 1 1 FROM sys.objects WHERE name = :table AND type = 'U'");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
