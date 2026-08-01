<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/worklog/WorklogRepository.php';
require_once __DIR__ . '/../../model/task/TaskRepository.php';
require_once __DIR__ . '/../../model/team/TeamRepository.php';
require_once __DIR__ . '/../../model/user/UserManagementRepository.php';
require_once __DIR__ . '/../../system/library/sync/CursorCodec.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/WorklogService.php';

use Api\Model\Task\TaskRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\User\UserManagementRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\WorklogService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // MySQL-only JSON_ARRAYAGG used by TaskRepository's tags/modules
    // subqueries — emulate it as a SQLite aggregate (same as sibling tests).
    $pdo->sqliteCreateAggregate(
        'JSON_ARRAYAGG',
        static function (?array $values, int $row, mixed $value): array { $values ??= []; $values[] = $value; return $values; },
        static function (?array $values, int $row): string { return json_encode($values ?? [], JSON_UNESCAPED_UNICODE) ?: '[]'; },
        1
    );

    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        login TEXT,
        full_name TEXT,
        created_by_user_id INTEGER NULL,
        deleted_at TEXT NULL
    )');
    $pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, client_public_id TEXT, task_key_prefix TEXT NULL, task_key_prefix_locked INTEGER NOT NULL DEFAULT 0, manager_user_id INTEGER NULL, team_public_id TEXT NULL, created_by_user_id INTEGER, archived_at TEXT NULL, created_at TEXT, updated_at TEXT, row_version INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, manager_user_id INTEGER, member_user_ids TEXT)');
    $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, task_key TEXT NULL, task_key_prefix TEXT NULL, task_sequence_number INTEGER NULL, project_id INTEGER NULL, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, due_at TEXT NULL, start_at TEXT NULL, end_at TEXT NULL, assignee_user_id INTEGER NULL, creator_user_id INTEGER, archived_at TEXT NULL, deleted_at TEXT NULL, created_at TEXT, updated_at TEXT, row_version INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE task_relations (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_task_id INTEGER, child_task_id INTEGER, relation_type TEXT, sort_order INTEGER DEFAULT 0, created_at TEXT)');
    $pdo->exec('CREATE TABLE entity_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, entity_public_id TEXT, tag_id INTEGER, created_at TEXT)');
    $pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, code TEXT, title TEXT, color TEXT)');
    $pdo->exec('CREATE TABLE task_dependencies (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, depends_on_task_id INTEGER)');
    $pdo->exec('CREATE TABLE work_cycles (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, status TEXT, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE cycle_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, cycle_id INTEGER, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE project_module_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, module_id INTEGER, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE project_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT NOT NULL DEFAULT \'\', status TEXT NOT NULL DEFAULT \'planned\', deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE knowledge_entity_links (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_public_id TEXT, page_id INTEGER)');
    $pdo->exec('CREATE TABLE work_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        user_id INTEGER,
        task_id INTEGER NULL,
        minutes_spent INTEGER,
        note TEXT,
        logged_at TEXT,
        created_at TEXT
    )');

    $pdo->exec("INSERT INTO users (id, public_id, login, full_name) VALUES (1, 'usr_owner', 'owner', 'Owner')");
    $pdo->exec("INSERT INTO users (id, public_id, login, full_name) VALUES (2, 'usr_other', 'other', 'Other')");
    $pdo->exec("INSERT INTO projects (id, public_id, title, created_by_user_id, manager_user_id, archived_at) VALUES (1, 'prj_unit', 'Unit Project', 1, 1, NULL)");
    $pdo->exec("INSERT INTO tasks (id, public_id, project_id, title, creator_user_id, assignee_user_id, deleted_at, archived_at, updated_at) VALUES (1, 'tsk_unit', 1, 'Unit Task', 1, 1, NULL, NULL, '2026-01-01 00:00:00')");

    $worklogs = new WorklogRepository($pdo);
    $tasks = new TaskRepository($pdo);
    $userManagement = new UserManagementRepository($pdo);
    $teamRepo = new TeamRepository($pdo);
    $logger = new JsonLogger([]);
    $service = new WorklogService($worklogs, $tasks, $userManagement, $teamRepo, $logger);

    $owner = ['id' => 1, 'public_id' => 'usr_owner', 'is_root' => false];
    $other = ['id' => 2, 'public_id' => 'usr_other', 'is_root' => false];

    $missingTaskCreate = $service->create([
        'task_public_id' => 'tsk_missing',
        'minutes_spent' => 15,
        'note' => 'Missing task',
    ], $owner);
    unitAssert($missingTaskCreate === 'TASK_NOT_FOUND', 'Create with missing task must return TASK_NOT_FOUND');

    $created = $service->create([
        'task_public_id' => 'tsk_unit',
        'minutes_spent' => 25,
        'note' => 'Unit worklog',
    ], $owner);
    unitAssert(is_array($created), 'Create must return worklog payload');
    $worklogPublicId = (string)($created['public_id'] ?? '');
    unitAssert($worklogPublicId !== '', 'Created worklog must have public_id');

    $forbiddenGet = $service->get($worklogPublicId, $other);
    unitAssert($forbiddenGet === null, 'Other actor must not access чужой worklog');

    $forbiddenUpdate = $service->update($worklogPublicId, ['minutes_spent' => 40], $other);
    unitAssert($forbiddenUpdate === 'FORBIDDEN', 'Other actor update must return FORBIDDEN');

    $updateMissingTask = $service->update($worklogPublicId, ['task_public_id' => 'tsk_missing'], $owner);
    unitAssert($updateMissingTask === 'TASK_NOT_FOUND', 'Update with missing task must return TASK_NOT_FOUND');

    $forbiddenDelete = $service->delete($worklogPublicId, $other);
    unitAssert($forbiddenDelete === 'FORBIDDEN', 'Other actor delete must return FORBIDDEN');

    $deleted = $service->delete($worklogPublicId, $owner);
    unitAssert($deleted === true, 'Owner delete must return true');
    $deletedAgain = $service->delete($worklogPublicId, $owner);
    unitAssert($deletedAgain === false, 'Delete missing worklog must return false');

    echo "[OK] worklog_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] worklog_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
