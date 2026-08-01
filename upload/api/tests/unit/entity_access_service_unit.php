<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/sync/CursorCodec.php';
require_once __DIR__ . '/../../model/common/UserRepository.php';
require_once __DIR__ . '/../../model/project/ProjectRepository.php';
require_once __DIR__ . '/../../model/team/TeamRepository.php';
require_once __DIR__ . '/../../model/task/TaskRepository.php';
require_once __DIR__ . '/../../model/comment/CommentRepository.php';
require_once __DIR__ . '/../../system/library/service/ProjectService.php';
require_once __DIR__ . '/../../system/library/service/TaskService.php';
require_once __DIR__ . '/../../system/library/service/EntityAccessService.php';

use Api\Model\Comment\CommentRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Service\EntityAccessService;
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

    $pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, client_public_id TEXT, task_key_prefix TEXT NULL, task_key_prefix_locked INTEGER NOT NULL DEFAULT 0, manager_user_id INTEGER NULL, team_public_id TEXT NULL, created_by_user_id INTEGER, archived_at TEXT NULL, created_at TEXT, updated_at TEXT, row_version INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, task_key TEXT NULL, task_key_prefix TEXT NULL, task_sequence_number INTEGER NULL, project_id INTEGER NULL, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, due_at TEXT NULL, start_at TEXT NULL, end_at TEXT NULL, assignee_user_id INTEGER NULL, creator_user_id INTEGER, archived_at TEXT NULL, deleted_at TEXT NULL, created_at TEXT, updated_at TEXT, row_version INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, task_id INTEGER NULL, project_id INTEGER NULL, author_user_id INTEGER NULL, body TEXT, visibility TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, full_name TEXT)');
    $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, manager_user_id INTEGER NULL, member_user_ids TEXT NULL, created_at TEXT, updated_at TEXT)');
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
            'public_id' => 'prj_unit_access',
            'title' => 'Project Access',
            'created_by' => 10,
            'manager' => 20,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

    $pdo->prepare('INSERT INTO tasks (id, public_id, project_id, title, status_code, priority_code, assignee_user_id, creator_user_id, created_at, updated_at, row_version) VALUES (1, :public_id, 1, :title, :status_code, :priority_code, :assignee_user_id, :creator_user_id, :created_at, :updated_at, 1)')
        ->execute([
            'public_id' => 'tsk_unit_access',
            'title' => 'Task Access',
            'status_code' => 'new',
            'priority_code' => 'normal',
            'assignee_user_id' => null,
            'creator_user_id' => 10,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

    $pdo->prepare('INSERT INTO comments (id, public_id, task_id, project_id, author_user_id, body, visibility, created_at, updated_at, deleted_at) VALUES (1, :public_id, 1, 1, 10, :body, :visibility, :created_at, :updated_at, NULL)')
        ->execute([
            'public_id' => 'cmt_unit_access',
            'body' => 'Unit comment',
            'visibility' => 'internal',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

    $teamRepository = new \Api\Model\Team\TeamRepository($pdo);
    $projectService = new ProjectService(new ProjectRepository($pdo), new \Api\Model\Common\UserRepository($pdo), $teamRepository);
    $taskService = new TaskService(new TaskRepository($pdo), $projectService, $teamRepository);
    $comments = new CommentRepository($pdo);
    $access = new EntityAccessService($taskService, $projectService, $comments);

    $actorOwner = ['id' => 10, 'is_root' => false];
    $actorDenied = ['id' => 999, 'is_root' => false];
    $actorRoot = ['id' => 1, 'is_root' => true];

    unitAssert($access->canAccess('task', 'tsk_unit_access', $actorOwner) === true, 'Task owner must have access');
    unitAssert($access->canAccess('task', 'tsk_unit_access', $actorDenied) === false, 'Unrelated actor must not have task access');
    unitAssert($access->canAccess('project', 'prj_unit_access', $actorOwner) === true, 'Project owner must have access');
    unitAssert($access->canAccess('project', 'prj_unit_access', $actorDenied) === false, 'Unrelated actor must not have project access');
    unitAssert($access->canAccess('comment', 'cmt_unit_access', $actorOwner) === true, 'Comment linked to accessible task must be accessible');
    unitAssert($access->canAccess('comment', 'cmt_unit_access', $actorDenied) === false, 'Comment must be inaccessible for unrelated actor');

    unitAssert($access->canAccess('task', '', $actorRoot) === false, 'Empty entity public_id must be denied');
    unitAssert($access->canAccess('unknown', 'id', $actorRoot) === false, 'Unknown entity_type must be denied');

    $pdo->exec("UPDATE comments SET deleted_at = '" . gmdate('Y-m-d H:i:s') . "' WHERE public_id = 'cmt_unit_access'");
    unitAssert($access->canAccess('comment', 'cmt_unit_access', $actorOwner) === false, 'Deleted comment must be inaccessible');

    echo "[OK] entity_access_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] entity_access_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
