<?php
/**
 * TropaTT CRM — API CRUD Test Suite
 * 
 * Tests all API entities with full CRUD lifecycle:
 * CREATE → READ → UPDATE → DELETE
 * 
 * Usage: php api/tests/api_crud_test.php
 * Requires: curl extension
 */

declare(strict_types=1);

class ApiCrudTest
{
    private string $baseUrl;
    private string $token;
    private int $pass = 0;
    private int $fail = 0;
    private array $failures = [];
    private array $created = [];

    public function __construct(string $baseUrl = 'https://demo.tropatt.com/api/index.php')
    {
        $this->baseUrl = $baseUrl;
    }

    public function run(): void
    {
        echo "=== TropaTT API CRUD Test Suite ===\n";
        echo "Base URL: {$this->baseUrl}\n";
        echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

        // Login
        if (!$this->login()) {
            echo "FATAL: Cannot authenticate\n";
            exit(1);
        }

        // Phase 1: Core Entities
        $this->section('PHASE 1: Core Entities');
        $this->testCrud('teams', 'api/v1/teams', ['title' => 'test_team'], ['title' => 'test_team_updated']);
        $this->testCrud('departments', 'api/v1/departments', ['title' => 'test_dept'], ['title' => 'test_dept_updated']);

        // Phase 2: Business Entities
        $this->section('PHASE 2: Business Entities');
        $this->testCrud('companies', 'api/v1/companies', ['title' => 'test_company'], ['title' => 'test_company_updated']);
        $this->testCrud('clients', 'api/v1/clients', ['title' => 'test_client'], ['title' => 'test_client_updated']);
        $this->testCrud('contacts', 'api/v1/contacts', ['full_name' => 'test_contact'], ['full_name' => 'test_contact_updated']);
        $this->testCrud('counterparties', 'api/v1/counterparties', ['title' => 'test_cp'], ['title' => 'test_cp_updated']);
        $this->testCrud('organizations', 'api/v1/organizations', ['title' => 'test_org'], ['title' => 'test_org_updated']);

        // Phase 3: Reference Data
        $this->section('PHASE 3: Reference Data');
        $this->testCrud('tags', 'api/v1/tags', ['title' => 'test_tag_' . uniqid(), 'color' => '#FF0000'], ['title' => 'test_tag_updated']);
        $this->testCrud('statuses', 'api/v1/statuses', ['title' => 'test_status', 'code' => 'test_st_' . time(), 'scope' => 'task', 'color' => '#00FF00'], ['title' => 'test_status_updated']);
        $this->testCrud('priorities', 'api/v1/priorities', ['title' => 'test_priority', 'code' => 'test_pr_' . time(), 'color' => '#0000FF', 'level' => 5], ['title' => 'test_priority_updated']);

        // Phase 4: Projects
        $this->section('PHASE 4: Projects');
        $prefix = 'Z' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $this->testCrud('projects', 'api/v1/projects', ['title' => 'test_project', 'task_key_prefix' => $prefix], ['title' => 'test_project_updated']);

        // Phase 5: Tasks + Related
        $this->section('PHASE 5: Tasks + Related');
        $this->testTasksAndRelated();

        // Phase 6: Milestones
        $this->section('PHASE 6: Milestones');
        $this->testMilestones();

        // Phase 7: Calendar
        $this->section('PHASE 7: Calendar');
        $this->testCrud('calendar_events', 'api/v1/calendar/events', [
            'title' => 'test_event',
            'starts_at' => date('Y-m-d\T10:00:00', strtotime('+1 day')),
            'ends_at' => date('Y-m-d\T11:00:00', strtotime('+1 day')),
        ], ['title' => 'test_event_updated']);

        // Phase 8: Reminders
        $this->section('PHASE 8: Reminders');
        $this->testCrud('reminders', 'api/v1/reminders', ['title' => 'test_reminder', 'remind_at' => date('Y-m-d\T12:00:00', strtotime('+30 days'))], ['title' => 'test_reminder_updated']);

        // Phase 9: Sticky Notes
        $this->section('PHASE 9: Sticky Notes');
        $this->testCrud('sticky_notes', 'api/v1/sticky-notes', ['title' => 'test_sticky', 'body' => 'test content'], ['title' => 'test_sticky_updated']);

        // Phase 10: Templates
        $this->section('PHASE 10: Templates');
        $this->testCrud('templates', 'api/v1/template/tasks', ['title' => 'test_template'], ['title' => 'test_template_updated']);

        // Phase 11: Ideas
        $this->section('PHASE 11: Ideas');
        $this->testCrud('ideas', 'api/v1/ideas', ['title' => 'test_idea', 'description' => 'test desc'], ['title' => 'test_idea_updated']);

        // Phase 12: Intake Items
        $this->section('PHASE 12: Intake Items');
        $this->testCrud('intake_items', 'api/v1/intake-items', ['title' => 'test_intake', 'description' => 'test desc', 'source' => 'api_test'], ['title' => 'test_intake_updated']);

        // Phase 13: Custom Fields
        $this->section('PHASE 13: Custom Fields');
        $this->testCrud('custom_fields', 'api/v1/custom-fields', ['title' => 'test_cf', 'scope' => 'task', 'code' => 'test_cf_' . time(), 'type' => 'text'], ['title' => 'test_cf_updated']);

        // Phase 14: Workflow Rules
        $this->section('PHASE 14: Workflow Rules');
        $this->testCrud('workflow_rules', 'api/v1/workflow/rules', ['title' => 'test_wf', 'trigger_code' => 'task_status_changed', 'action_code' => 'send_notification', 'is_enabled' => true], ['title' => 'test_wf_updated']);

        // Phase 15: SLA Policies
        $this->section('PHASE 15: SLA Policies');
        $this->testCrud('sla_policies', 'api/v1/sla/policies', ['title' => 'test_sla', 'response_minutes' => 240, 'resolve_minutes' => 1440], ['title' => 'test_sla_updated']);

        // Phase 16: Webhooks
        $this->section('PHASE 16: Webhooks');
        $this->testCrud('webhooks', 'api/v1/webhooks', ['title' => 'test_webhook', 'endpoint' => 'https://example.com/webhook', 'events' => ['task.created']], ['title' => 'test_webhook_updated']);

        // Phase 17: Views
        $this->section('PHASE 17: Views');
        $this->testCrud('views', 'api/v1/views', ['title' => 'test_view', 'filters' => []], ['title' => 'test_view_updated']);

        // Phase 18: Knowledge
        $this->section('PHASE 18: Knowledge');
        $this->testKnowledge();

        // Phase 19: List Endpoints
        $this->section('PHASE 19: List Endpoints');
        $this->testListEndpoints();

        // Summary
        $this->summary();
    }

    private function login(): bool
    {
        $r = $this->api('POST', 'api/v1/auth/login', [
            'login' => 'admin',
            'password' => 'adminadmin',
        ], false);
        $this->token = $r['data']['access_token'] ?? '';
        return !empty($this->token);
    }

    private function testCrud(string $name, string $url, array $create, array $update): void
    {
        // CREATE
        $r = $this->api('POST', $url, $create);
        $pid = $this->extractPid($r);
        if ($pid) {
            $this->pass("CREATE $name");
        } else {
            $this->fail("CREATE $name", $r['code'] ?? 'unknown');
            return;
        }

        // READ
        $r2 = $this->api('GET', "$url/$pid");
        if ($r2['success'] ?? false) {
            $this->pass("READ $name");
        } else {
            $this->fail("READ $name", $r2['code'] ?? 'unknown');
        }

        // UPDATE
        $r3 = $this->api('PATCH', "$url/$pid", $update);
        if ($r3['success'] ?? false) {
            $this->pass("UPDATE $name");
        } else {
            $this->fail("UPDATE $name", $r3['code'] ?? 'unknown');
        }

        // DELETE
        $r4 = $this->api('DELETE', "$url/$pid");
        if ($r4['success'] ?? false) {
            $this->pass("DELETE $name");
        } else {
            $this->fail("DELETE $name", $r4['code'] ?? 'unknown');
        }
    }

    private function testTasksAndRelated(): void
    {
        // Get default status
        $r = $this->api('GET', 'api/v1/statuses');
        $statusId = '';
        foreach (($r['data']['items'] ?? []) as $item) {
            if (($item['scope'] ?? '') === 'task') {
                $statusId = $item['public_id'];
                break;
            }
        }
        if (!$statusId) {
            $this->fail('TASK SETUP', 'no_task_status');
            return;
        }

        // Create task
        $r = $this->api('POST', 'api/v1/tasks', [
            'title' => 'test_task',
            'status_public_id' => $statusId,
        ]);
        $taskId = $this->extractPid($r);
        if (!$taskId) {
            $this->fail('CREATE task', $r['code'] ?? 'unknown');
            return;
        }
        $this->pass('CREATE task');

        // Read
        $r = $this->api('GET', "api/v1/tasks/$taskId");
        $this->assert($r['success'] ?? false, 'READ task');

        // Update
        $r = $this->api('PATCH', "api/v1/tasks/$taskId", ['title' => 'test_task_updated']);
        $this->assert($r['success'] ?? false, 'UPDATE task');

        // Move
        $r = $this->api('POST', "api/v1/tasks/$taskId/move", ['to_status_public_id' => $statusId]);
        $this->assert($r['success'] ?? false, 'MOVE task');

        // Subtask
        $r = $this->api('POST', "api/v1/tasks/$taskId/subtasks", ['title' => 'test_subtask']);
        $subId = $this->extractPid($r);
        if ($subId) {
            $this->pass('CREATE subtask');
            $r2 = $this->api('GET', "api/v1/subtasks/$subId");
            $this->assert($r2['success'] ?? false, 'READ subtask');
            $r3 = $this->api('PATCH', "api/v1/subtasks/$subId", ['title' => 'test_subtask_updated']);
            $this->assert($r3['success'] ?? false, 'UPDATE subtask');
            $r4 = $this->api('DELETE', "api/v1/subtasks/$subId");
            $this->assert($r4['success'] ?? false, 'DELETE subtask');
        } else {
            $this->fail('CREATE subtask', $r['code'] ?? 'unknown');
        }

        // Comment
        $r = $this->api('POST', "api/v1/tasks/$taskId/comments", ['body' => 'test comment']);
        $commentId = $this->extractPid($r);
        if ($commentId) {
            $this->pass('CREATE comment');
            $r2 = $this->api('PATCH', "api/v1/comments/$commentId", ['body' => 'test comment updated']);
            $this->assert($r2['success'] ?? false, 'UPDATE comment');
            $r3 = $this->api('DELETE', "api/v1/comments/$commentId");
            $this->assert($r3['success'] ?? false, 'DELETE comment');
        } else {
            $this->fail('CREATE comment', $r['code'] ?? 'unknown');
        }

        // Checklist + items
        $r = $this->api('POST', "api/v1/tasks/$taskId/checklists", ['title' => 'test_checklist']);
        $clId = $this->extractPid($r);
        if ($clId) {
            $this->pass('CREATE checklist');
            $r2 = $this->api('GET', "api/v1/checklists/$clId");
            $this->assert($r2['success'] ?? false, 'READ checklist');
            $r3 = $this->api('PATCH', "api/v1/checklists/$clId", ['title' => 'test_checklist_updated']);
            $this->assert($r3['success'] ?? false, 'UPDATE checklist');

            // Checklist item
            $r4 = $this->api('POST', "api/v1/checklists/$clId/items", ['title' => 'test_item']);
            $itemId = $this->extractPid($r4);
            if ($itemId) {
                $this->pass('CREATE checklist item');
                $r5 = $this->api('GET', "api/v1/checklist-items/$itemId");
                $this->assert($r5['success'] ?? false, 'READ checklist item');
                $r6 = $this->api('PATCH', "api/v1/checklist-items/$itemId", ['title' => 'test_item_updated']);
                $this->assert($r6['success'] ?? false, 'UPDATE checklist item');
                $r7 = $this->api('DELETE', "api/v1/checklist-items/$itemId");
                $this->assert($r7['success'] ?? false, 'DELETE checklist item');
            } else {
                $this->fail('CREATE checklist item', $r4['code'] ?? 'unknown');
            }

            $r8 = $this->api('DELETE', "api/v1/checklists/$clId");
            $this->assert($r8['success'] ?? false, 'DELETE checklist');
        } else {
            $this->fail('CREATE checklist', $r['code'] ?? 'unknown');
        }

        // Worklog
        $r = $this->api('POST', 'api/v1/worklogs', [
            'task_public_id' => $taskId,
            'minutes_spent' => 30,
            'description' => 'test worklog',
            'logged_at' => date('Y-m-d'),
        ]);
        $wlId = $this->extractPid($r);
        if ($wlId) {
            $this->pass('CREATE worklog');
            $r2 = $this->api('GET', "api/v1/worklogs/$wlId");
            $this->assert($r2['success'] ?? false, 'READ worklog');
            $r3 = $this->api('PATCH', "api/v1/worklogs/$wlId", ['minutes_spent' => 60]);
            $this->assert($r3['success'] ?? false, 'UPDATE worklog');
            $r4 = $this->api('DELETE', "api/v1/worklogs/$wlId");
            $this->assert($r4['success'] ?? false, 'DELETE worklog');
        } else {
            $this->fail('CREATE worklog', $r['code'] ?? 'unknown');
        }

        // Task relations
        $r = $this->api('POST', 'api/v1/tasks', ['title' => 'test_task2', 'status_public_id' => $statusId]);
        $taskId2 = $this->extractPid($r);
        if ($taskId2) {
            $r2 = $this->api('POST', "api/v1/tasks/$taskId/relations", [
                'target_task_public_id' => $taskId2,
                'relation_type' => 'blocks',
            ]);
            $relId = $this->extractPid($r2);
            if ($relId) {
                $this->pass('CREATE relation');
                $r3 = $this->api('DELETE', "api/v1/task-relations/$relId");
                $this->assert($r3['success'] ?? false, 'DELETE relation');
            } else {
                $this->fail('CREATE relation', $r2['code'] ?? 'unknown');
            }
            $this->api('DELETE', "api/v1/tasks/$taskId2");
        }

        // Tag binding
        $r = $this->api('POST', 'api/v1/tags', ['title' => 'test_task_tag', 'color' => '#FF0000']);
        $tagId = $this->extractPid($r);
        if ($tagId) {
            $r2 = $this->api('POST', "api/v1/tasks/$taskId/tags/$tagId");
            $this->assert($r2['success'] ?? false, 'ATTACH tag');
            $r3 = $this->api('DELETE', "api/v1/tasks/$taskId/tags/$tagId");
            $this->assert($r3['success'] ?? false, 'DETACH tag');
            $this->api('DELETE', "api/v1/tags/$tagId");
        }

        // Delete task
        $r = $this->api('DELETE', "api/v1/tasks/$taskId");
        $this->assert($r['success'] ?? false, 'DELETE task');
    }

    private function testMilestones(): void
    {
        // Get a project
        $r = $this->api('GET', 'api/v1/projects');
        $projectId = '';
        foreach (($r['data']['items'] ?? []) as $item) {
            $projectId = $item['public_id'] ?? '';
            if ($projectId) break;
        }
        if (!$projectId) {
            $this->fail('MILESTONES SETUP', 'no_project');
            return;
        }

        $r = $this->api('POST', 'api/v1/milestones', [
            'title' => 'test_milestone',
            'project_public_id' => $projectId,
            'due_at' => date('Y-m-d', strtotime('+30 days')),
        ]);
        $msId = $this->extractPid($r);
        if ($msId) {
            $this->pass('CREATE milestone');
            $r2 = $this->api('GET', "api/v1/milestones/$msId");
            $this->assert($r2['success'] ?? false, 'READ milestone');
            $r3 = $this->api('PATCH', "api/v1/milestones/$msId", ['title' => 'test_milestone_updated']);
            $this->assert($r3['success'] ?? false, 'UPDATE milestone');
            $r4 = $this->api('DELETE', "api/v1/milestones/$msId");
            $this->assert($r4['success'] ?? false, 'DELETE milestone');
        } else {
            $this->fail('CREATE milestone', $r['code'] ?? 'unknown');
        }
    }

    private function testKnowledge(): void
    {
        // Create space
        $r = $this->api('POST', 'api/v1/knowledge/spaces', ['title' => 'test_space']);
        $spaceId = $this->extractPid($r);
        if (!$spaceId) {
            $this->fail('CREATE knowledge space', $r['code'] ?? 'unknown');
            return;
        }
        $this->pass('CREATE knowledge space');

        // Read space
        $r2 = $this->api('GET', "api/v1/knowledge/spaces/$spaceId");
        $this->assert($r2['success'] ?? false, 'READ knowledge space');

        // Update space
        $r3 = $this->api('PATCH', "api/v1/knowledge/spaces/$spaceId", ['title' => 'test_space_updated']);
        $this->assert($r3['success'] ?? false, 'UPDATE knowledge space');

        // Create page
        $r4 = $this->api('POST', 'api/v1/knowledge/pages', [
            'title' => 'test_page',
            'space_public_id' => $spaceId,
            'content' => 'test content',
        ]);
        $pageId = $this->extractPid($r4) ?? ($r4['data']['public_id'] ?? '');
        if ($pageId) {
            $this->pass('CREATE knowledge page');

            // Read page
            $r5 = $this->api('GET', "api/v1/knowledge/pages/$pageId");
            $this->assert($r5['success'] ?? false, 'READ knowledge page');

            // Update page
            $r6 = $this->api('PATCH', "api/v1/knowledge/pages/$pageId", ['title' => 'test_page_updated']);
            $this->assert($r6['success'] ?? false, 'UPDATE knowledge page');

            // Delete page
            $r7 = $this->api('DELETE', "api/v1/knowledge/pages/$pageId");
            $this->assert($r7['success'] ?? false, 'DELETE knowledge page');
        } else {
            $this->fail('CREATE knowledge page', $r4['code'] ?? 'unknown');
        }

        // Delete space
        $r8 = $this->api('DELETE', "api/v1/knowledge/spaces/$spaceId");
        $this->assert($r8['success'] ?? false, 'DELETE knowledge space');
    }

    private function testListEndpoints(): void
    {
        $endpoints = [
            'auth/me', 'auth/menu', 'auth/menu/preferences', 'version',
            'health/status', 'health/deep',
            'users', 'roles', 'permissions',
            'teams', 'departments',
            'companies', 'clients', 'contacts', 'counterparties', 'organizations',
            'statuses', 'priorities', 'tags',
            'projects', 'tasks', 'tasks/board',
            'notifications', 'notifications/counters',
            'calendar/events', 'calendar/my-day', 'calendar/my-week', 'calendar/my-month',
            'calendar/business',
            'dashboard/summary',
            'analytics/summary', 'analytics/projects', 'analytics/users',
            'activity/feed', 'audit/list', 'logs/request',
            'settings', 'feature-flags',
            'profile/me', 'profile/preferences',
            'security/sessions', 'security/2fa/status', 'security/invitations',
            'ops/system', 'ops/metrics', 'admin/cache', 'admin/widgets/summary', 'admin/widgets/system',
            'core/version', 'core/updates/status', 'core/updates/history',
            'recurring', 'recycle-bin',
            'api-clients', 'modules',
            'import/jobs', 'export/jobs',
            'pages/my-day', 'pages/kanban', 'pages/my-week',
            'notifications/push-subscriptions',
            'dependencies', 'mentions', 'reactions', 'favorites', 'subscriptions',
            'approvals', 'retention/metadata', 'docs/openapi',
            'worklogs/matrix', 'worklogs/earnings',
            'knowledge/overview', 'knowledge/recent', 'knowledge/popular',
            'knowledge/pages', 'knowledge/spaces', 'knowledge/spaces-tree',
            'knowledge/favorites', 'knowledge/outdated', 'knowledge/suggest',
            'knowledge/analytics', 'knowledge/templates',
            'ai/availability', 'ai/settings', 'ai/action-types', 'ai/suggestions',
            'webhooks', 'webhooks/deliveries',
        ];

        foreach ($endpoints as $ep) {
            $r = $this->api('GET', "api/v1/$ep");
            $this->assert($r['success'] ?? false, "LIST $ep");
        }
    }

    private function api(string $method, string $url, ?array $data = null, bool $auth = true): array
    {
        $fullUrl = $this->baseUrl . '?route=' . $url;
        $headers = ['Content-Type: application/json'];
        if ($auth && !empty($this->token)) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }

        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?: [];
    }

    private function extractPid(array $response): ?string
    {
        if (!($response['success'] ?? false)) {
            return null;
        }
        $data = $response['data'] ?? [];
        if (is_array($data)) {
            foreach ($data as $v) {
                if (is_array($v) && isset($v['public_id'])) {
                    return $v['public_id'];
                }
            }
            if (isset($data['public_id'])) {
                return $data['public_id'];
            }
        }
        return null;
    }

    private function section(string $title): void
    {
        echo "\n--- $title ---\n";
    }

    private function pass(string $label): void
    {
        $this->pass++;
        echo "  ✓ $label\n";
    }

    private function fail(string $label, string $reason = ''): void
    {
        $this->fail++;
        $msg = "  ✗ $label";
        if ($reason) $msg .= " [$reason]";
        echo "$msg\n";
        $this->failures[] = $label . ($reason ? " [$reason]" : '');
    }

    private function assert(bool $condition, string $label): void
    {
        if ($condition) {
            $this->pass($label);
        } else {
            $this->fail($label);
        }
    }

    private function summary(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "SUMMARY\n";
        echo str_repeat('=', 60) . "\n";
        echo "PASS: {$this->pass}\n";
        echo "FAIL: {$this->fail}\n";
        echo "TOTAL: " . ($this->pass + $this->fail) . "\n";
        echo "Finished: " . date('Y-m-d H:i:s') . "\n";

        if ($this->failures) {
            echo "\nFailed tests:\n";
            foreach ($this->failures as $f) {
                echo "  - $f\n";
            }
        }

        echo "\n" . ($this->fail === 0 ? 'ALL TESTS PASSED!' : 'SOME TESTS FAILED') . "\n";
    }
}

// Run
$test = new ApiCrudTest();
$test->run();
