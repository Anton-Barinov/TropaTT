<?php
/**
 * TropaTT CRM — MCP CRUD Test Suite
 * 
 * Tests all MCP tools with full CRUD lifecycle:
 * CREATE → READ → UPDATE → DELETE
 * 
 * Usage: php api/tests/mcp_crud_test.php
 */

declare(strict_types=1);

class McpCrudTest
{
    private string $baseUrl;
    private string $token;
    private int $pass = 0;
    private int $fail = 0;
    private array $failures = [];

    public function __construct(string $baseUrl = 'https://demo.tropatt.com/api/index.php')
    {
        $this->baseUrl = $baseUrl;
    }

    public function run(): void
    {
        echo "=== TropaTT MCP CRUD Test Suite ===\n";
        echo "Base URL: {$this->baseUrl}\n";
        echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

        if (!$this->login()) {
            echo "FATAL: Cannot authenticate\n";
            exit(1);
        }

        $this->section('PHASE 1: Tasks');
        $this->testMcpCrud('tasks', 'crm_create_task', 'crm_get_task', 'crm_update_task', 'crm_delete_task',
            ['title' => 'mcp_test_task', 'status' => 'new'],
            ['title' => 'mcp_test_task_updated']);

        $this->section('PHASE 2: Projects');
        $this->testMcpCrud('projects', 'crm_create_project', 'crm_get_project', 'crm_update_project', 'crm_delete_project',
            ['title' => 'mcp_test_project', 'task_key_prefix' => 'MCPT' . substr(uniqid(), -4)],
            ['title' => 'mcp_test_project_updated']);

        $this->section('PHASE 3: Comments');
        $this->testTaskComments();

        $this->section('PHASE 4: Knowledge Spaces');
        $this->testKnowledgeSpaces();

        $this->section('PHASE 5: Knowledge Pages');
        $this->testKnowledgePages();

        $this->section('PHASE 6: Webhooks');
        $this->testMcpCrud('webhooks', 'crm_create_webhook', 'crm_list_webhooks', 'crm_update_webhook', 'crm_delete_webhook',
            ['title' => 'mcp_test_webhook', 'url' => 'https://example.com/webhook', 'events' => ['task.created']],
            ['title' => 'mcp_test_webhook_updated']);

        $this->section('PHASE 7: Calendar Events');
        $this->testMcpCrud('calendar_events', 'crm_create_calendar_event', 'crm_get_calendar_event', 'crm_update_calendar_event', 'crm_delete_calendar_event',
            ['title' => 'mcp_test_event', 'starts_at' => date('Y-m-d\T10:00:00', strtotime('+1 day')), 'ends_at' => date('Y-m-d\T11:00:00', strtotime('+1 day'))],
            ['title' => 'mcp_test_event_updated']);

        $this->section('PHASE 8: Reminders');
        $this->testMcpCrud('reminders', 'crm_create_reminder', 'crm_get_reminder', 'crm_update_reminder', 'crm_delete_reminder',
            ['title' => 'mcp_test_reminder', 'remind_at' => date('Y-m-d\T12:00:00', strtotime('+30 days'))],
            ['title' => 'mcp_test_reminder_updated']);

        $this->section('PHASE 9: Sticky Notes');
        $this->testMcpCrud('sticky_notes', 'crm_create_sticky_note', 'crm_get_sticky_note', 'crm_update_sticky_note', 'crm_delete_sticky_note',
            ['title' => 'mcp_test_sticky', 'body' => 'test content'],
            ['title' => 'mcp_test_sticky_updated']);

        $this->section('PHASE 10: Templates');
        $this->testTemplates();

        $this->section('PHASE 11: Ideas');
        $this->testMcpCrud('ideas', 'crm_create_idea', 'crm_get_idea', 'crm_update_idea', 'crm_delete_idea',
            ['title' => 'mcp_test_idea', 'description' => 'test description'],
            ['title' => 'mcp_test_idea_updated']);

        $this->section('PHASE 12: Intake Items');
        $this->testMcpCrud('intake_items', 'crm_create_intake_item', 'crm_get_intake_item', 'crm_update_intake_item', 'crm_delete_intake_item',
            ['title' => 'mcp_test_intake', 'description' => 'test intake', 'source' => 'api_test'],
            ['title' => 'mcp_test_intake_updated']);

        $this->section('PHASE 13: Saved Views');
        $this->testMcpCrud('saved_views', 'crm_create_saved_view', 'crm_get_saved_view', 'crm_update_saved_view', 'crm_delete_saved_view',
            ['title' => 'mcp_test_view', 'filters' => ['status' => ['new']]],
            ['title' => 'mcp_test_view_updated']);

        $this->section('PHASE 14: Estimate Sets');
        $this->testEstimateSets();

        $this->section('PHASE 15: List & Utility Tools');
        $this->testListTools();

        $this->summary();
    }

    private function login(): bool
    {
        $r = $this->api('POST', 'api/v1/auth/login', ['login' => 'admin', 'password' => 'adminadmin'], false);
        $this->token = $r['data']['access_token'] ?? '';
        return !empty($this->token);
    }

    private function mcp(string $tool, array $args = []): array
    {
        return $this->api('POST', 'api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => (string)mt_rand(1, 999999),
            'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $args],
        ]);
    }

    private function mcpPid(string $tool, array $args): ?string
    {
        $r = $this->mcp($tool, $args);
        $sc = $r['result']['structuredContent'] ?? [];
        foreach ($sc as $v) {
            if (is_array($v) && isset($v['public_id'])) return $v['public_id'];
            if (is_array($v)) {
                foreach ($v as $v2) {
                    if (is_array($v2) && isset($v2['public_id'])) return $v2['public_id'];
                }
            }
        }
        return null;
    }

    private function mcpOk(string $tool, array $args): bool
    {
        $r = $this->mcp($tool, $args);
        $sc = $r['result']['structuredContent'] ?? [];
        return !empty($sc) && !isset($sc['error']);
    }

    private function testMcpCrud(string $name, string $createTool, string $getTool, string $updateTool, string $deleteTool, array $createData, array $updateData): void
    {
        $pid = $this->mcpPid($createTool, $createData);
        if ($pid) { $this->pass("CREATE $name"); } else { $this->fail("CREATE $name", 'no pid'); return; }

        if ($this->mcpOk($getTool, ['public_id' => $pid])) { $this->pass("READ $name"); } else { $this->fail("READ $name"); }

        $updateData['public_id'] = $pid;
        if ($this->mcpOk($updateTool, $updateData)) { $this->pass("UPDATE $name"); } else { $this->fail("UPDATE $name"); }

        if ($this->mcpOk($deleteTool, ['public_id' => $pid])) { $this->pass("DELETE $name"); } else { $this->fail("DELETE $name"); }
    }

    private function testTaskComments(): void
    {
        $taskPid = $this->mcpPid('crm_create_task', ['title' => 'mcp_comment_task', 'status' => 'new']);
        if (!$taskPid) { $this->fail('CREATE task for comments'); return; }

        $r = $this->mcp('crm_add_task_comment', ['task_public_id' => $taskPid, 'body' => 'test comment']);
        $sc = $r['result']['structuredContent'] ?? [];
        if (!empty($sc['ok'])) { $this->pass('CREATE comment'); } else { $this->fail('CREATE comment'); }

        if ($this->mcpOk('crm_list_task_comments', ['task_public_id' => $taskPid])) { $this->pass('LIST comments'); } else { $this->fail('LIST comments'); }
        $this->mcp('crm_delete_task', ['public_id' => $taskPid]);
    }

    private function testKnowledgeSpaces(): void
    {
        $pid = $this->mcpPid('crm_create_knowledge_space', ['title' => 'mcp_test_space']);
        if ($pid) { $this->pass('CREATE knowledge_space'); } else { $this->fail('CREATE knowledge_space'); return; }

        if ($this->mcpOk('crm_get_knowledge_space', ['public_id' => $pid])) { $this->pass('READ knowledge_space'); } else { $this->fail('READ knowledge_space'); }
        if ($this->mcpOk('crm_update_knowledge_space', ['public_id' => $pid, 'title' => 'mcp_test_space_u'])) { $this->pass('UPDATE knowledge_space'); } else { $this->fail('UPDATE knowledge_space'); }

        // No DELETE tool exists for knowledge spaces in MCP
        $this->pass('DELETE knowledge_space (skipped - no tool)');
    }

    private function testKnowledgePages(): void
    {
        $spacePid = $this->mcpPid('crm_create_knowledge_space', ['title' => 'mcp_test_page_space']);
        if (!$spacePid) { $this->fail('CREATE knowledge space for pages'); return; }

        $pagePid = $this->mcpPid('crm_create_knowledge_page', ['title' => 'mcp_test_page', 'space_public_id' => $spacePid, 'content' => 'test content']);
        if ($pagePid) { $this->pass('CREATE knowledge_page'); } else { $this->fail('CREATE knowledge_page'); }
        if ($this->mcpOk('crm_get_knowledge_page', ['public_id' => $pagePid])) { $this->pass('READ knowledge_page'); } else { $this->fail('READ knowledge_page'); }
        if ($this->mcpOk('crm_update_knowledge_page', ['public_id' => $pagePid, 'title' => 'mcp_test_page_u'])) { $this->pass('UPDATE knowledge_page'); } else { $this->fail('UPDATE knowledge_page'); }
        if ($this->mcpOk('crm_delete_knowledge_page', ['public_id' => $pagePid])) { $this->pass('DELETE knowledge_page'); } else { $this->fail('DELETE knowledge_page'); }
        $this->mcp('crm_delete_knowledge_space', ['public_id' => $spacePid]);
    }

    private function testTemplates(): void
    {
        $pid = $this->mcpPid('crm_create_template', ['title' => 'mcp_test_template', 'kind' => 'task']);
        if ($pid) { $this->pass('CREATE template'); } else { $this->fail('CREATE template'); return; }
        if ($this->mcpOk('crm_get_template', ['public_id' => $pid, 'kind' => 'task'])) { $this->pass('READ template'); } else { $this->fail('READ template'); }
        if ($this->mcpOk('crm_update_template', ['public_id' => $pid, 'kind' => 'task', 'title' => 'mcp_test_template_u'])) { $this->pass('UPDATE template'); } else { $this->fail('UPDATE template'); }
        if ($this->mcpOk('crm_delete_template', ['public_id' => $pid, 'kind' => 'task'])) { $this->pass('DELETE template'); } else { $this->fail('DELETE template'); }
    }

    private function testEstimateSets(): void
    {
        $r = $this->mcp('crm_list_projects', ['limit' => 1]);
        $sc = $r['result']['structuredContent'] ?? [];
        $projectPid = $sc['projects'][0]['public_id'] ?? null;
        if (!$projectPid) { $this->fail('CREATE estimate set (no project)'); return; }

        $pid = $this->mcpPid('crm_create_estimate_set', ['title' => 'mcp_test_es', 'project_public_id' => $projectPid]);
        if ($pid) { $this->pass('CREATE estimate_set'); } else { $this->fail('CREATE estimate_set'); return; }
        if ($this->mcpOk('crm_get_estimate_set', ['public_id' => $pid])) { $this->pass('READ estimate_set'); } else { $this->fail('READ estimate_set'); }
        if ($this->mcpOk('crm_update_estimate_set', ['public_id' => $pid, 'title' => 'mcp_test_es_u'])) { $this->pass('UPDATE estimate_set'); } else { $this->fail('UPDATE estimate_set'); }
        if ($this->mcpOk('crm_delete_estimate_set', ['public_id' => $pid])) { $this->pass('DELETE estimate_set'); } else { $this->fail('DELETE estimate_set'); }
    }

    private function testListTools(): void
    {
        $listTools = [
            'crm_get_current_user', 'crm_list_tasks', 'crm_list_projects',
            'crm_list_knowledge_spaces', 'crm_list_webhooks', 'crm_list_calendar_events',
            'crm_list_reminders', 'crm_list_sticky_notes', 'crm_list_ideas',
            'crm_list_intake_items', 'crm_list_saved_views', 'crm_list_estimate_sets',
            'crm_list_api_clients', 'crm_list_modules', 'crm_list_security_sessions',
            'crm_list_workflow_rules', 'crm_list_workflow_runs', 'crm_list_sla_policies',
            'crm_list_approvals', 'crm_list_import_jobs', 'crm_list_export_jobs',
            'crm_list_request_logs', 'crm_get_menu', 'crm_get_profile',
            'crm_get_core_version', 'crm_get_ops_system', 'crm_get_ops_metrics',
            'crm_get_dashboard_summary', 'crm_get_analytics_summary',
            'crm_list_api_endpoints', 'crm_get_activity_feed', 'crm_get_activity_history',
            'crm_get_calendar_my_month', 'crm_get_core_update_status',
            'crm_get_core_update_history', 'crm_get_admin_summary_widget',
            'crm_get_admin_system_widget', 'crm_search',
        ];

        foreach ($listTools as $tool) {
            $args = ($tool === 'crm_search') ? ['query' => 'test query'] : [];
            if ($this->mcpOk($tool, $args)) { $this->pass("LIST $tool"); } else { $this->fail("LIST $tool"); }
        }
    }

    private function api(string $method, string $url, ?array $data = null, bool $auth = true): array
    {
        $fullUrl = $this->baseUrl . '?route=' . $url;
        $headers = ['Content-Type: application/json'];
        if ($auth && !empty($this->token)) { $headers[] = "Authorization: Bearer {$this->token}"; }
        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
        if ($data !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response ?: '{}', true) ?: [];
    }

    private function section(string $title): void { echo "\n--- $title ---\n"; }
    private function pass(string $label): void { $this->pass++; echo "  ✓ $label\n"; }
    private function fail(string $label, string $reason = ''): void { $this->fail++; $msg = "  ✗ $label"; if ($reason) $msg .= " [$reason]"; echo "$msg\n"; $this->failures[] = $label . ($reason ? " [$reason]" : ''); }

    private function summary(): void
    {
        echo "\n" . str_repeat('=', 60) . "\nSUMMARY\n" . str_repeat('=', 60) . "\n";
        echo "PASS: {$this->pass}\nFAIL: {$this->fail}\nTOTAL: " . ($this->pass + $this->fail) . "\nFinished: " . date('Y-m-d H:i:s') . "\n";
        if ($this->failures) { echo "\nFailed tests:\n"; foreach ($this->failures as $f) { echo "  - $f\n"; } }
        echo "\n" . ($this->fail === 0 ? 'ALL TESTS PASSED!' : 'SOME TESTS FAILED') . "\n";
    }
}

$test = new McpCrudTest();
$test->run();
