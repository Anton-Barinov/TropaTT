<?php
declare(strict_types=1);

/**
 * WorkCycle Integration Test (ТЗ 006)
 *
 * Tests Work Cycles API endpoints via the service layer.
 *
 * Usage:
 *   php api/tests/WorkCycleIntegrationTest.php
 *
 * Environment:
 *   Uses DB_* env vars (defaults from .env or api/config/database.php)
 *
 * Test plan:
 *   - CRUD cycles (create, list, get, update, delete)
 *   - Add/remove tasks (including duplicate and project mismatch)
 *   - Start/complete/archive/reopen cycles
 *   - Summary and transfer unfinished
 *   - Task filter by cycle_public_id
 */

// ── Bootstrap ──
$projectRoot = dirname(__DIR__, 2);
$apiRoot = $projectRoot . '/api';

require_once $apiRoot . '/system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader($apiRoot);
$autoloader->register();

// ── PDO from env ──
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'crm_api';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ── Test helpers ──
$testsRun = 0;
$testsPassed = 0;
$testsFailed = [];

function assertEq(mixed $expected, mixed $actual, string $label): void
{
    global $testsRun, $testsPassed, $testsFailed;
    $testsRun++;
    if ($expected === $actual) {
        $testsPassed++;
        echo "  ✅ {$label}\n";
    } else {
        $msg = "{$label}: expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . ", got " . json_encode($actual, JSON_UNESCAPED_UNICODE);
        $testsFailed[] = $msg;
        echo "  ❌ {$msg}\n";
    }
}

function assertTrue(bool $actual, string $label): void
{
    assertEq(true, $actual, $label);
}

function assertFalse(bool $actual, string $label): void
{
    assertEq(false, $actual, $label);
}

function assertNull(mixed $actual, string $label): void
{
    assertEq(null, $actual, $label);
}

function assertNotNull(mixed $actual, string $label): void
{
    global $testsRun, $testsPassed, $testsFailed;
    $testsRun++;
    if ($actual !== null) {
        $testsPassed++;
        echo "  ✅ {$label}\n";
    } else {
        $msg = "{$label}: expected non-null, got null";
        $testsFailed[] = $msg;
        echo "  ❌ {$msg}\n";
    }
}

function assertArrayHasKey(string $key, array $array, string $label): void
{
    global $testsRun, $testsPassed, $testsFailed;
    $testsRun++;
    if (array_key_exists($key, $array)) {
        $testsPassed++;
        echo "  ✅ {$label}\n";
    } else {
        $msg = "{$label}: expected key '{$key}' not found in array";
        $testsFailed[] = $msg;
        echo "  ❌ {$msg}\n";
    }
}

// ── Create test data ──
echo "\n═══════════════════════════════════════════\n";
echo "  ТЗ 006 — Work Cycles Integration Tests\n";
echo "═══════════════════════════════════════════\n\n";

echo "Creating test project and tasks...\n";

// Find or create test project
$stmt = $pdo->prepare("SELECT id, public_id FROM projects WHERE public_id = 'TEST_WORK_CYCLES_PROJECT' LIMIT 1");
$stmt->execute();
$project = $stmt->fetch();

if ($project) {
    $projectId = (int)$project['id'];
    $projectPublicId = $project['public_id'];
    // Clean up existing test data from previous runs (including orphaned rows)
    $pdo->prepare("DELETE ct FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE wc.project_id = ?")
       ->execute([$projectId]);
    $pdo->prepare("DELETE FROM cycle_snapshots WHERE cycle_id IN (SELECT id FROM work_cycles WHERE project_id = ?)")
       ->execute([$projectId]);
    $pdo->prepare("DELETE FROM work_cycles WHERE project_id = ?")
       ->execute([$projectId]);
    echo "  Cleaned up existing cycles for test project\n";
} else {
    $projectPublicId = 'TEST_WORK_CYCLES_PROJECT';
    $projectId = null; // will be set after insert
}

// Find root user
$stmt = $pdo->prepare("SELECT id, public_id FROM users WHERE is_root = 1 OR login = 'admin' LIMIT 1");
$stmt->execute();
$rootUser = $stmt->fetch();

if (!$rootUser) {
    // Try to find any user
    $stmt = $pdo->prepare("SELECT id, public_id FROM users LIMIT 1");
    $stmt->execute();
    $rootUser = $stmt->fetch();
}

if (!$rootUser) {
    echo "❌ No users found in database. Create a user first.\n";
    exit(1);
}

$actorUserId = (int)$rootUser['id'];
$actorUserPublicId = $rootUser['public_id'];

$actor = [
    'id' => $actorUserId,
    'public_id' => $actorUserPublicId,
    'is_root' => true,
    'login' => 'admin',
    'full_name' => 'Admin',
];

// Create test project if not exists
if ($projectId === null) {
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO projects (public_id, title, description, status_code, created_by_user_id, created_at, updated_at) VALUES (?, 'Work Cycles Test Project', 'Test project for cycles', 'active', ?, ?, ?)")
       ->execute([$projectPublicId, $actorUserId, $now, $now]);
    $projectId = (int)$pdo->lastInsertId();
    echo "  Created test project (id={$projectId})\n";
} else {
    echo "  Found test project (id={$projectId})\n";
}

// Create test tasks — always fresh to avoid stale cycle_tasks from previous runs
$taskPublicIds = [];
for ($i = 1; $i <= 3; $i++) {
    $now = gmdate('Y-m-d H:i:s');
    $pid = 'tsk_' . strtoupper(bin2hex(random_bytes(10)));
    $pdo->prepare("INSERT INTO tasks (public_id, project_id, title, description, status_code, priority_code, creator_user_id, created_at, updated_at) VALUES (?, ?, 'Test Task {$i}', 'Cycle test task {$i}', 'new', 'normal', ?, ?, ?)")
       ->execute([$pid, $projectId, $actorUserId, $now, $now]);
    $taskPublicIds[$i] = $pid;
}

echo "  Test tasks: " . implode(', ', $taskPublicIds) . "\n\n";

// ── Instantiate services ──
echo "Instantiating services...\n";

$taskRepo = new Api\Model\Task\TaskRepository($pdo);
$workCycleRepo = new Api\Model\Cycle\WorkCycleRepository($pdo);
$cycleTaskRepo = new Api\Model\Cycle\CycleTaskRepository($pdo);
$cycleSnapshotRepo = new Api\Model\Cycle\CycleSnapshotRepository($pdo);

// We need minimal ProjectService - create with mock dependencies
$notificationService = null; // Will be resolved from container if needed
$aiSemanticIndexService = null;
$chatService = null;

// Since ProjectService requires many dependencies, let's use the container
require_once $apiRoot . '/config/default.php';

// Build minimal container for services
$container = new Api\System\Library\Container();
$container->set('db.pdo', $pdo);
$container->factory('repository.project', fn() => new Api\Model\Project\ProjectRepository($pdo));
$container->factory('repository.user', fn() => new Api\Model\Common\UserRepository($pdo));
$container->factory('repository.team', fn() => new Api\Model\Team\TeamRepository($pdo));
$container->factory('repository.notification', fn() => new Api\Model\Notification\NotificationRepository($pdo));
$container->factory('repository.task', fn() => $taskRepo);
$container->factory('repository.work_cycle', fn() => $workCycleRepo);
$container->factory('repository.cycle_task', fn() => $cycleTaskRepo);
$container->factory('repository.cycle_snapshot', fn() => $cycleSnapshotRepo);

// Use null for optional services (notifications, chat, AI)

$projectService = new Api\System\Library\Service\ProjectService(
    $container->get('repository.project'),
    $container->get('repository.user'),
    $container->get('repository.team'),
    null, // notification service
    null, // AI semantic index
    null  // chat
);

$taskService = new Api\System\Library\Service\TaskService(
    $taskRepo,
    $projectService,
    $container->get('repository.team'),
    null, // notification service
    null, // AI semantic index
    null  // no task activity
);

$workCycleService = new Api\System\Library\Service\WorkCycleService(
    $workCycleRepo,
    $cycleTaskRepo,
    $cycleSnapshotRepo,
    $taskRepo,
    $taskService,
    $projectService,
    null // no task activity for MVP
);

echo "  Services ready.\n\n";

// ═══════════════════════════════════════════
// TEST SUITE
// ═══════════════════════════════════════════

echo "═══════════════════════════════════════════\n";
echo "  1. CRUD Cycles\n";
echo "═══════════════════════════════════════════\n\n";

// ── 1a. List cycles (empty after cleanup) ──
echo "1a. List cycles (empty after cleanup):\n";
$listResult = $workCycleService->list(['project_public_id' => $projectPublicId], $actor);
assertEq(0, count($listResult['items']), "list returns 0 items after cleanup");

// ── 1b. Create cycle ──
echo "\n1b. Create cycle:\n";
$cycle1 = $workCycleService->create([
    'project_public_id' => $projectPublicId,
    'title' => 'Sprint 2026-W25',
    'description' => 'Two week sprint',
    'goal' => 'Close critical tasks',
    'status' => 'planned',
    'start_at' => '2026-06-15 00:00:00',
    'end_at' => '2026-06-28 23:59:59',
    'timezone' => 'Europe/Amsterdam',
], $actor);
assertTrue(!is_string($cycle1), "create returns array (not error)");
assertArrayHasKey('public_id', $cycle1, "cycle has public_id");
assertEq('Sprint 2026-W25', $cycle1['title'], "cycle title matches");
assertEq('planned', $cycle1['status'], "cycle status is planned");
$cycle1PublicId = $cycle1['public_id'];

// ── 1c. List cycles (1 item) ──
echo "\n1c. List cycles (1 item):\n";
$listResult = $workCycleService->list(['project_public_id' => $projectPublicId], $actor);
assertEq(1, count($listResult['items']), "list returns 1 item after creating 1 cycle");

// ── 1d. Get cycle ──
echo "\n1d. Get cycle:\n";
$cycle = $workCycleService->get($cycle1PublicId, $actor);
assertTrue(!is_string($cycle), "get returns array");
assertEq($cycle1PublicId, $cycle['public_id'], "get returns correct cycle");
assertArrayHasKey('progress_percent', $cycle, "get includes progress_percent");
assertArrayHasKey('time_state', $cycle, "get includes time_state");

// ── 1e. Update cycle ──
echo "\n1e. Update cycle:\n";
$updated = $workCycleService->update($cycle1PublicId, [
    'title' => 'Sprint 2026-W25 (Updated)',
    'description' => 'Updated description',
], $actor);
assertTrue(!is_string($updated), "update returns array");
assertEq('Sprint 2026-W25 (Updated)', $updated['title'], "title updated");

// ── 1f. Validation: empty title ──
echo "\n1f. Validation: empty title:\n";
$result = $workCycleService->create([
    'project_public_id' => $projectPublicId,
    'title' => '   ',
], $actor);
assertEq('CYCLE_TITLE_REQUIRED', $result, "empty title returns CYCLE_TITLE_REQUIRED");

// ── 1g. Validation: invalid date range ──
echo "\n1g. Validation: invalid date range:\n";
$result = $workCycleService->create([
    'project_public_id' => $projectPublicId,
    'title' => 'Bad date range',
    'start_at' => '2026-06-28 00:00:00',
    'end_at' => '2026-06-15 00:00:00',
], $actor);
assertEq('CYCLE_INVALID_DATE_RANGE', $result, "end < start returns error");

// ── 1h. Create cycle with active status ──
echo "\n1h. Create active cycle:\n";
$cycle2 = $workCycleService->create([
    'project_public_id' => $projectPublicId,
    'title' => 'Active Cycle',
    'status' => 'active',
], $actor);
assertTrue(!is_string($cycle2), "active cycle created");
assertEq('active', $cycle2['status'], "status is active");
$cycle2PublicId = $cycle2['public_id'];


echo "\n═══════════════════════════════════════════\n";
echo "  2. Add/Remove Tasks\n";
echo "═══════════════════════════════════════════\n\n";

// ── 2a. Add tasks to cycle ──
echo "2a. Add tasks to cycle:\n";
$addResult = $workCycleService->addTasks($cycle1PublicId, [
    'task_public_ids' => [$taskPublicIds[1], $taskPublicIds[2]],
], $actor);
assertTrue(!is_string($addResult), "addTasks returns array");
assertEq(2, count($addResult['added']), "two tasks added");
assertEq(0, count($addResult['errors']), "no errors");

// ── 2b. List cycle tasks ──
echo "\n2b. List cycle tasks:\n";
$tasksResult = $workCycleService->tasks($cycle1PublicId, [], $actor);
assertTrue(!is_string($tasksResult), "tasks returns array");
assertEq(2, count($tasksResult['items']), "two tasks in cycle");

// ── 2c. Remove task from cycle ──
echo "\n2c. Remove task from cycle:\n";
$removeResult = $workCycleService->removeTask($cycle1PublicId, $taskPublicIds[2], $actor);
assertTrue($removeResult, "task removed successfully");

// Verify removal
$tasksResult = $workCycleService->tasks($cycle1PublicId, [], $actor);
assertEq(1, count($tasksResult['items']), "one task remains after removal");

// ── 2d. Add task back ──
echo "\n2d. Add task back:\n";
$addResult = $workCycleService->addTasks($cycle1PublicId, [
    'task_public_ids' => [$taskPublicIds[2]],
], $actor);
assertTrue(!is_string($addResult), "task re-added");
assertEq(1, count($addResult['added']), "one task re-added");

// ── 2e. Duplicate add protection ──
echo "\n2e. Duplicate add protection:\n";
$addResult = $workCycleService->addTasks($cycle1PublicId, [
    'task_public_ids' => [$taskPublicIds[1]],
], $actor);
assertTrue(is_array($addResult), "duplicate returns array");
// When all tasks fail, addTasks returns the errors array directly (not wrapped in ['added'=>..., 'errors'=>...])
assertTrue(!isset($addResult['added']), "addTasks returns errors directly when no tasks added");
assertEq(1, count($addResult), "one error for duplicate");
assertEq('CYCLE_TASK_ALREADY_IN_ACTIVE_CYCLE', ($addResult[0]['error'] ?? ''), "error is ALREADY_IN_ACTIVE_CYCLE");

// ── 2f. Project mismatch ──
echo "\n2f. Project mismatch:\n";
// Create a task in a different project (no project = allowed)
$taskPublicIdNoProject = 'tsk_NOPROJECT_' . bin2hex(random_bytes(6));
$now = gmdate('Y-m-d H:i:s');
$pdo->prepare("INSERT INTO tasks (public_id, project_id, title, status_code, priority_code, creator_user_id, created_at, updated_at) VALUES (?, NULL, 'No Project Task', 'new', 'normal', ?, ?, ?)")
   ->execute([$taskPublicIdNoProject, $actorUserId, $now, $now]);

// Tasks without a project should be addable to any cycle
$addResult = $workCycleService->addTasks($cycle1PublicId, [
    'task_public_ids' => [$taskPublicIdNoProject],
], $actor);
assertTrue(!is_string($addResult), "no-project task addable");
// Since task has project_id=0 and cycle has project_id > 0, project_id check passes
// The code checks: $taskProjectId > 0 && $taskProjectId !== $cycleProjectId
// With $taskProjectId = 0, it doesn't enter the check

// ── 2g. Task already in active cycle ──
echo "\n2g. Task already in active cycle:\n";
// Task 1 is already in cycle1 (active/planned). Try adding to cycle2 (active).
$addResult = $workCycleService->addTasks($cycle2PublicId, [
    'task_public_ids' => [$taskPublicIds[1]],
], $actor);
assertTrue(is_array($addResult), "add returns array");
// When all tasks fail, addTasks returns the errors array directly
assertTrue(!isset($addResult['added']), "addTasks returns errors directly");
assertEq(1, count($addResult), "one error");
assertEq('CYCLE_TASK_ALREADY_IN_ACTIVE_CYCLE', ($addResult[0]['error'] ?? ''), "error is CYCLE_TASK_ALREADY_IN_ACTIVE_CYCLE");


echo "\n═══════════════════════════════════════════\n";
echo "  3. Start / Complete / Archive / Reopen\n";
echo "═══════════════════════════════════════════\n\n";

// ── 3a. Start cycle (planned → active) ──
echo "3a. Start cycle:\n";
$started = $workCycleService->start($cycle1PublicId, [], $actor);
assertTrue(!is_string($started), "start returns array");
assertEq('active', $started['status'], "status changed to active");

// ── 3b. Start already active cycle (should fail) ──
echo "\n3b. Start already active cycle:\n";
$result = $workCycleService->start($cycle1PublicId, [], $actor);
assertEq('CYCLE_INVALID_STATUS_TRANSITION', $result, "cannot start active cycle");

// ── 3c. Complete cycle ──
echo "\n3c. Complete cycle:\n";
$completed = $workCycleService->complete($cycle1PublicId, [
    'unfinished_action' => 'leave',
], $actor);
assertTrue(!is_string($completed), "complete returns array");
assertEq('completed', $completed['status'], "status changed to completed");
assertArrayHasKey('completed_at', $completed, "completed_at is set");

// ── 3d. Complete already completed cycle (should fail) ──
echo "\n3d. Complete already completed cycle:\n";
$result = $workCycleService->complete($cycle1PublicId, ['unfinished_action' => 'leave'], $actor);
assertEq('CYCLE_INVALID_STATUS_TRANSITION', $result, "cannot complete completed cycle");

// ── 3e. Archive cycle ──
echo "\n3e. Archive completed cycle:\n";
$archived = $workCycleService->archive($cycle1PublicId, ['source_type' => 'test'], $actor);
assertTrue($archived, "archive returns true");

// Verify archived
$cycle = $workCycleService->get($cycle1PublicId, $actor);
assertTrue(!is_string($cycle), "can still get archived cycle");
assertEq('completed', $cycle['status'], "status is still completed (archive only sets archived_at)");
assertArrayHasKey('archived_at', $cycle, "archived_at is set");

// ── 3f. Reopen cycle ──
echo "\n3f. Reopen cycle:\n";
$reopened = $workCycleService->reopen($cycle1PublicId, ['source_type' => 'test'], $actor);
assertTrue(!is_string($reopened), "reopen returns array");
assertEq('active', $reopened['status'], "status changed back to active");

// ── 3g. Soft delete cycle ──
echo "\n3g. Soft delete cycle:\n";
$deleted = $workCycleService->delete($cycle1PublicId, $actor);
assertTrue($deleted, "delete returns true");

// Verify deleted
$cycle = $workCycleService->get($cycle1PublicId, $actor);
assertEq('CYCLE_NOT_FOUND', $cycle, "deleted cycle returns CYCLE_NOT_FOUND");


echo "\n═══════════════════════════════════════════\n";
echo "  4. Summary\n";
echo "═══════════════════════════════════════════\n\n";

// Create a new cycle with tasks for summary testing
echo "4a. Create cycle for summary:\n";
$cycle3 = $workCycleService->create([
    'project_public_id' => $projectPublicId,
    'title' => 'Summary Cycle',
    'status' => 'active',
], $actor);
assertTrue(!is_string($cycle3), "cycle created");
$cycle3PublicId = $cycle3['public_id'];

// Add tasks
$workCycleService->addTasks($cycle3PublicId, [
    'task_public_ids' => [$taskPublicIds[1], $taskPublicIds[2], $taskPublicIds[3]],
], $actor);

// ── 4b. Get summary ──
echo "\n4b. Get summary:\n";
$summaryResult = $workCycleService->summary($cycle3PublicId, $actor);
assertTrue(!is_string($summaryResult), "summary returns array");
assertArrayHasKey('summary', $summaryResult, "summary has summary key");
$summary = $summaryResult['summary'];
assertEq(3, $summary['total_tasks'], "total tasks = 3");
assertEq(0, $summary['completed_tasks'], "completed tasks = 0");
assertEq(3, $summary['open_tasks'], "open tasks = 3");
assertEq(0, $summary['progress_percent'], "progress = 0%");
assertArrayHasKey('by_status', $summary, "summary has by_status");
assertArrayHasKey('by_priority', $summary, "summary has by_priority");
assertArrayHasKey('by_assignee', $summary, "summary has by_assignee");

// Mark one task as done
$pdo->prepare("UPDATE tasks SET status_code = 'done' WHERE public_id = ?")
   ->execute([$taskPublicIds[1]]);

$summaryResult = $workCycleService->summary($cycle3PublicId, $actor);
$summary = $summaryResult['summary'];
assertEq(1, $summary['completed_tasks'], "completed tasks = 1 (after status change)");
assertEq(2, $summary['open_tasks'], "open tasks = 2");
assertTrue($summary['progress_percent'] > 0, "progress > 0%");


echo "\n═══════════════════════════════════════════\n";
echo "  5. Transfer Unfinished Tasks\n";
echo "═══════════════════════════════════════════\n\n";

// ── 5a. Create target cycle ──
echo "5a. Create target cycle:\n";
$cycle4 = $workCycleService->create([
    'project_public_id' => $projectPublicId,
    'title' => 'Target Cycle',
    'status' => 'active',
], $actor);
assertTrue(!is_string($cycle4), "target cycle created");
$cycle4PublicId = $cycle4['public_id'];

// ── 5b. Transfer unfinished from cycle3 to cycle4 ──
echo "\n5b. Transfer unfinished tasks:\n";
// Task 1 is 'done', tasks 2 and 3 are 'new' (unfinished)
$transferResult = $workCycleService->transferUnfinished($cycle3PublicId, [
    'target_cycle_public_id' => $cycle4PublicId,
], $actor);
assertTrue(!is_string($transferResult), "transfer returns array");
assertEq(2, $transferResult['count'], "2 unfinished tasks transferred");
assertTrue(in_array($taskPublicIds[2], $transferResult['transferred']), "task 2 transferred");
assertTrue(in_array($taskPublicIds[3], $transferResult['transferred']), "task 3 transferred");

// Verify: task 1 (done) should NOT be in target cycle
$targetTasks = $workCycleService->tasks($cycle4PublicId, [], $actor);
$targetPids = array_map(fn(array $t): string => $t['task_public_id'], $targetTasks['items']);
assertTrue(!in_array($taskPublicIds[1], $targetPids), "done task not in target cycle");
assertTrue(in_array($taskPublicIds[2], $targetPids), "unfinished task 2 in target cycle");

// ── 5c. Transfer validation: missing target ──
echo "\n5c. Transfer validation: missing target:\n";
$result = $workCycleService->transferUnfinished($cycle3PublicId, [], $actor);
assertEq('CYCLE_TARGET_CYCLE_REQUIRED', $result, "missing target returns error");

// ── 5d. Transfer validation: invalid target ──
echo "\n5d. Transfer validation: invalid target:\n";
$result = $workCycleService->transferUnfinished($cycle3PublicId, [
    'target_cycle_public_id' => 'NONEXISTENT',
], $actor);
assertEq('CYCLE_TARGET_CYCLE_NOT_FOUND', $result, "invalid target returns error");


echo "\n═══════════════════════════════════════════\n";
echo "  6. Task Filter by cycle_public_id\n";
echo "═══════════════════════════════════════════\n\n";

// ── 6a. Filter tasks by cycle_public_id ──
echo "6a. Filter tasks by cycle_public_id:\n";
$filteredTasks = $taskRepo->list([
    'cycle_public_id' => $cycle4PublicId,
    'limit' => 10,
], $actorUserId, true);
assertTrue(($filteredTasks['total'] ?? 0) >= 2, "at least 2 tasks in target cycle");

// ── 6b. Verify cycle info in task response ──
echo "\n6b. Verify cycle info in task detail:\n";
$taskDetail = $taskRepo->findByPublicId($taskPublicIds[2]);
if ($taskDetail) {
    // The task should have cycle_public_id field from the subquery
    assertArrayHasKey('cycle_public_id', $taskDetail, "task detail has cycle_public_id");
} else {
    echo "  ⚠️ Task detail not available\n";
}


echo "\n═══════════════════════════════════════════\n";
echo "  7. Row Version Conflict\n";
echo "═══════════════════════════════════════════\n\n";

// ── 7a. Update with wrong row_version ──
echo "7a. Update with wrong row_version:\n";
$result = $workCycleService->update($cycle2PublicId, [
    'title' => 'Should fail',
    'row_version' => 999,
], $actor);
assertEq('ROW_VERSION_CONFLICT', $result, "wrong row_version returns conflict");

// ── 7b. Update with correct row_version ──
echo "\n7b. Update with correct row_version:\n";
$cycle2 = $workCycleService->get($cycle2PublicId, $actor);
assertTrue(!is_string($cycle2), "get cycle2 works");
$currentVersion = (int)($cycle2['row_version'] ?? 1);
$result = $workCycleService->update($cycle2PublicId, [
    'title' => 'Valid update',
    'row_version' => $currentVersion,
], $actor);
assertTrue(!is_string($result), "update with correct version succeeds");


echo "\n═══════════════════════════════════════════\n";
echo "  RESULTS\n";
echo "═══════════════════════════════════════════\n\n";

$totalTests = $testsRun;
$passed = $testsPassed;
$failed = count($testsFailed);

echo "Tests run:  {$totalTests}\n";
echo "Passed:     {$passed}\n";
echo "Failed:     {$failed}\n\n";

if ($failed > 0) {
    echo "Failed tests:\n";
    foreach ($testsFailed as $f) {
        echo "  - {$f}\n";
    }
    echo "\n";
}

// ── Clean up test data ──
echo "Cleaning up test data...\n";

// Soft-delete test cycles
foreach ([$cycle1PublicId, $cycle2PublicId, $cycle3PublicId, $cycle4PublicId] as $cp) {
    if ($cp) {
        try {
            $workCycleRepo->softDeleteByPublicId($cp, gmdate('Y-m-d H:i:s'));
        } catch (\Throwable $e) { error_log('[WorkCycleIntegrationTest] ' . $e->getMessage()); }
    }
}

// Hard-delete all cycle_tasks for the project
if ($projectId) {
    try {
        $pdo->prepare("DELETE ct FROM cycle_tasks ct INNER JOIN work_cycles wc ON wc.id = ct.cycle_id WHERE wc.project_id = ?")
           ->execute([$projectId]);
    } catch (\Throwable $e) { error_log('[WorkCycleIntegrationTest] ' . $e->getMessage()); }
}

// Delete test tasks
foreach ($taskPublicIds as $tp) {
    try {
        $pdo->prepare("DELETE FROM tasks WHERE public_id = ?")->execute([$tp]);
    } catch (\Throwable $e) { error_log('[WorkCycleIntegrationTest] ' . $e->getMessage()); }
}

if ($taskPublicIdNoProject !== '') {
    try {
        $pdo->prepare("DELETE FROM tasks WHERE public_id = ?")->execute([$taskPublicIdNoProject]);
    } catch (\Throwable $e) { error_log('[WorkCycleIntegrationTest] ' . $e->getMessage()); }
}

echo "  Done.\n\n";

exit($failed > 0 ? 1 : 0);
