<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/database/builder/Expression.php';
require_once __DIR__ . '/../../system/library/sync/CursorCodec.php';
require_once __DIR__ . '/../../model/common/UserRepository.php';
require_once __DIR__ . '/../../model/project/ProjectRepository.php';
require_once __DIR__ . '/../../model/team/TeamRepository.php';
require_once __DIR__ . '/../../model/task/TaskRepository.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/ProjectService.php';
require_once __DIR__ . '/../../system/library/service/TaskService.php';

use Api\Model\Common\UserRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Service\ProjectService;
use Api\System\Library\Service\TaskService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->sqliteCreateAggregate(
        'JSON_ARRAYAGG',
        static function (?array $values, int $row, mixed $value): array { $values ??= []; $values[] = $value; return $values; },
        static function (?array $values, int $row): string { return json_encode($values ?? [], JSON_UNESCAPED_UNICODE) ?: '[]'; },
        1
    );

    $pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, client_public_id TEXT, manager_user_id INTEGER, team_public_id TEXT NULL, created_by_user_id INTEGER, archived_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL, row_version INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, task_key TEXT NULL, task_key_prefix TEXT NULL, task_sequence_number INTEGER NULL, project_id INTEGER NULL, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, due_at TEXT NULL, start_at TEXT NULL, end_at TEXT NULL, assignee_user_id INTEGER NULL, creator_user_id INTEGER, archived_at TEXT NULL, deleted_at TEXT NULL, created_at TEXT, updated_at TEXT, row_version INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE task_status_history (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT, task_id INTEGER, old_status TEXT, new_status TEXT, changed_by_user_id INTEGER, created_at TEXT)');
    $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, manager_user_id INTEGER NULL, member_user_ids TEXT NULL, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, full_name TEXT, login TEXT)');
    $pdo->exec('CREATE TABLE task_relations (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_task_id INTEGER, child_task_id INTEGER, relation_type TEXT, sort_order INTEGER DEFAULT 0, created_at TEXT)');
    $pdo->exec('CREATE TABLE entity_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, entity_public_id TEXT, tag_id INTEGER, created_at TEXT)');
    $pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, code TEXT, title TEXT, color TEXT)');
    $pdo->exec('CREATE TABLE task_dependencies (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, depends_on_task_id INTEGER)');
    $pdo->exec('CREATE TABLE work_cycles (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, status TEXT, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE cycle_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, cycle_id INTEGER, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE project_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT NOT NULL DEFAULT \'\', status TEXT NOT NULL DEFAULT \'planned\', deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE project_module_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, module_id INTEGER, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE knowledge_entity_links (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_public_id TEXT, page_id INTEGER)');

    $pdo->prepare('INSERT INTO projects (id, public_id, title, created_by_user_id, manager_user_id, created_at, updated_at) VALUES (1, :public_id, :title, :created_by, :manager, :created_at, :updated_at)')
        ->execute([
            'public_id' => 'prj_unit',
            'title' => 'Unit Project',
            'created_by' => 10,
            'manager' => 10,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

    $pdo->prepare('INSERT INTO tasks (public_id, project_id, title, description, status_code, priority_code, assignee_user_id, creator_user_id, created_at, updated_at, row_version) VALUES (:public_id, 1, :title, :description, :status_code, :priority_code, :assignee_user_id, :creator_user_id, :created_at, :updated_at, :row_version)')
        ->execute([
            'public_id' => 'tsk_unit_1',
            'title' => 'Unit Task',
            'description' => 'Desc',
            'status_code' => 'new',
            'priority_code' => 'normal',
            'assignee_user_id' => null,
            'creator_user_id' => 10,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'row_version' => 3,
        ]);

    $repo = new TaskRepository($pdo);
    $pdo->exec("INSERT INTO tags (id, public_id, code, title, color) VALUES (1, 'tag_unit', 'unit', 'Unit tag', '#008866')");
    $pdo->exec("INSERT INTO entity_tags (entity_type, entity_public_id, tag_id) VALUES ('task', 'tsk_unit_1', 1)");
    $pdo->exec("INSERT INTO knowledge_entity_links (entity_type, entity_public_id) VALUES ('task', 'tsk_unit_1')");
    $pdo->exec("INSERT INTO work_cycles (id, public_id, title, status) VALUES (1, 'cyc_unit', 'Unit cycle', 'active')");
    $pdo->exec("INSERT INTO cycle_tasks (task_id, cycle_id) VALUES (1, 1)");
    $pdo->exec("INSERT INTO project_modules (id, public_id, title, status) VALUES (1, 'mod_unit', 'Unit module', 'planned')");
    $pdo->exec("INSERT INTO project_module_tasks (task_id, module_id) VALUES (1, 1)");
    $listed = $repo->list(['limit' => 10], 10, true);
    $listedTask = $listed['items'][0] ?? [];
    unitAssert(!array_key_exists('_task_id', $listedTask), 'List response must not expose internal task ids');
    unitAssert((int)($listedTask['knowledge_links_count'] ?? 0) === 1, 'List hydration must include knowledge link count');
    unitAssert((string)($listedTask['cycle_public_id'] ?? '') === 'cyc_unit', 'List hydration must include cycle data');
    unitAssert(str_contains((string)($listedTask['tags'] ?? ''), 'tag_unit'), 'List hydration must include tags');
    unitAssert(str_contains((string)($listedTask['modules'] ?? ''), 'mod_unit'), 'List hydration must include modules');

    $teamRepository = new TeamRepository($pdo);
    $projectService = new ProjectService(new ProjectRepository($pdo), new UserRepository($pdo), $teamRepository);
    $service = new TaskService($repo, $projectService, $teamRepository);

    $forbidden = $service->update('tsk_unit_1', ['title' => 'Denied'], 999, ['id' => 999, 'is_root' => false]);
    unitAssert($forbidden === null, 'Unauthorized actor must not update task');

    $conflict = $service->update('tsk_unit_1', ['row_version' => 2, 'title' => 'Conflict'], 10, ['id' => 10, 'is_root' => false]);
    unitAssert($conflict === 'ROW_VERSION_CONFLICT', 'Stale row_version must return ROW_VERSION_CONFLICT');

    $updated = $service->update('tsk_unit_1', ['row_version' => 3, 'title' => 'Updated Title'], 10, ['id' => 10, 'is_root' => false]);
    unitAssert(is_array($updated), 'Valid row_version update must return updated task');
    unitAssert((string)($updated['title'] ?? '') === 'Updated Title', 'Task title must be updated');
    unitAssert((int)($updated['row_version'] ?? 0) === 4, 'row_version must increment after successful update');

    echo "[OK] task_row_version_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] task_row_version_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
