<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/file/FileRepository.php';
require_once __DIR__ . '/../../model/task/TaskRepository.php';
require_once __DIR__ . '/../../model/project/ProjectRepository.php';
require_once __DIR__ . '/../../model/knowledge/KnowledgeRepository.php';
require_once __DIR__ . '/../../model/recycle_bin/RecycleBinRepository.php';
require_once __DIR__ . '/../../system/library/sync/CursorCodec.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/FileService.php';

use Api\Model\File\FileRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Recycle_bin\RecycleBinRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\FileService;

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

    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        full_name TEXT
    )');
    $pdo->exec('CREATE TABLE projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        title TEXT,
        team_public_id TEXT NULL,
        created_by_user_id INTEGER,
        manager_user_id INTEGER,
        archived_at TEXT NULL
    )');
    $pdo->exec('CREATE TABLE teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        title TEXT,
        manager_user_id INTEGER,
        member_user_ids TEXT
    )');
    $pdo->exec('CREATE TABLE task_relations (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_task_id INTEGER, child_task_id INTEGER, relation_type TEXT, sort_order INTEGER DEFAULT 0, created_at TEXT)');
    $pdo->exec('CREATE TABLE entity_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, entity_public_id TEXT, tag_id INTEGER, created_at TEXT)');
    $pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, code TEXT, title TEXT, color TEXT)');
    $pdo->exec('CREATE TABLE task_dependencies (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, depends_on_task_id INTEGER)');
    $pdo->exec('CREATE TABLE work_cycles (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, status TEXT, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE cycle_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, cycle_id INTEGER, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE project_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT NOT NULL DEFAULT \'\', status TEXT NOT NULL DEFAULT \'planned\', deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE project_module_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, module_id INTEGER, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE knowledge_entity_links (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_public_id TEXT, page_id INTEGER)');
    $pdo->exec('CREATE TABLE tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        task_key TEXT NULL,
        task_key_prefix TEXT NULL,
        task_sequence_number INTEGER NULL,
        project_id INTEGER,
        title TEXT,
        creator_user_id INTEGER,
        assignee_user_id INTEGER,
        deleted_at TEXT NULL,
        archived_at TEXT NULL,
        updated_at TEXT
    )');
    $pdo->exec('CREATE TABLE files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        entity_type TEXT,
        entity_public_id TEXT,
        uploader_user_id INTEGER,
        original_name TEXT,
        storage_path TEXT,
        mime_type TEXT,
        size_bytes INTEGER,
        is_deleted INTEGER,
        created_at TEXT,
        deleted_at TEXT NULL
    )');
    $pdo->exec('CREATE TABLE recycle_bin (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        entity_type TEXT,
        entity_public_id TEXT,
        payload TEXT,
        deleted_by_user_id INTEGER,
        deleted_at TEXT,
        restored_at TEXT NULL
    )');

    $files = new FileRepository($pdo);
    $tasks = new TaskRepository($pdo);
    $projects = new ProjectRepository($pdo);
    $recycle = new RecycleBinRepository($pdo);
    $logger = new JsonLogger([]);

    $baseTmp = sys_get_temp_dir() . '/api_unit_file_' . bin2hex(random_bytes(3));
    $uploads = $baseTmp . '/uploads';
    $quarantine = $baseTmp . '/quarantine';
    @mkdir($uploads, 0775, true);
    @mkdir($quarantine, 0775, true);

    $service = new FileService(
        $files,
        $uploads,
        $quarantine,
        4,
        ['exe'],
        ['application/x-msdownload'],
        ['application/x-php', 'application/x-sh', 'application/x-msdownload'],
        $tasks,
        $projects,
        new KnowledgeRepository($pdo),
        $recycle,
        $logger
    );

    $actor = ['id' => 2, 'public_id' => 'usr_actor', 'is_root' => false];

    unitAssert($tasks->findByPublicId('tsk_missing') === null, 'Missing task lookup must return null');

    try {
        $service->create([], [], 2, $actor);
        throw new RuntimeException('FILE_REQUIRED branch was not triggered');
    } catch (RuntimeException $e) {
        unitAssert($e->getMessage() === 'FILE_REQUIRED', 'Expected FILE_REQUIRED');
    }

    try {
        $service->create([
            'name' => 'bad.txt',
            'content_base64' => '$$$not_base64$$$',
            'mime_type' => 'text/plain',
        ], [], 2, $actor);
        throw new RuntimeException('INVALID_BASE64 branch was not triggered');
    } catch (RuntimeException $e) {
        unitAssert($e->getMessage() === 'INVALID_BASE64', 'Expected INVALID_BASE64');
    }

    try {
        $service->create([
            'name' => 'too-large.txt',
            'content_base64' => base64_encode('12345'),
            'mime_type' => 'text/plain',
        ], [], 2, $actor);
        throw new RuntimeException('FILE_TOO_LARGE branch was not triggered');
    } catch (RuntimeException $e) {
        unitAssert($e->getMessage() === 'FILE_TOO_LARGE', 'Expected FILE_TOO_LARGE');
    }

    try {
        $service->create([
            'entity_type' => 'task',
            'entity_public_id' => 'tsk_missing',
            'name' => 'ok.txt',
            'content_base64' => base64_encode('1234'),
            'mime_type' => 'text/plain',
        ], [], 2, $actor);
        throw new RuntimeException('ENTITY_ACCESS_DENIED branch was not triggered');
    } catch (RuntimeException $e) {
        unitAssert($e->getMessage() === 'ENTITY_ACCESS_DENIED', 'Expected ENTITY_ACCESS_DENIED');
    }

    $pathLike = $service->create([
        'name' => '../../secret.txt',
        'content_base64' => base64_encode('1234'),
        'mime_type' => 'text/plain',
    ], [], 2, $actor);
    unitAssert((string)($pathLike['original_name'] ?? '') === 'secret.txt', 'Path-like base64 filename must be normalized to basename');
    $pathLikeStored = $files->findByPublicId((string)$pathLike['public_id']);
    unitAssert(is_array($pathLikeStored), 'Path-like file row must exist');
    // SEC-001: files are stored on disk as <uploads>/<publicId>.bin — the
    // original name never appears in storage_path, and path traversal must be
    // impossible.
    $storedPath = (string)($pathLikeStored['storage_path'] ?? '');
    unitAssert(!str_contains($storedPath, '..'), 'Stored path must not contain path traversal');
    unitAssert(str_ends_with($storedPath, '.bin'), 'Stored path must use random .bin name (no user-controlled extension)');

    $headerLike = $service->create([
        'name' => "bad\r\nX-Evil: 1.txt",
        'content_base64' => base64_encode('1234'),
        'mime_type' => 'text/plain',
    ], [], 2, $actor);
    $headerLikeName = (string)($headerLike['original_name'] ?? '');
    unitAssert(!str_contains($headerLikeName, "\r") && !str_contains($headerLikeName, "\n"), 'Filename must not contain CR/LF');
    unitAssert($headerLikeName === 'badX-Evil: 1.txt', 'Control chars must be stripped from filename');

    $unicode = $service->create([
        'name' => 'файл "Q".txt',
        'content_base64' => base64_encode('1234'),
        'mime_type' => 'text/plain',
    ], [], 2, $actor);
    unitAssert((string)($unicode['original_name'] ?? '') === 'файл "Q".txt', 'Unicode filename must be preserved for API metadata');

    @unlink($uploads);
    @unlink($quarantine);
    @rmdir($uploads);
    @rmdir($quarantine);
    @rmdir($baseTmp);

    echo "[OK] file_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] file_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
