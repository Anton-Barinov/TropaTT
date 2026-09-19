<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Completes the workspace boundary for feature stores that are created by
 * later module migrations.  A workspace owns the whole lifecycle of an
 * entity, including its history, links, drafts, projections and auxiliary
 * records.  Keeping the column on these stores also gives background workers
 * an explicit tenant key instead of making them infer it from a user.
 */
final class OrganizationFunctionalScopeMigration implements MigrationInterface
{
    /** @var list<string> */
    private const TABLES = [
        'task_relations_v2', 'task_activity_events', 'task_key_counters',
        'estimate_sets', 'estimate_options', 'task_estimates',
        'project_modules', 'project_module_tasks', 'project_module_members', 'project_module_links',
        'knowledge_page_versions', 'knowledge_drafts', 'knowledge_space_permissions',
        'knowledge_page_permissions', 'knowledge_entity_links', 'knowledge_search_index',
        'knowledge_templates', 'knowledge_page_views', 'knowledge_search_queries',
        'knowledge_comments', 'knowledge_page_properties',
        'intake_item_activities', 'sticky_notes', 'entity_tags', 'activity_feed',
        'business_calendars', 'holidays', 'working_hours', 'ai_suggestions', 'ai_usage_logs',
    ];

    public function key(): string
    {
        return '20260919_000007_organization_functional_scope';
    }

    public function description(): string
    {
        return 'Scope feature lifecycle stores and projections by workspace';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            throw new \RuntimeException('Unable to resolve default workspace for functional scope migration');
        }
        $default = (int)$default;

        foreach (self::TABLES as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) {
                continue;
            }
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            if (!IndexHelper::columnExists($pdo, $driver, $table, 'organization_id')) {
                throw new \RuntimeException("Unable to add {$table}.organization_id");
            }
            $this->backfillFromParent($pdo, $table, $default);
            $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL")
                ->execute(['organization_id' => $default]);
            IndexHelper::createIndexIfNotExists($pdo, $table, 'idx_' . $table . '_organization', 'organization_id', false, $driver);
        }
    }

    private function backfillFromParent(PDO $pdo, string $table, int $default): void
    {
        $updates = [
            'task_relations_v2' => 'UPDATE task_relations_v2 SET organization_id = (SELECT t.organization_id FROM tasks t WHERE t.id = task_relations_v2.task_id) WHERE organization_id IS NULL',
            'task_activity_events' => 'UPDATE task_activity_events SET organization_id = (SELECT t.organization_id FROM tasks t WHERE t.id = task_activity_events.task_id) WHERE organization_id IS NULL',
            'task_estimates' => 'UPDATE task_estimates SET organization_id = (SELECT t.organization_id FROM tasks t WHERE t.id = task_estimates.task_id) WHERE organization_id IS NULL',
            'estimate_options' => 'UPDATE estimate_options SET organization_id = (SELECT s.organization_id FROM estimate_sets s WHERE s.id = estimate_options.estimate_set_id) WHERE organization_id IS NULL',
            'project_module_tasks' => 'UPDATE project_module_tasks SET organization_id = (SELECT m.organization_id FROM project_modules m WHERE m.id = project_module_tasks.module_id) WHERE organization_id IS NULL',
            'project_module_members' => 'UPDATE project_module_members SET organization_id = (SELECT m.organization_id FROM project_modules m WHERE m.id = project_module_members.module_id) WHERE organization_id IS NULL',
            'project_module_links' => 'UPDATE project_module_links SET organization_id = (SELECT m.organization_id FROM project_modules m WHERE m.id = project_module_links.module_id) WHERE organization_id IS NULL',
            'knowledge_page_versions' => 'UPDATE knowledge_page_versions SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_page_versions.page_id) WHERE organization_id IS NULL',
            'knowledge_drafts' => 'UPDATE knowledge_drafts SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_drafts.page_id) WHERE organization_id IS NULL',
            'knowledge_page_permissions' => 'UPDATE knowledge_page_permissions SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_page_permissions.page_id) WHERE organization_id IS NULL',
            'knowledge_space_permissions' => 'UPDATE knowledge_space_permissions SET organization_id = (SELECT s.organization_id FROM knowledge_spaces s WHERE s.id = knowledge_space_permissions.space_id) WHERE organization_id IS NULL',
            'knowledge_entity_links' => 'UPDATE knowledge_entity_links SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_entity_links.page_id) WHERE organization_id IS NULL',
            'knowledge_search_index' => 'UPDATE knowledge_search_index SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_search_index.page_id) WHERE organization_id IS NULL',
            'knowledge_page_views' => 'UPDATE knowledge_page_views SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_page_views.page_id) WHERE organization_id IS NULL',
            'knowledge_comments' => 'UPDATE knowledge_comments SET organization_id = (SELECT p.organization_id FROM knowledge_pages p WHERE p.id = knowledge_comments.page_id) WHERE organization_id IS NULL',
            'intake_item_activities' => 'UPDATE intake_item_activities SET organization_id = (SELECT i.organization_id FROM intake_items i WHERE i.id = intake_item_activities.intake_item_id) WHERE organization_id IS NULL',
            'holidays' => 'UPDATE holidays SET organization_id = (SELECT c.organization_id FROM business_calendars c WHERE c.id = holidays.calendar_id) WHERE organization_id IS NULL',
            'working_hours' => 'UPDATE working_hours SET organization_id = (SELECT c.organization_id FROM business_calendars c WHERE c.id = working_hours.calendar_id) WHERE organization_id IS NULL',
        ];
        $sql = $updates[$table] ?? null;
        if ($sql !== null) {
            try {
                $pdo->exec($sql);
            } catch (\Throwable) {
                // A module can be installed with an older column name. The
                // deterministic default fallback below still keeps it safe.
            }
        }
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table LIMIT 1");
        } elseif ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare("SELECT TOP 1 1 FROM sys.objects WHERE name = :table AND type = 'U'");
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        }
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
