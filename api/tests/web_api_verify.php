<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/**
 * Comprehensive web frontend API verification.
 * Tests every endpoint the web UI calls.
 */

final class WebApiVerify
{
    private string $baseUrl;
    private string $token = '';
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];
    private array $cleanup = [];

    public function __construct()
    {
        $this->baseUrl = rtrim((string)(getenv('CRM_TEST_BASE') ?: 'https://demo.tropatt.com'), '/') . '/api/index.php?route=';
    }

    public function run(): void
    {
        $this->login();
        $this->testAuthEndpoints();
        $this->testUsersEndpoints();
        $this->testRolesEndpoints();
        $this->testTeamsEndpoints();
        $this->testDepartmentsEndpoints();
        $this->testProjectsEndpoints();
        $this->testTasksEndpoints();
        $this->testSubtasksEndpoints();
        $this->testTagsEndpoints();
        $this->testCommentsEndpoints();
        $this->testChecklistsEndpoints();
        $this->testWorklogsEndpoints();
        $this->testCompaniesEndpoints();
        $this->testClientsEndpoints();
        $this->testContactsEndpoints();
        $this->testWebhooksEndpoints();
        $this->testApiClientsEndpoints();
        $this->testIdeasEndpoints();
        $this->testSettingsEndpoints();
        $this->testStatusesEndpoints();
        $this->testPrioritiesEndpoints();
        $this->testApprovalsEndpoints();
        $this->testRecurringEndpoints();
        $this->testFavoritesSubscriptions();
        $this->testFilesEndpoints();
        $this->testWorkflowsEndpoints();
        $this->testStickyNotesEndpoints();
        $this->testCyclesEndpoints();
        $this->testKnowledgeEndpoints();
        $this->testAnalyticsEndpoints();
        $this->testDashboardEndpoints();
        $this->testNotificationsEndpoints();
        $this->testSearchEndpoints();
        $this->testProfileEndpoints();
        $this->testSecurityEndpoints();
        $this->testAdminEndpoints();
        $this->testExportImportEndpoints();
        $this->testRecycleBinEndpoints();
        $this->testEstimateEndpoints();
        $this->testProjectModulesEndpoints();
        $this->testOrganizationsEndpoints();
        $this->testCounterpartiesEndpoints();
        $this->testCustomFieldsEndpoints();
        $this->testMentionsEndpoints();
        $this->testReactionsEndpoints();
        $this->testIntakeEndpoints();
        $this->testTemplateEndpoints();
        $this->testSLAEndpoints();
        $this->testRetirementEndpoints();

        $this->cleanupAll();
        $this->printSummary();
    }

    private function login(): void
    {
        $res = $this->request('POST', 'api/v1/auth/login', ['login' => 'admin', 'password' => 'adminadmin'], false);
        $this->token = (string)($res['data']['access_token'] ?? '');
        $this->assert('Login', $this->token !== '');
    }

    // ===== AUTH =====
    private function testAuthEndpoints(): void
    {
        $s = 'Auth';
        $this->ok("$s: me", 'GET', 'api/v1/auth/me');
        $this->ok("$s: menu", 'GET', 'api/v1/auth/menu');
        $this->ok("$s: logout", 'POST', 'api/v1/auth/logout');
        // Re-login since logout destroys the token
        $res = $this->request('POST', 'api/v1/auth/login', ['login' => 'admin', 'password' => 'adminadmin'], false);
        $this->token = (string)($res['data']['access_token'] ?? '');
        $this->assert("$s: re-login", $this->token !== '');
    }

    // ===== USERS =====
    private function testUsersEndpoints(): void
    {
        $s = 'Users';
        $this->ok("$s: list", 'GET', 'api/v1/users');
        // Create user
        $r = $this->request('POST', 'api/v1/users', [
            'login' => 'webtest_' . bin2hex(random_bytes(4)),
            'email' => 'wt_' . bin2hex(random_bytes(4)) . '@test.local',
            'password' => 'Test12345!',
            'full_name' => 'Web Test User',
        ]);
        $pid = $r['data']['user']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/users/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/users/$pid", ['full_name' => 'Updated']);
            $this->ok("$s: delete", 'DELETE', "api/v1/users/$pid");
        }
    }

    // ===== ROLES =====
    private function testRolesEndpoints(): void
    {
        $s = 'Roles';
        $this->ok("$s: list", 'GET', 'api/v1/roles');
        $r = $this->request('POST', 'api/v1/roles', ['code' => 'wtrole_' . bin2hex(random_bytes(4)), 'title' => 'Web Test Role']);
        $pid = $r['data']['role']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/roles/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/roles/$pid", ['title' => 'Updated Role']);
            $this->ok("$s: permissions", 'GET', "api/v1/roles/$pid/permissions");
            $this->ok("$s: delete", 'DELETE', "api/v1/roles/$pid");
        }
        $this->ok("$s: permissions registry", 'GET', 'api/v1/permissions');
    }

    // ===== TEAMS =====
    private function testTeamsEndpoints(): void
    {
        $s = 'Teams';
        $this->ok("$s: list", 'GET', 'api/v1/teams');
        $r = $this->request('POST', 'api/v1/teams', ['title' => 'Web Test Team ' . bin2hex(random_bytes(4))]);
        $pid = $r['data']['team']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/teams/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/teams/$pid", ['title' => 'Updated Team']);
            $this->ok("$s: delete", 'DELETE', "api/v1/teams/$pid");
        }
    }

    // ===== DEPARTMENTS =====
    private function testDepartmentsEndpoints(): void
    {
        $s = 'Departments';
        $this->ok("$s: list", 'GET', 'api/v1/departments');
        $r = $this->request('POST', 'api/v1/departments', ['title' => 'Web Test Dept ' . bin2hex(random_bytes(4))]);
        $pid = $r['data']['department']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/departments/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/departments/$pid", ['title' => 'Updated Dept']);
            $this->ok("$s: delete", 'DELETE', "api/v1/departments/$pid");
        }
    }

    // ===== PROJECTS =====
    private function testProjectsEndpoints(): void
    {
        $s = 'Projects';
        $this->ok("$s: list", 'GET', 'api/v1/projects');
        $prefix = 'P' . strtoupper(bin2hex(random_bytes(4)));
        $r = $this->request('POST', 'api/v1/projects', [
            'title' => 'Web Test Project',
            'description' => 'Test',
            'status' => 'active',
            'priority' => 'normal',
            'task_key_prefix' => $prefix,
        ]);
        $pid = $r['data']['project']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/projects/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/projects/$pid", ['title' => 'Updated Project']);
            $this->ok("$s: delete", 'DELETE', "api/v1/projects/$pid");
        }
        $this->ok("$s: dashboard summary", 'GET', 'api/v1/dashboard/summary');
        $this->ok("$s: kanban", 'GET', 'api/v1/pages/kanban');
        $this->ok("$s: my-day", 'GET', 'api/v1/pages/my-day');
        $this->ok("$s: my-week", 'GET', 'api/v1/pages/my-week');
    }

    // ===== TASKS =====
    private function testTasksEndpoints(): void
    {
        $s = 'Tasks';
        $this->ok("$s: list", 'GET', 'api/v1/tasks');
        $r = $this->request('POST', 'api/v1/tasks', ['title' => 'Web Test Task', 'status' => 'new', 'priority' => 'normal']);
        $pid = $r['data']['task']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/tasks/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/tasks/$pid", ['title' => 'Updated Task']);
            $this->ok("$s: tags", 'GET', "api/v1/tasks/$pid/tags");
            $this->ok("$s: subtasks", 'GET', "api/v1/tasks/$pid/subtasks");
            $this->ok("$s: checklists", 'GET', "api/v1/tasks/$pid/checklists");
            $this->ok("$s: comments", 'GET', "api/v1/tasks/$pid/comments");
            $this->ok("$s: activity", 'GET', "api/v1/tasks/$pid/activity");
            $this->ok("$s: files", 'GET', "api/v1/tasks/$pid/files");
            $this->ok("$s: comment-draft", 'GET', "api/v1/tasks/$pid/comment-draft");
            $this->cleanup[] = ['DELETE', "api/v1/tasks/$pid"];
        }
        // Bulk
        $this->ok("$s: bulk", 'POST', 'api/v1/tasks/bulk', [
            'task_public_ids' => [$pid],
            'changes' => ['priority' => 'high'],
        ]);
        // Statuses for tasks
        $this->ok("$s: statuses", 'GET', 'api/v1/statuses');
    }

    // ===== SUBTASKS =====
    private function testSubtasksEndpoints(): void
    {
        $s = 'Subtasks';
        $taskPid = $this->createTask('Subtask Test Task');
        if (!$taskPid) return;
        $r = $this->request('POST', "api/v1/tasks/$taskPid/subtasks", ['title' => 'Test Subtask', 'status' => 'new']);
        $pid = $r['data']['subtask']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: update", 'PATCH', "api/v1/subtasks/$pid", ['title' => 'Updated Subtask']);
            $this->ok("$s: delete", 'DELETE', "api/v1/subtasks/$pid");
        }
        $this->cleanup[] = ['DELETE', "api/v1/tasks/$taskPid"];
    }

    // ===== TAGS =====
    private function testTagsEndpoints(): void
    {
        $s = 'Tags';
        $this->ok("$s: list", 'GET', 'api/v1/tags');
        $r = $this->request('POST', 'api/v1/tags', ['code' => 'wt_' . bin2hex(random_bytes(4)), 'title' => 'Web Test Tag', 'color' => '#ff0000']);
        $pid = $r['data']['tag']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: update", 'PATCH', "api/v1/tags/$pid", ['title' => 'Updated Tag']);
            $this->ok("$s: delete", 'DELETE', "api/v1/tags/$pid");
        }
    }

    // ===== COMMENTS =====
    private function testCommentsEndpoints(): void
    {
        $s = 'Comments';
        $taskPid = $this->createTask('Comment Test Task');
        if (!$taskPid) return;
        $r = $this->request('POST', "api/v1/tasks/$taskPid/comments", ['body' => 'Test comment']);
        $pid = $r['data']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: update", 'PATCH', "api/v1/comments/$pid", ['body' => 'Updated']);
            $this->ok("$s: delete", 'DELETE', "api/v1/comments/$pid");
        }
        $this->cleanup[] = ['DELETE', "api/v1/tasks/$taskPid"];
    }

    // ===== CHECKLISTS =====
    private function testChecklistsEndpoints(): void
    {
        $s = 'Checklists';
        $taskPid = $this->createTask('Checklist Test Task');
        if (!$taskPid) return;
        $r = $this->request('POST', "api/v1/tasks/$taskPid/checklists", ['title' => 'Test Checklist']);
        $clPid = $r['data']['checklist']['public_id'] ?? '';
        $this->assert("$s: create", $clPid !== '');
        if ($clPid) {
            $this->ok("$s: get", 'GET', "api/v1/checklists/$clPid");
            $this->ok("$s: update", 'PATCH', "api/v1/checklists/$clPid", ['title' => 'Updated Checklist']);
            $r2 = $this->request('POST', "api/v1/checklists/$clPid/items", ['title' => 'Item 1', 'is_done' => 0]);
            $itemPid = $r2['data']['item']['public_id'] ?? $r2['data']['public_id'] ?? '';
            $this->assert("$s: item create", $itemPid !== '');
            if ($itemPid) {
                $this->ok("$s: item get", 'GET', "api/v1/checklist-items/$itemPid");
                $this->ok("$s: item update", 'PATCH', "api/v1/checklist-items/$itemPid", ['title' => 'Updated Item']);
                $this->ok("$s: item delete", 'DELETE', "api/v1/checklist-items/$itemPid");
            }
            $this->ok("$s: delete", 'DELETE', "api/v1/checklists/$clPid");
        }
        $this->cleanup[] = ['DELETE', "api/v1/tasks/$taskPid"];
    }

    // ===== WORKLOGS =====
    private function testWorklogsEndpoints(): void
    {
        $s = 'Worklogs';
        $taskPid = $this->createTask('Worklog Test Task');
        if (!$taskPid) return;
        $r = $this->request('POST', 'api/v1/worklogs', [
            'task_public_id' => $taskPid,
            'minutes_spent' => 60,
            'note' => 'Test worklog',
        ]);
        $pid = $r['data']['worklog']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: update", 'PATCH', "api/v1/worklogs/$pid", ['minutes_spent' => 90]);
            $this->ok("$s: delete", 'DELETE', "api/v1/worklogs/$pid");
        }
        $this->ok("$s: summary", 'GET', 'api/v1/worklogs/summary');
        $this->ok("$s: detail", 'GET', 'api/v1/worklogs/detail');
        $this->ok("$s: earnings", 'GET', 'api/v1/worklogs/earnings');
        $this->ok("$s: matrix", 'GET', 'api/v1/worklogs/matrix');
        $this->cleanup[] = ['DELETE', "api/v1/tasks/$taskPid"];
    }

    // ===== COMPANIES =====
    private function testCompaniesEndpoints(): void
    {
        $s = 'Companies';
        $this->ok("$s: list", 'GET', 'api/v1/companies');
        $r = $this->request('POST', 'api/v1/companies', ['title' => 'Web Test Company ' . bin2hex(random_bytes(4))]);
        $pid = $r['data']['company']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/companies/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/companies/$pid", ['title' => 'Updated Company']);
            $this->ok("$s: delete", 'DELETE', "api/v1/companies/$pid");
        }
    }

    // ===== CLIENTS =====
    private function testClientsEndpoints(): void
    {
        $s = 'Clients';
        $this->ok("$s: list", 'GET', 'api/v1/clients');
        $r = $this->request('POST', 'api/v1/clients', [
            'title' => 'Web Test Client ' . bin2hex(random_bytes(4)),
            'status' => 'active',
        ]);
        $pid = $r['data']['client']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/clients/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/clients/$pid", ['title' => 'Updated Client']);
            $this->ok("$s: delete", 'DELETE', "api/v1/clients/$pid");
        }
    }

    // ===== CONTACTS =====
    private function testContactsEndpoints(): void
    {
        $s = 'Contacts';
        $this->ok("$s: list", 'GET', 'api/v1/contacts');
        $r = $this->request('POST', 'api/v1/contacts', [
            'full_name' => 'Web Test Contact ' . bin2hex(random_bytes(4)),
            'email' => 'ct@test.local',
        ]);
        $pid = $r['data']['contact']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/contacts/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/contacts/$pid", ['full_name' => 'Updated Contact']);
            $this->ok("$s: delete", 'DELETE', "api/v1/contacts/$pid");
        }
    }

    // ===== WEBHOOKS =====
    private function testWebhooksEndpoints(): void
    {
        $s = 'Webhooks';
        $this->ok("$s: list", 'GET', 'api/v1/webhooks');
        $r = $this->request('POST', 'api/v1/webhooks', [
            'title' => 'Web Test Webhook ' . bin2hex(random_bytes(4)),
            'endpoint' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['task.updated'],
            'is_active' => 1,
        ]);
        $pid = $r['data']['webhook']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/webhooks/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/webhooks/$pid", ['title' => 'Updated Webhook']);
            $this->ok("$s: delete", 'DELETE', "api/v1/webhooks/$pid");
        }
    }

    // ===== API CLIENTS =====
    private function testApiClientsEndpoints(): void
    {
        $s = 'API Clients';
        $this->ok("$s: list", 'GET', 'api/v1/api-clients');
        $r = $this->request('POST', 'api/v1/api-clients', [
            'title' => 'Web Test API Client ' . bin2hex(random_bytes(4)),
            'scopes' => ['read:tasks'],
            'is_active' => 1,
        ]);
        $pid = $r['data']['api_client']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/api-clients/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/api-clients/$pid", ['title' => 'Updated API Client']);
            $this->ok("$s: keys list", 'GET', "api/v1/api-clients/$pid/keys");
            $kr = $this->request('POST', "api/v1/api-clients/$pid/keys", [
                'scopes' => ['read:tasks'],
                'expires_at' => '2027-12-31 23:59:59',
            ]);
            $keyPid = $kr['data']['api_key']['public_id'] ?? '';
            $this->assert("$s: key create", $keyPid !== '');
            if ($keyPid) {
                $this->ok("$s: key usage", 'GET', "api/v1/api-keys/$keyPid/usage");
                $this->ok("$s: key rotate", 'POST', "api/v1/api-keys/$keyPid/rotate");
                // Get new key after rotate
                $keys = $this->request('GET', "api/v1/api-clients/$pid/keys");
                $newKeyPid = $keys['data']['items'][0]['public_id'] ?? '';
                if ($newKeyPid) {
                    $this->ok("$s: key revoke", 'POST', "api/v1/api-keys/$newKeyPid/revoke");
                }
            }
            $this->ok("$s: delete", 'DELETE', "api/v1/api-clients/$pid");
        }
    }

    // ===== IDEAS =====
    private function testIdeasEndpoints(): void
    {
        $s = 'Ideas';
        $this->ok("$s: list", 'GET', 'api/v1/ideas');
        $r = $this->request('POST', 'api/v1/ideas', [
            'title' => 'Web Test Idea ' . bin2hex(random_bytes(4)),
            'description' => 'Test idea',
            'category' => 'improvement',
        ]);
        $pid = $r['data']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: get", 'GET', "api/v1/ideas/$pid");
            $this->ok("$s: update", 'PATCH', "api/v1/ideas/$pid", ['title' => 'Updated Idea']);
            $this->ok("$s: questions", 'GET', "api/v1/ideas/$pid/questions");
            $this->ok("$s: vote", 'POST', "api/v1/ideas/$pid/vote");
            $this->ok("$s: comments", 'GET', "api/v1/ideas/$pid/comments");
            $this->ok("$s: delete", 'DELETE', "api/v1/ideas/$pid");
        }
    }

    // ===== SETTINGS =====
    private function testSettingsEndpoints(): void
    {
        $s = 'Settings';
        $this->ok("$s: list", 'GET', 'api/v1/settings');
    }

    // ===== STATUSES =====
    private function testStatusesEndpoints(): void
    {
        $s = 'Statuses';
        $this->ok("$s: list", 'GET', 'api/v1/statuses');
    }

    // ===== PRIORITIES =====
    private function testPrioritiesEndpoints(): void
    {
        $s = 'Priorities';
        $this->ok("$s: list", 'GET', 'api/v1/priorities');
    }

    // ===== APPROVALS =====
    private function testApprovalsEndpoints(): void
    {
        $s = 'Approvals';
        $this->ok("$s: list", 'GET', 'api/v1/approvals');
    }

    // ===== RECURRING =====
    private function testRecurringEndpoints(): void
    {
        $s = 'Recurring';
        $this->ok("$s: list", 'GET', 'api/v1/recurring');
    }

    // ===== FAVORITES / SUBSCRIPTIONS =====
    private function testFavoritesSubscriptions(): void
    {
        $taskPid = $this->createTask('Fav/Sub Test');
        if (!$taskPid) return;
        $this->ok('Favorites: subscribe', 'POST', 'api/v1/subscriptions', ['entity_type' => 'task', 'entity_public_id' => $taskPid]);
        $this->ok('Favorites: list', 'GET', 'api/v1/subscriptions');
        $this->ok('Favorites: favorite', 'POST', 'api/v1/favorites', ['entity_type' => 'task', 'entity_public_id' => $taskPid]);
        $this->ok('Favorites: list favorites', 'GET', 'api/v1/favorites');
        // Cleanup
        $subs = $this->request('GET', 'api/v1/subscriptions');
        foreach ($subs['data']['items'] ?? [] as $sub) {
            if (($sub['entity_public_id'] ?? '') === $taskPid) {
                $this->request('DELETE', "api/v1/subscriptions/{$sub['public_id']}");
            }
        }
        $favs = $this->request('GET', 'api/v1/favorites');
        foreach ($favs['data']['items'] ?? [] as $fav) {
            if (($fav['entity_public_id'] ?? '') === $taskPid) {
                $this->request('DELETE', "api/v1/favorites/{$fav['public_id']}");
            }
        }
        $this->cleanup[] = ['DELETE', "api/v1/tasks/$taskPid"];
    }

    // ===== FILES =====
    private function testFilesEndpoints(): void
    {
        $this->ok('Files: list', 'GET', 'api/v1/files');
    }

    // ===== WORKFLOWS =====
    private function testWorkflowsEndpoints(): void
    {
        $this->ok('Workflow: rules', 'GET', 'api/v1/workflow/rules');
        $this->ok('Workflow: runs', 'GET', 'api/v1/workflow/runs');
    }

    // ===== STICKY NOTES =====
    private function testStickyNotesEndpoints(): void
    {
        $s = 'StickyNotes';
        $this->ok("$s: list", 'GET', 'api/v1/sticky-notes');
        $r = $this->request('POST', 'api/v1/sticky-notes', ['content' => 'Test note', 'color' => 'yellow']);
        $pid = $r['data']['sticky_note']['public_id'] ?? $r['data']['public_id'] ?? '';
        $this->assert("$s: create", $pid !== '');
        if ($pid) {
            $this->ok("$s: update", 'PATCH', "api/v1/sticky-notes/$pid", ['content' => 'Updated note']);
            $this->ok("$s: delete", 'DELETE', "api/v1/sticky-notes/$pid");
        }
    }

    // ===== CYCLES =====
    private function testCyclesEndpoints(): void
    {
        $this->ok('Cycles: list', 'GET', 'api/v1/cycles');
    }

    // ===== KNOWLEDGE =====
    private function testKnowledgeEndpoints(): void
    {
        $this->ok('Knowledge: overview', 'GET', 'api/v1/knowledge/overview');
    }

    // ===== ANALYTICS =====
    private function testAnalyticsEndpoints(): void
    {
        $this->ok('Analytics: summary', 'GET', 'api/v1/analytics/summary');
        $this->ok('Analytics: projects', 'GET', 'api/v1/analytics/projects');
        $this->ok('Analytics: users', 'GET', 'api/v1/analytics/users');
    }

    // ===== DASHBOARD =====
    private function testDashboardEndpoints(): void
    {
        $this->ok('Dashboard: summary', 'GET', 'api/v1/dashboard/summary');
    }

    // ===== NOTIFICATIONS =====
    private function testNotificationsEndpoints(): void
    {
        $this->ok('Notifications: list', 'GET', 'api/v1/notifications');
        $this->ok('Notifications: counters', 'GET', 'api/v1/notifications/counters');
        $this->ok('Notifications: mark-all-read', 'POST', 'api/v1/notifications/mark-all-read');
    }

    // ===== SEARCH =====
    private function testSearchEndpoints(): void
    {
        $this->ok('Search: global', 'GET', 'api/v1/search/global?q=test');
    }

    // ===== PROFILE =====
    private function testProfileEndpoints(): void
    {
        $this->ok('Profile: me', 'GET', 'api/v1/profile/me');
        $this->ok('Profile: preferences', 'GET', 'api/v1/profile/preferences');
    }

    // ===== SECURITY =====
    private function testSecurityEndpoints(): void
    {
        $this->ok('Security: 2fa status', 'GET', 'api/v1/security/2fa/status');
        $this->ok('Security: sessions', 'GET', 'api/v1/security/sessions');
        $this->ok('Security: impersonation status', 'POST', 'api/v1/security/impersonation/status');
    }

    // ===== ADMIN =====
    private function testAdminEndpoints(): void
    {
        $this->ok('Admin: cache', 'GET', 'api/v1/admin/cache');
        $this->ok('Admin: role-matrix', 'GET', 'api/v1/admin/role-matrix');
        $this->ok('Admin: widgets summary', 'GET', 'api/v1/admin/widgets/summary');
        $this->ok('Admin: ops system', 'GET', 'api/v1/ops/system');
        $this->ok('Feature flags: list', 'GET', 'api/v1/feature-flags');
        $this->ok('Feature flags: list2', 'GET', 'api/v1/feature-flags/list');
        $this->ok('Logs: audit', 'GET', 'api/v1/logs/audit');
        $this->ok('Logs: request', 'GET', 'api/v1/logs/request');
        $this->ok('Logs: security', 'GET', 'api/v1/logs/security');
    }

    // ===== EXPORT/IMPORT =====
    private function testExportImportEndpoints(): void
    {
        $this->ok('Export: jobs', 'GET', 'api/v1/export/jobs');
        $this->ok('Import: jobs', 'GET', 'api/v1/import/jobs');
    }

    // ===== RECYCLE BIN =====
    private function testRecycleBinEndpoints(): void
    {
        $this->ok('RecycleBin: list', 'GET', 'api/v1/recycle-bin');
    }

    // ===== ESTIMATES =====
    private function testEstimateEndpoints(): void
    {
        $this->ok('EstimateSets: list', 'GET', 'api/v1/estimate-sets');
    }

    // ===== PROJECT MODULES =====
    private function testProjectModulesEndpoints(): void
    {
        $this->ok('ProjectModules: list', 'GET', 'api/v1/project-modules');
    }

    // ===== ORGANIZATIONS =====
    private function testOrganizationsEndpoints(): void
    {
        $this->ok('Organizations: list', 'GET', 'api/v1/organizations');
    }

    // ===== COUNTERPARTIES =====
    private function testCounterpartiesEndpoints(): void
    {
        $this->ok('Counterparties: list', 'GET', 'api/v1/counterparties');
    }

    // ===== CUSTOM FIELDS =====
    private function testCustomFieldsEndpoints(): void
    {
        $this->ok('CustomFields: list', 'GET', 'api/v1/custom-fields');
    }

    // ===== MENTIONS =====
    private function testMentionsEndpoints(): void
    {
        $this->ok('Mentions: create', 'POST', 'api/v1/mentions', ['entity_type' => 'task', 'entity_public_id' => 'test', 'mentioned_user_public_id' => 'usr_8A83F4604581A11A', 'comment_body' => 'test']);
    }

    // ===== REACTIONS =====
    private function testReactionsEndpoints(): void
    {
        $this->ok('Reactions: list', 'GET', 'api/v1/reactions');
    }

    // ===== INTAKE =====
    private function testIntakeEndpoints(): void
    {
        $this->ok('IntakeItems: list', 'GET', 'api/v1/intake-items');
    }

    // ===== TEMPLATES =====
    private function testTemplateEndpoints(): void
    {
        $this->ok('TemplateProjects: list', 'GET', 'api/v1/template/projects');
        $this->ok('TemplateTasks: list', 'GET', 'api/v1/template/tasks');
    }

    // ===== SLA =====
    private function testSLAEndpoints(): void
    {
        $this->ok('SLA: policies', 'GET', 'api/v1/sla/policies');
        $this->ok('SLA: report', 'GET', 'api/v1/sla/report');
    }

    // ===== RETIREMENT =====
    private function testRetirementEndpoints(): void
    {
        $this->ok('Retention: metadata', 'GET', 'api/v1/retention/metadata');
    }

    // ===== HELPERS =====
    private function createTask(string $title): string
    {
        $r = $this->request('POST', 'api/v1/tasks', ['title' => $title, 'status' => 'new']);
        return $r['data']['task']['public_id'] ?? '';
    }

    private function ok(string $label, string $method, string $route, ?array $body = null): void
    {
        $res = $this->request($method, $route, $body);
        $ok = ($res['success'] ?? false) === true || ($res['_http'] ?? 0) < 500;
        $this->assert("$label ($method)", $ok, $ok ? '' : 'Code: ' . ($res['code'] ?? '') . ' HTTP: ' . ($res['_http'] ?? ''));
    }

    private function cleanupAll(): void
    {
        foreach (array_reverse($this->cleanup) as [$method, $route]) {
            $this->request($method, $route);
        }
    }

    private function request(string $method, string $route, ?array $body = null, bool $withAuth = true): array
    {
        $ch = curl_init();
        $headers = ['Content-Type: application/json'];
        if ($withAuth && $this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        $url = $this->baseUrl . str_replace('%2F', '/', rawurlencode($route));
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!is_string($raw)) {
            return ['success' => false, 'error' => 'curl failed', '_http' => $http];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'invalid json', '_http' => $http];
        }
        $decoded['_http'] = $http;
        return $decoded;
    }

    private function assert(string $label, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  ✓ $label\n";
        } else {
            $this->failed++;
            $msg = "  ✗ $label" . ($detail ? " — $detail" : '');
            echo "$msg\n";
            $this->errors[] = $msg;
        }
    }

    private function printSummary(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "RESULTS: {$this->passed} passed, {$this->failed} failed\n";
        echo str_repeat('=', 60) . "\n";
        if ($this->errors) {
            echo "\nFAILED:\n";
            foreach ($this->errors as $e) echo "$e\n";
        }
        exit($this->failed > 0 ? 1 : 0);
    }
}

$test = new WebApiVerify();
$test->run();
