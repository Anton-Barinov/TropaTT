<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/**
 * Live API CRUD test — hits the demo server for every entity.
 * Usage: php api/tests/api_crud_live_test.php
 */

final class LiveCrudTest
{
    private string $baseUrl;
    private string $token = '';
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function __construct()
    {
        $this->baseUrl = rtrim((string)(getenv('CRM_TEST_BASE') ?: 'https://demo.tropatt.com'), '/') . '/api/index.php?route=';
    }

    public function run(): void
    {
        $this->login();
        $this->testUsers();
        $this->testRoles();
        $this->testTeams();
        $this->testDepartments();
        $this->testCompanies();
        $this->testClients();
        $this->testContacts();
        $this->testProjects();
        $this->testTasks();
        $this->testTags();
        $this->testComments();
        $this->testSubtasks();
        $this->testChecklists();
        $this->testWorklogs();
        $this->testPriorities();
        $this->testStatuses();
        $this->testWebhooks();
        $this->testApiClients();
        $this->testIdeas();
        $this->testSettings();
        $this->testSecurity();

        $this->printSummary();
    }

    private function login(): void
    {
        $res = $this->request('POST', 'api/v1/auth/login', [
            'login' => 'admin',
            'password' => 'adminadmin',
        ], false);
        $this->token = (string)($res['data']['access_token'] ?? '');
        $this->assert('Login', $this->token !== '', 'Expected access_token');
    }

    /** Extract the entity public_id from various response shapes */
    private function extractPid(array $res, string $entity): string
    {
        // Some endpoints nest under data.<entity>.public_id, others use data.public_id directly
        return (string)($res['data'][$entity]['public_id'] ?? $res['data']['public_id'] ?? '');
    }

    /** Extract a nested field from response */
    private function extractField(array $res, string $entity, string $field): mixed
    {
        return $res['data'][$entity][$field] ?? $res['data'][$field] ?? null;
    }

    private function testUsers(): void
    {
        $section = 'Users';
        $res = $this->request('GET', 'api/v1/users');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items array');

        $login = 'test_live_' . time();
        $created = $this->request('POST', 'api/v1/users', [
            'login' => $login,
            'email' => $login . '@test.local',
            'password' => 'Test12345!',
            'full_name' => 'Test Live User',
            'locale' => 'ru-ru',
        ]);
        $pid = $this->extractPid($created, 'user');
        $this->assert("$section: create", $pid !== '', 'Expected public_id, got: ' . json_encode($created['data'] ?? $created['error'] ?? ''));

        if ($pid) {
            $read = $this->request('GET', "api/v1/users/$pid");
            $this->assert("$section: read", $this->extractField($read, 'user', 'public_id') === $pid, 'Mismatch');

            $upd = $this->request('PATCH', "api/v1/users/$pid", ['full_name' => 'Updated Test User']);
            $this->assert("$section: update", $this->extractField($upd, 'user', 'full_name') === 'Updated Test User', 'Name mismatch');

            $del = $this->request('DELETE', "api/v1/users/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testRoles(): void
    {
        $section = 'Roles';
        $res = $this->request('GET', 'api/v1/roles');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/roles', [
            'code' => 'test_role_' . time(),
            'title' => 'Test Role Live',
        ]);
        $pid = $this->extractPid($created, 'role');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/roles/$pid", ['title' => 'Updated Test Role']);
            $this->assert("$section: update", $this->extractField($upd, 'role', 'title') === 'Updated Test Role', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/roles/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testTeams(): void
    {
        $section = 'Teams';
        $res = $this->request('GET', 'api/v1/teams');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/teams', ['title' => 'Test Team ' . time()]);
        $pid = $this->extractPid($created, 'team');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/teams/$pid", ['title' => 'Updated Team']);
            $this->assert("$section: update", $this->extractField($upd, 'team', 'title') === 'Updated Team', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/teams/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testDepartments(): void
    {
        $section = 'Departments';
        $res = $this->request('GET', 'api/v1/departments');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/departments', ['title' => 'Test Dept ' . time()]);
        $pid = $this->extractPid($created, 'department');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/departments/$pid", ['title' => 'Updated Dept']);
            $this->assert("$section: update", $this->extractField($upd, 'department', 'title') === 'Updated Dept', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/departments/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testCompanies(): void
    {
        $section = 'Companies';
        $res = $this->request('GET', 'api/v1/companies');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/companies', ['title' => 'Test Company ' . time()]);
        $pid = $this->extractPid($created, 'company');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/companies/$pid", ['title' => 'Updated Company']);
            $this->assert("$section: update", $this->extractField($upd, 'company', 'title') === 'Updated Company', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/companies/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testClients(): void
    {
        $section = 'Clients';
        $cRes = $this->request('POST', 'api/v1/companies', ['title' => 'Client Parent ' . time()]);
        $companyPid = $this->extractPid($cRes, 'company');
        if (!$companyPid) { $this->assert("$section: setup", false, 'Need company'); return; }

        $res = $this->request('GET', 'api/v1/clients');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/clients', [
            'company_public_id' => $companyPid,
            'title' => 'Test Client ' . time(),
            'email' => 'client@test.local',
            'phone' => '+7 999 000-00-01',
            'status' => 'active',
        ]);
        $pid = $this->extractPid($created, 'client');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/clients/$pid", ['title' => 'Updated Client']);
            $this->assert("$section: update", $this->extractField($upd, 'client', 'title') === 'Updated Client', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/clients/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
        $this->request('DELETE', "api/v1/companies/$companyPid");
    }

    private function testContacts(): void
    {
        $section = 'Contacts';
        $cRes = $this->request('POST', 'api/v1/companies', ['title' => 'Contact Parent ' . time()]);
        $companyPid = $this->extractPid($cRes, 'company');
        if (!$companyPid) { $this->assert("$section: setup", false, 'Need company'); return; }

        $clRes = $this->request('POST', 'api/v1/clients', [
            'company_public_id' => $companyPid,
            'title' => 'Contact Client ' . time(),
            'status' => 'active',
        ]);
        $clientPid = $this->extractPid($clRes, 'client');

        $res = $this->request('GET', 'api/v1/contacts');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/contacts', [
            'company_public_id' => $companyPid,
            'client_public_id' => $clientPid,
            'full_name' => 'Test Contact ' . time(),
            'email' => 'contact@test.local',
            'phone' => '+7 999 000-00-02',
        ]);
        $pid = $this->extractPid($created, 'contact');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/contacts/$pid", ['full_name' => 'Updated Contact']);
            $this->assert("$section: update", $this->extractField($upd, 'contact', 'full_name') === 'Updated Contact', 'Name mismatch');

            $del = $this->request('DELETE', "api/v1/contacts/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }

        if ($clientPid) $this->request('DELETE', "api/v1/clients/$clientPid");
        $this->request('DELETE', "api/v1/companies/$companyPid");
    }

    private function testProjects(): void
    {
        $section = 'Projects';
        $cRes = $this->request('POST', 'api/v1/companies', ['title' => 'Proj Company ' . time()]);
        $companyPid = $this->extractPid($cRes, 'company');
        $clRes = $this->request('POST', 'api/v1/clients', [
            'company_public_id' => $companyPid,
            'title' => 'Proj Client ' . time(),
            'status' => 'active',
        ]);
        $clientPid = $this->extractPid($clRes, 'client');

        $res = $this->request('GET', 'api/v1/projects');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/projects', [
            'title' => 'Test Project ' . bin2hex(random_bytes(4)),
            'description' => 'Live test project',
            'status' => 'active',
            'priority' => 'normal',
            'client_public_id' => $clientPid,
        ]);
        $pid = $this->extractPid($created, 'project');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/projects/$pid", ['title' => 'Updated Project', 'priority' => 'high']);
            $this->assert("$section: update", $this->extractField($upd, 'project', 'title') === 'Updated Project', 'Title mismatch');

            $read = $this->request('GET', "api/v1/projects/$pid");
            $this->assert("$section: read", $this->extractField($read, 'project', 'public_id') === $pid, 'Read mismatch');

            $del = $this->request('DELETE', "api/v1/projects/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }

        $this->request('DELETE', "api/v1/clients/$clientPid");
        $this->request('DELETE', "api/v1/companies/$companyPid");
    }

    private function testTasks(): void
    {
        $section = 'Tasks';
        $res = $this->request('GET', 'api/v1/tasks');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/tasks', [
            'title' => 'Test Task ' . time(),
            'description' => 'Live test task',
            'status' => 'new',
            'priority' => 'normal',
        ]);
        $pid = $this->extractPid($created, 'task');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/tasks/$pid", ['title' => 'Updated Task', 'priority' => 'high']);
            $this->assert("$section: update", $this->extractField($upd, 'task', 'title') === 'Updated Task', 'Title mismatch');

            $read = $this->request('GET', "api/v1/tasks/$pid");
            $this->assert("$section: read", $this->extractField($read, 'task', 'public_id') === $pid, 'Read mismatch');

            $del = $this->request('DELETE', "api/v1/tasks/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testTags(): void
    {
        $section = 'Tags';
        $res = $this->request('GET', 'api/v1/tags');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/tags', [
            'code' => 'test_tag_' . time(),
            'title' => 'Test Tag',
            'color' => '#ff0000',
        ]);
        $pid = $this->extractPid($created, 'tag');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/tags/$pid", ['title' => 'Updated Tag', 'color' => '#00ff00']);
            $this->assert("$section: update", $this->extractField($upd, 'tag', 'title') === 'Updated Tag', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/tags/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testComments(): void
    {
        $section = 'Comments';
        $tRes = $this->request('POST', 'api/v1/tasks', ['title' => 'Comment Test ' . time(), 'status' => 'new']);
        $taskPid = $this->extractPid($tRes, 'task');
        if (!$taskPid) { $this->assert("$section: setup", false, 'Need task'); return; }

        $created = $this->request('POST', "api/v1/tasks/$taskPid/comments", ['body' => 'Test comment ' . time()]);
        // Comments return data.public_id directly (not nested under data.comment)
        $pid = (string)($created['data']['public_id'] ?? $created['data']['comment']['public_id'] ?? '');
        $this->assert("$section: create", $pid !== '', 'Expected public_id, got: ' . json_encode($created['data'] ?? $created['code'] ?? ''));

        if ($pid) {
            $list = $this->request('GET', "api/v1/tasks/$taskPid/comments");
            $this->assert("$section: list", isset($list['data']['items']), 'Expected items');

            $del = $this->request('DELETE', "api/v1/comments/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed: ' . json_encode($del['code'] ?? ''));
        }

        $this->request('DELETE', "api/v1/tasks/$taskPid");
    }

    private function testSubtasks(): void
    {
        $section = 'Subtasks';
        $tRes = $this->request('POST', 'api/v1/tasks', ['title' => 'Subtask Test ' . time(), 'status' => 'new']);
        $taskPid = $this->extractPid($tRes, 'task');
        if (!$taskPid) { $this->assert("$section: setup", false, 'Need task'); return; }

        $created = $this->request('POST', "api/v1/tasks/$taskPid/subtasks", ['title' => 'Test subtask', 'status' => 'new']);
        $pid = $this->extractPid($created, 'subtask');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/subtasks/$pid", ['title' => 'Updated subtask', 'status' => 'in_progress']);
            $this->assert("$section: update", $this->extractField($upd, 'subtask', 'title') === 'Updated subtask', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/subtasks/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }

        $this->request('DELETE', "api/v1/tasks/$taskPid");
    }

    private function testChecklists(): void
    {
        $section = 'Checklists';
        $tRes = $this->request('POST', 'api/v1/tasks', ['title' => 'Checklist Test ' . time(), 'status' => 'new']);
        $taskPid = $this->extractPid($tRes, 'task');
        if (!$taskPid) { $this->assert("$section: setup", false, 'Need task'); return; }

        $created = $this->request('POST', "api/v1/tasks/$taskPid/checklists", ['title' => 'Test checklist']);
        $pid = $this->extractPid($created, 'checklist');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $item = $this->request('POST', "api/v1/checklists/$pid/items", ['title' => 'Check item 1', 'is_done' => 0]);
            $itemPid = (string)($item['data']['item']['public_id'] ?? $item['data']['public_id'] ?? '');
            $this->assert("$section: item create", $itemPid !== '', 'Expected item public_id');

            if ($itemPid) {
                $this->request('PATCH', "api/v1/checklist-items/$itemPid", ['is_done' => 1]);
                $del = $this->request('DELETE', "api/v1/checklist-items/$itemPid");
                $this->assert("$section: item delete", $del['success'] ?? false, 'Delete failed: ' . json_encode($del['code'] ?? ''));
            }

            $del = $this->request('DELETE', "api/v1/checklists/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }

        $this->request('DELETE', "api/v1/tasks/$taskPid");
    }

    private function testWorklogs(): void
    {
        $section = 'Worklogs';
        $tRes = $this->request('POST', 'api/v1/tasks', ['title' => 'Worklog Test ' . time(), 'status' => 'new']);
        $taskPid = $this->extractPid($tRes, 'task');
        if (!$taskPid) { $this->assert("$section: setup", false, 'Need task'); return; }

        $created = $this->request('POST', 'api/v1/worklogs', [
            'task_public_id' => $taskPid,
            'minutes_spent' => 60,
            'note' => 'Test worklog',
        ]);
        $pid = $this->extractPid($created, 'worklog');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/worklogs/$pid", ['minutes_spent' => 90, 'note' => 'Updated worklog']);
            $this->assert("$section: update", $this->extractField($upd, 'worklog', 'minutes_spent') == 90, 'Minutes mismatch');

            $del = $this->request('DELETE', "api/v1/worklogs/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }

        $this->request('DELETE', "api/v1/tasks/$taskPid");
    }

    private function testPriorities(): void
    {
        $section = 'Priorities';
        $res = $this->request('GET', 'api/v1/priorities');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');
    }

    private function testStatuses(): void
    {
        $section = 'Statuses';
        // The statuses list endpoint doesn't take ?scope as query param in the route
        $res = $this->request('GET', 'api/v1/statuses');
        $this->assert("$section: list", ($res['success'] ?? false) === true && isset($res['data']['items']), 'Expected items, got: ' . ($res['code'] ?? ''));
    }

    private function testWebhooks(): void
    {
        $section = 'Webhooks';
        $res = $this->request('GET', 'api/v1/webhooks');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/webhooks', [
            'title' => 'Test Webhook ' . time(),
            'endpoint' => 'https://example.com/hook',
            'secret' => 'test-secret-123',
            'events' => ['task.updated'],
            'is_active' => 1,
        ]);
        $pid = $this->extractPid($created, 'webhook');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/webhooks/$pid", ['title' => 'Updated Webhook']);
            $this->assert("$section: update", $this->extractField($upd, 'webhook', 'title') === 'Updated Webhook', 'Title mismatch');

            $del = $this->request('DELETE', "api/v1/webhooks/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed');
        }
    }

    private function testApiClients(): void
    {
        $section = 'API Clients';
        $res = $this->request('GET', 'api/v1/api-clients');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/api-clients', [
            'title' => 'Test API Client ' . time(),
            'scopes' => ['read:tasks'],
            'is_active' => 1,
        ]);
        $pid = $this->extractPid($created, 'api_client');
        $this->assert("$section: create", $pid !== '', 'Expected public_id');

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/api-clients/$pid", ['title' => 'Updated API Client']);
            $this->assert("$section: update", $this->extractField($upd, 'api_client', 'title') === 'Updated API Client', 'Title mismatch');

            $keyRes = $this->request('POST', "api/v1/api-clients/$pid/keys", [
                'scopes' => ['read:tasks'],
                'expires_at' => '2027-12-31 23:59:59',
            ]);
            // Keys return data.api_key or data.key
            $keyPid = (string)($keyRes['data']['api_key']['public_id'] ?? $keyRes['data']['key']['public_id'] ?? $keyRes['data']['public_id'] ?? '');
            $this->assert("$section: create key", $keyPid !== '', 'Expected key, got: ' . json_encode($keyRes['code'] ?? ''));

            // Must revoke keys before deleting client
            if ($keyPid) {
                $this->request('DELETE', "api/v1/api-keys/$keyPid/revoke");
            }

            $del = $this->request('DELETE', "api/v1/api-clients/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed: ' . json_encode($del['code'] ?? ''));
        }
    }

    private function testIdeas(): void
    {
        $section = 'Ideas';
        $res = $this->request('GET', 'api/v1/ideas');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');

        $created = $this->request('POST', 'api/v1/ideas', [
            'title' => 'Test Idea ' . time(),
            'description' => 'A test idea for live testing',
            'category' => 'improvement',
        ]);
        // Ideas return data.public_id directly
        $pid = (string)($created['data']['public_id'] ?? $created['data']['idea']['public_id'] ?? '');
        $this->assert("$section: create", $pid !== '', 'Expected public_id, got: ' . json_encode($created['code'] ?? ''));

        if ($pid) {
            $upd = $this->request('PATCH', "api/v1/ideas/$pid", ['title' => 'Updated Idea']);
            $this->assert("$section: update", ($upd['success'] ?? false) === true, 'Update failed: ' . json_encode($upd['code'] ?? ''));

            $read = $this->request('GET', "api/v1/ideas/$pid");
            $this->assert("$section: read", ($read['data']['idea']['public_id'] ?? $read['data']['public_id'] ?? '') === $pid, 'Read mismatch');

            $del = $this->request('DELETE', "api/v1/ideas/$pid");
            $this->assert("$section: delete", $del['success'] ?? false, 'Delete failed: ' . json_encode($del['code'] ?? ''));
        }
    }

    private function testSettings(): void
    {
        $section = 'Settings';
        $res = $this->request('GET', 'api/v1/settings');
        $this->assert("$section: list", isset($res['data']['items']), 'Expected items');
    }

    private function testSecurity(): void
    {
        $section = 'Security';

        $savedToken = $this->token;
        $this->token = '';
        $noAuth = $this->request('GET', 'api/v1/tasks', [], false);
        $this->assert("$section: no token → 401", ($noAuth['code'] ?? '') === 'UNAUTHORIZED' || ($noAuth['success'] ?? true) === false, 'Expected 401');
        $this->token = $savedToken;

        $badLogin = $this->request('POST', 'api/v1/auth/login', [
            'login' => 'admin',
            'password' => 'wrongpassword',
        ], false);
        $this->assert("$section: wrong password → error", ($badLogin['success'] ?? true) === false, 'Expected failure');

        $xssTitle = '<script>alert("xss")</script> Test ' . time();
        $xssTask = $this->request('POST', 'api/v1/tasks', ['title' => $xssTitle, 'status' => 'new']);
        $xssPid = $this->extractPid($xssTask, 'task');
        $this->assert("$section: XSS task created", $xssPid !== '', 'XSS task creation failed');
        if ($xssPid) {
            $xssRead = $this->request('GET', "api/v1/tasks/$xssPid");
            $returnedTitle = $this->extractField($xssRead, 'task', 'title') ?? '';
            $this->assert("$section: XSS title escaped", !str_contains($returnedTitle, '<script>'), 'Script tag should be sanitized');
            $this->request('DELETE', "api/v1/tasks/$xssPid");
        }

        $sqli = $this->request('GET', "api/v1/tasks?sort=1%20OR%201=1");
        $this->assert("$section: SQLi sort safe", isset($sqli['data']) || ($sqli['success'] ?? false) === false, 'SQLi should not crash');
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
            return ['success' => false, 'error' => 'curl failed', 'http' => $http];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'invalid json', 'http' => $http, 'body' => substr($raw, 0, 200)];
        }
        $decoded['_http'] = $http;

        return $decoded;
    }

    private function printSummary(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "RESULTS: {$this->passed} passed, {$this->failed} failed\n";
        echo str_repeat('=', 60) . "\n";
        if ($this->errors) {
            echo "\nFAILED:\n";
            foreach ($this->errors as $e) {
                echo "$e\n";
            }
        }
        exit($this->failed > 0 ? 1 : 0);
    }
}

$test = new LiveCrudTest();
$test->run();
