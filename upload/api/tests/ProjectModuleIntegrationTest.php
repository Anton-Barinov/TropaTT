<?php
declare(strict_types=1);

namespace {
    use Api\Model\Project\ProjectModuleRepository;
    use Api\Model\Project\ProjectModuleTaskRepository;
    use Api\Model\Project\ProjectModuleMemberRepository;
    use Api\Model\Project\ProjectModuleLinkRepository;
    use Api\Model\Project\ProjectRepository;
    use Api\Model\Common\UserRepository;
    use Api\Model\Team\TeamRepository;
    use Api\Model\Task\TaskRepository;
    use Api\System\Library\Service\ProjectModuleService;
    use Api\System\Library\Service\ProjectService;
    use Api\System\Library\Service\TaskService;

    require_once __DIR__ . '/../system/library/support/Autoloader.php';

    $loader = new \Api\System\Library\Support\Autoloader('api');
    $loader->register();

    $pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=crm_api;charset=utf8mb4', 'root', '', [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]);

    $pass = 0;
    $fail = 0;

    function assertEq($expected, $actual, string $msg): void {
        global $pass, $fail;
        if ($expected === $actual) {
            $pass++;
        } else {
            $fail++;
            echo "  FAIL: {$msg}\n";
            echo "    Expected: " . var_export($expected, true) . "\n";
            echo "    Actual:   " . var_export($actual, true) . "\n";
        }
    }

    function assertNotNull($value, string $msg): void {
        global $pass, $fail;
        if ($value !== null) {
            $pass++;
        } else {
            $fail++;
            echo "  FAIL: {$msg} (expected not null)\n";
        }
    }

    function assertTrue($value, string $msg): void {
        global $pass, $fail;
        if ($value) {
            $pass++;
        } else {
            $fail++;
            echo "  FAIL: {$msg} (expected true)\n";
        }
    }

    echo "=== Project Module Integration Tests ===\n\n";

    // 0. Aggressive cleanup of ALL test data
    echo "--- 0. Cleanup test data ---\n";
    $pdo->exec('DELETE FROM project_module_links');
    $pdo->exec('DELETE FROM project_module_members');
    $pdo->exec('DELETE FROM project_module_tasks');
    $pdo->exec('DELETE FROM project_modules');
    $pdo->exec("DELETE FROM projects WHERE public_id = 'test_pm_project'");
    $pdo->exec("DELETE FROM users WHERE public_id = 'test_pm_user'");
    echo "   Cleanup completed\n";

    // 1. Setup: create test user
    echo "\n--- 1. Setup: create test user ---\n";
    $pdo->exec("INSERT INTO users (public_id, login, full_name, email, created_at, updated_at) 
                VALUES ('test_pm_user', 'test_pm', 'Test PM User', 'test_pm@test.com', NOW(), NOW())");
    $stmt = $pdo->prepare('SELECT id, public_id FROM users WHERE public_id = :pid LIMIT 1');
    $stmt->execute(['pid' => 'test_pm_user']);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
    assertNotNull($user, 'Test user created');
    $userId = (int)$user['id'];
    echo "   User ID: {$userId}\n";

    // 2. Setup: create test project
    echo "\n--- 2. Setup: create test project ---\n";
    $pdo->exec("INSERT INTO projects (public_id, title, status_code, priority_code, created_by_user_id, created_at, updated_at, row_version) 
                VALUES ('test_pm_project', 'Test PM Project', 'active', 'normal', {$userId}, NOW(), NOW(), 1)");
    $stmt = $pdo->prepare('SELECT id, public_id FROM projects WHERE public_id = :pid LIMIT 1');
    $stmt->execute(['pid' => 'test_pm_project']);
    $project = $stmt->fetch(\PDO::FETCH_ASSOC);
    assertNotNull($project, 'Test project created');
    $projectId = (int)$project['id'];
    echo "   Project ID: {$projectId}\n";

    // 3. Setup: instantiate services
    echo "\n--- 3. Setup: instantiate services ---\n";
    $projectRepo = new ProjectRepository($pdo);
    $userRepo = new UserRepository($pdo);
    $teamRepo = new TeamRepository($pdo);
    $taskRepo = new TaskRepository($pdo);

    $projectService = new ProjectService($projectRepo, $userRepo, $teamRepo);
    $taskService = new TaskService($taskRepo, $projectService, $teamRepo);

    $moduleRepo = new ProjectModuleRepository($pdo);
    $moduleTaskRepo = new ProjectModuleTaskRepository($pdo);
    $moduleMemberRepo = new ProjectModuleMemberRepository($pdo);
    $moduleLinkRepo = new ProjectModuleLinkRepository($pdo);

    $service = new ProjectModuleService(
        $moduleRepo,
        $moduleTaskRepo,
        $moduleMemberRepo,
        $moduleLinkRepo,
        $projectService,
        $taskRepo,
        $taskService
    );

    $actor = ['id' => $userId, 'is_root' => false];
    echo "   Services initialized\n";

    // 4. Create module
    echo "\n--- 4. Create module ---\n";
    $module1 = $service->create([
        'title' => 'Frontend Module',
        'description' => 'All frontend tasks',
        'project_public_id' => 'test_pm_project',
        'status' => 'in_progress',
        'color' => '#3498db',
        'icon' => 'frontend',
        'sort_order' => 100,
    ], $actor);

    assertTrue(!is_string($module1), 'Create module succeeded');
    assertEq('Frontend Module', $module1['title'] ?? '', 'Module title matches');
    assertEq('in_progress', $module1['status'] ?? '', 'Module status matches');
    assertEq('#3498db', $module1['color'] ?? '', 'Module color matches');
    assertEq('frontend', $module1['icon'] ?? '', 'Module icon matches');
    assertEq(100, $module1['sort_order'] ?? 0, 'Module sort_order matches');
    assertEq('test_pm_project', $module1['project_public_id'] ?? '', 'Module project_public_id matches');
    assertNotNull($module1['public_id'] ?? null, 'Module has public_id');
    $module1PublicId = $module1['public_id'];
    echo "   Module public_id: {$module1PublicId}\n";

    // 5. Create another module
    echo "\n--- 5. Create second module ---\n";
    $module2 = $service->create([
        'title' => 'Backend Module',
        'project_public_id' => 'test_pm_project',
        'status' => 'planned',
    ], $actor);

    assertTrue(!is_string($module2), 'Create second module succeeded');
    assertEq('Backend Module', $module2['title'] ?? '', 'Second module title matches');
    $module2PublicId = $module2['public_id'];
    echo "   Module 2 public_id: {$module2PublicId}\n";

    // 6. Get module by public_id
    echo "\n--- 6. Get module ---\n";
    $getResult = $service->get($module1PublicId, $actor);
    assertTrue(!is_string($getResult) && $getResult !== null, 'Get module succeeded');
    assertEq('Frontend Module', $getResult['title'] ?? '', 'Get returns correct title');

    // 7. List modules
    echo "\n--- 7. List modules ---\n";
    $list = $service->list([], $actor);
    assertEq(2, $list['meta']['pagination']['total'] ?? 0, 'List returns 2 modules');
    assertEq(2, count($list['items'] ?? []), 'List items count is 2');

    // 8. List modules filtered by project
    echo "\n--- 8. List modules by project ---\n";
    $filteredList = $service->list(['project_public_id' => 'test_pm_project'], $actor);
    assertEq(2, $filteredList['meta']['pagination']['total'] ?? 0, 'Filtered list returns 2 modules');

    // 9. List modules by status
    echo "\n--- 9. List modules by status ---\n";
    $statusList = $service->list(['status' => 'planned'], $actor);
    assertEq(1, $statusList['meta']['pagination']['total'] ?? 0, 'Status filter returns 1 module');
    assertEq('Backend Module', $statusList['items'][0]['title'] ?? '', 'Status filter matches title');

    // 10. List modules by search
    echo "\n--- 10. List modules by search ---\n";
    $searchList = $service->list(['q' => 'Frontend'], $actor);
    assertEq(1, $searchList['meta']['pagination']['total'] ?? 0, 'Search returns 1 module');

    // 11. Update module
    echo "\n--- 11. Update module ---\n";
    $updateResult = $service->update($module1PublicId, [
        'title' => 'Frontend Module v2',
        'status' => 'completed',
        'color' => '#2ecc71',
        'description' => 'Updated description',
    ], $actor);

    assertTrue(!is_string($updateResult) && $updateResult !== null, 'Update module succeeded');
    assertEq('Frontend Module v2', $updateResult['title'] ?? '', 'Title updated');
    assertEq('completed', $updateResult['status'] ?? '', 'Status updated');
    assertEq('#2ecc71', $updateResult['color'] ?? '', 'Color updated');

    // 12. Get non-existent module (verify cleanup works: no leftover modules)
    echo "\n--- 12. Get non-existent module ---\n";
    $notFound = $service->get('pmod_NONEXISTENT', $actor);
    assertEq('PROJECT_MODULE_NOT_FOUND', $notFound, 'Non-existent module returns NOT_FOUND');

    // 13. Access control: different user cannot access module
    echo "\n--- 13. Access control ---\n";
    $otherActor = ['id' => 99999, 'is_root' => false];
    $otherGet = $service->get($module1PublicId, $otherActor);
    assertEq('PROJECT_MODULE_FORBIDDEN', $otherGet, 'Other user cannot access module via project');

    // 14. Root user can access any module
    echo "\n--- 14. Root user access ---\n";
    $rootActor = ['id' => 99999, 'is_root' => true];
    $rootGet = $service->get($module1PublicId, $rootActor);
    assertTrue(!is_string($rootGet) && $rootGet !== null, 'Root user can access module');
    assertEq('Frontend Module v2', $rootGet['title'] ?? '', 'Root gets correct data');

    // 15. Archive module
    echo "\n--- 15. Archive module ---\n";
    $archiveResult = $service->archive($module2PublicId, $actor);
    assertTrue($archiveResult === true, 'Archive module succeeded');

    // 16. List members (module2 has no members yet)
    echo "\n--- 16. List empty members ---\n";
    $emptyMembers = $service->members($module1PublicId, $actor);
    assertTrue(!is_string($emptyMembers) && $emptyMembers !== null, 'List members succeeded');
    assertEq(0, count($emptyMembers), 'No members yet');

    // 17. Add members
    echo "\n--- 17. Add members ---\n";
    $membersResult = $service->addMembers($module1PublicId, [
        'members' => [
            ['user_public_id' => 'test_pm_user', 'role_code' => 'lead'],
        ],
    ], $actor);

    assertTrue(!is_string($membersResult) && $membersResult !== null, 'Add members succeeded');
    assertEq(1, count($membersResult['added'] ?? []), 'One member added');

    // 18. List members after add
    echo "\n--- 18. List members ---\n";
    $membersList = $service->members($module1PublicId, $actor);
    assertTrue(!is_string($membersList) && $membersList !== null, 'List members succeeded');
    assertEq(1, count($membersList), 'One member in list');
    assertEq('test_pm_user', $membersList[0]['user_public_id'] ?? '', 'Member user matches');

    // 19. Duplicate member error
    echo "\n--- 19. Duplicate member error ---\n";
    $dupResult = $service->addMembers($module1PublicId, [
        'members' => [
            ['user_public_id' => 'test_pm_user', 'role_code' => 'member'],
        ],
    ], $actor);

    assertTrue(!is_string($dupResult) && $dupResult !== null, 'Duplicate member returns result');
    assertEq(0, count($dupResult['added'] ?? []), 'No members added');
    assertEq(1, count($dupResult['errors'] ?? []), 'One duplicate error');

    // 20. Remove member
    echo "\n--- 20. Remove member ---\n";
    $removeMemberResult = $service->removeMember($module1PublicId, 'test_pm_user', $actor);
    assertTrue($removeMemberResult === true, 'Remove member succeeded');

    // 21. List members after remove
    echo "\n--- 21. List members after remove ---\n";
    $afterRemove = $service->members($module1PublicId, $actor);
    assertTrue(!is_string($afterRemove) && $afterRemove !== null, 'List members after remove');
    assertEq(0, count($afterRemove), 'No members after remove');

    // 22. Add links
    echo "\n--- 22. Add links ---\n";
    $linkResult = $service->addLink($module1PublicId, [
        'title' => 'GitHub Repo',
        'url' => 'https://github.com/test/repo',
        'link_type' => 'repository',
        'sort_order' => 10,
    ], $actor);

    assertTrue(!is_string($linkResult) && $linkResult !== null, 'Add link succeeded');
    assertEq('GitHub Repo', $linkResult['title'] ?? '', 'Link title matches');
    assertEq('https://github.com/test/repo', $linkResult['url'] ?? '', 'Link URL matches');
    assertEq('repository', $linkResult['link_type'] ?? '', 'Link type matches');

    $linkResult2 = $service->addLink($module1PublicId, [
        'title' => 'Figma Design',
        'url' => 'https://figma.com/file/test',
        'link_type' => 'design',
    ], $actor);
    assertTrue(!is_string($linkResult2) && $linkResult2 !== null, 'Add second link succeeded');

    // 23. List links
    echo "\n--- 23. List links ---\n";
    $linksList = $service->links($module1PublicId, $actor);
    assertTrue(!is_string($linksList) && $linksList !== null, 'List links succeeded');
    assertEq(2, count($linksList), 'Two links in list');

    // 24. Summary
    echo "\n--- 24. Module summary ---\n";
    $summary = $service->summary($module1PublicId, $actor);
    assertTrue(!is_string($summary) && $summary !== null, 'Summary succeeded');
    assertTrue(isset($summary['summary']), 'Summary has summary key');

    // 25. Validation: title required
    echo "\n--- 25. Validation errors ---\n";
    $emptyTitle = $service->create(['title' => '', 'project_public_id' => 'test_pm_project'], $actor);
    assertEq('PROJECT_MODULE_TITLE_REQUIRED', $emptyTitle, 'Empty title returns error');

    $noProject = $service->create(['title' => 'No Project Module'], $actor);
    assertEq('PROJECT_MODULE_PROJECT_REQUIRED', $noProject, 'No project returns error');

    $badProject = $service->create(['title' => 'Bad Project', 'project_public_id' => 'nonexistent_project'], $actor);
    assertEq('PROJECT_MODULE_PROJECT_NOT_FOUND', $badProject, 'Non-existent project returns error');

    $badStatus = $service->create(['title' => 'Bad Status', 'project_public_id' => 'test_pm_project', 'status' => 'invalid'], $actor);
    assertEq('PROJECT_MODULE_INVALID_STATUS', $badStatus, 'Invalid status returns error');

    $badColor = $service->create(['title' => 'Bad Color', 'project_public_id' => 'test_pm_project', 'color' => 'not-a-color'], $actor);
    assertEq('PROJECT_MODULE_INVALID_COLOR', $badColor, 'Invalid color returns error');

    $badIcon = $service->create(['title' => 'Bad Icon', 'project_public_id' => 'test_pm_project', 'icon' => 'UPPERCASE_ICON!'], $actor);
    assertEq('PROJECT_MODULE_INVALID_ICON', $badIcon, 'Invalid icon returns error');

    // 26. Delete module
    echo "\n--- 26. Delete module ---\n";
    $deleteResult = $service->delete($module2PublicId, $actor);
    assertTrue($deleteResult === true, 'Delete module succeeded');

    $deletedGet = $service->get($module2PublicId, $actor);
    assertEq('PROJECT_MODULE_NOT_FOUND', $deletedGet, 'Deleted module returns NOT_FOUND');

    // 27. Pagination
    echo "\n--- 27. Pagination ---\n";
    $pagedList = $service->list(['limit' => 1, 'page' => 1], $actor);
    assertEq(1, count($pagedList['items'] ?? []), 'Page 1 has 1 item');

    // 28. Row version conflict
    echo "\n--- 28. Row version conflict ---\n";
    $conflict = $service->update($module1PublicId, ['title' => 'Conflict', 'row_version' => 999], $actor);
    assertTrue(is_string($conflict), 'Row version conflict returns string');
    assertEq('ROW_VERSION_CONFLICT', $conflict, 'Row version conflict error');

    // 29. Invalid link URL
    echo "\n--- 29. Invalid link URL ---\n";
    $badLink = $service->addLink($module1PublicId, ['title' => 'Bad', 'url' => 'javascript:alert(1)'], $actor);
    assertEq('PROJECT_MODULE_LINK_INVALID_URL', $badLink, 'Invalid URL returns error');

    $badLink2 = $service->addLink($module1PublicId, ['title' => 'Bad2', 'url' => ''], $actor);
    assertEq('PROJECT_MODULE_LINK_URL_REQUIRED', $badLink2, 'Empty URL returns error');

    $badLink3 = $service->addLink($module1PublicId, ['title' => '', 'url' => 'https://example.com'], $actor);
    assertEq('PROJECT_MODULE_LINK_TITLE_REQUIRED', $badLink3, 'Empty title returns error');

    // Cleanup
    echo "\n--- Cleanup ---\n";
    $pdo->exec('DELETE FROM project_module_links');
    $pdo->exec('DELETE FROM project_module_members');
    $pdo->exec('DELETE FROM project_module_tasks');
    $pdo->exec('DELETE FROM project_modules');
    $pdo->exec("DELETE FROM projects WHERE public_id = 'test_pm_project'");
    $pdo->exec("DELETE FROM users WHERE public_id = 'test_pm_user'");
    echo "   Cleanup completed\n";

    echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
    exit($fail > 0 ? 1 : 0);
}
