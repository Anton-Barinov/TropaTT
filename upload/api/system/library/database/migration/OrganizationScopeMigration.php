<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Introduces the first physical tenant boundary for business data.
 *
 * The column deliberately remains nullable in this migration.  The context
 * layer will enforce a non-null value for new writes after it has a safe
 * legacy fallback.  Keeping DDL and request enforcement in separate releases
 * makes updates from older installations resumable and prevents a partially
 * upgraded application from losing access to existing rows.
 */
final class OrganizationScopeMigration implements MigrationInterface
{
    /** @var array<int,string> */
    private const SCOPED_TABLES = [
        'projects',
        'tasks',
        'task_relations',
        'task_assignees',
        'task_watchers',
        'task_status_history',
        'subtasks',
        'checklists',
        'checklist_items',
        'comments',
        'comment_drafts',
        'clients',
        'companies',
        'contacts',
        'counterparties',
        'teams',
        'departments',
        'chats',
        'chat_participants',
        'chat_messages',
        'chat_message_audit_logs',
        'chat_read_markers',
        'files',
        'notifications',
        'reminders',
        'calendar_events',
        'work_logs',
        'task_templates',
        'project_templates',
        'recurring_rules',
        'recurring_instances',
        'custom_fields',
        'custom_field_values',
        'automation_rules',
        'automation_runs',
        'sla_policies',
        'approval_requests',
        'approval_steps',
        'milestones',
        'task_dependencies',
        'saved_views',
        'favorites',
        'mentions',
        'reactions',
        'subscriptions',
        'recycle_bin',
        'import_jobs',
        'export_jobs',
        'webhook_subscriptions',
        'webhook_deliveries',
        'ai_jobs',
    ];

    public function key(): string
    {
        return '20260918_000001_organization_scope';
    }

    public function description(): string
    {
        return 'Add nullable organization scope and backfill legacy business data';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->ensureOrganization($pdo, $driver);
        $organizationId = $this->defaultOrganizationId($pdo);
        if ($organizationId === null) {
            throw new \RuntimeException('Unable to resolve default organization for legacy data');
        }

        foreach (self::SCOPED_TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                // Optional tables can be introduced by a later feature
                // migration.  The ordered migration runner will revisit the
                // table in that feature's own scope migration.
                continue;
            }

            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            if (!IndexHelper::columnExists($pdo, $driver, $table, 'organization_id')) {
                throw new \RuntimeException("Unable to add {$table}.organization_id");
            }

            $pdo->prepare(
                "UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL"
            )->execute(['organization_id' => $organizationId]);

            IndexHelper::createIndexIfNotExists(
                $pdo,
                $table,
                'idx_' . $table . '_organization',
                'organization_id',
                false,
                $driver
            );
        }

        $this->ensureMemberships($pdo, $driver, $organizationId);
    }

    private function ensureOrganization(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'organizations')) {
            throw new \RuntimeException('organizations table is required before organization scope migration');
        }

        // Reuse the oldest workspace where one already exists.  This avoids
        // creating a duplicate workspace during an update of an installation
        // that already has organizations, while still providing a deterministic
        // default for pre-Organizations databases.
        $existing = $pdo->query("SELECT id FROM organizations WHERE slug = 'default-workspace' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($existing === false || $existing === null) {
            $existing = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        }
        if ($existing !== false && $existing !== null) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO organizations (public_id, title, slug, created_at, updated_at) '
            . 'VALUES (:public_id, :title, :slug, :created_at, :updated_at)'
        );
        $stmt->execute([
            // A stable public id makes retries and restore rehearsals
            // deterministic. Existing installations still reuse their oldest
            // organization above, so this cannot duplicate a live workspace.
            'public_id' => 'org_default_workspace',
            'title' => 'Основное рабочее пространство',
            'slug' => 'default-workspace',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function defaultOrganizationId(PDO $pdo): ?int
    {
        $id = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $id === false || $id === null ? null : (int)$id;
    }

    private function ensureMemberships(PDO $pdo, string $driver, int $organizationId): void
    {
        if (!$this->tableExists($pdo, $driver, 'organization_memberships')
            || !$this->tableExists($pdo, $driver, 'users')) {
            return;
        }

        $users = $pdo->query('SELECT id, is_root FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($users === []) {
            return;
        }

        $exists = $pdo->prepare(
            'SELECT 1 FROM organization_memberships WHERE organization_id = :organization_id AND user_id = :user_id LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO organization_memberships '
            . '(public_id, organization_id, user_id, role_code, created_at) '
            . 'VALUES (:public_id, :organization_id, :user_id, :role_code, :created_at)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $ownerCheck = $pdo->prepare(
            "SELECT 1 FROM organization_memberships WHERE organization_id = :organization_id AND role_code = 'owner' LIMIT 1"
        );
        $ownerCheck->execute(['organization_id' => $organizationId]);
        $ownerAssigned = $ownerCheck->fetchColumn() !== false;
        foreach ($users as $user) {
            $userId = (int)$user['id'];
            $exists->execute(['organization_id' => $organizationId, 'user_id' => $userId]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }

            $isRoot = (int)($user['is_root'] ?? 0) === 1;
            $role = (!$ownerAssigned && $isRoot) || (!$ownerAssigned && $userId === (int)$users[0]['id'])
                ? 'owner'
                : 'member';
            if ($role === 'owner') {
                $ownerAssigned = true;
            }
            $insert->execute([
                'public_id' => 'orgm_' . strtoupper(bin2hex(random_bytes(8))),
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'role_code' => $role,
                'created_at' => $now,
            ]);
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
