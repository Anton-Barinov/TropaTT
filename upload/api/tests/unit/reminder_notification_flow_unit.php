<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/common/UserRepository.php';
require_once __DIR__ . '/../../model/notification/NotificationRepository.php';
require_once __DIR__ . '/../../model/reminder/ReminderRepository.php';
require_once __DIR__ . '/../../model/task/TaskRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/Expression.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/language/LanguageManager.php';
require_once __DIR__ . '/../../system/library/language/TranslatableTrait.php';
require_once __DIR__ . '/../../system/library/service/NotificationService.php';
require_once __DIR__ . '/../../system/library/service/ReminderService.php';
require_once __DIR__ . '/../../system/library/sync/CursorCodec.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';

use Api\Model\Common\UserRepository;
use Api\Model\Notification\NotificationRepository;
use Api\Model\Reminder\ReminderRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\ReminderService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, login TEXT, email TEXT, full_name TEXT, is_active INTEGER DEFAULT 1, is_root INTEGER DEFAULT 0, deleted_at TEXT NULL)');
    $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, description TEXT, status_code TEXT, priority_code TEXT, due_at TEXT NULL, creator_user_id INTEGER, assignee_user_id INTEGER NULL, project_id INTEGER NULL, archived_at TEXT NULL, deleted_at TEXT NULL, row_version INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE reminders (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, task_id INTEGER NULL, remind_at TEXT, status TEXT, created_at TEXT)');
    $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, category TEXT, title TEXT, body TEXT, entity_type TEXT NULL, entity_public_id TEXT NULL, action_code TEXT NULL, actor_user_id INTEGER NULL, actor_public_id TEXT NULL, actor_name TEXT NULL, link TEXT NULL, payload_json TEXT NULL, is_read INTEGER DEFAULT 0, created_at TEXT, read_at TEXT NULL)');

    $insertUser = $pdo->prepare('INSERT INTO users (id, public_id, login, email, full_name, is_active, is_root, deleted_at) VALUES (:id, :public_id, :login, :email, :full_name, 1, 0, NULL)');
    $insertUser->execute(['id' => 1, 'public_id' => 'usr_1', 'login' => 'u1', 'email' => 'u1@local', 'full_name' => 'User One']);

    $notificationService = new NotificationService(
        new NotificationRepository($pdo),
        new UserRepository($pdo),
        new JsonLogger([])
    );

    $reminders = new ReminderService(
        new ReminderRepository($pdo),
        new TaskRepository($pdo),
        new JsonLogger([]),
        $notificationService
    );

    $actor = ['id' => 1, 'public_id' => 'usr_1', 'full_name' => 'User One', 'is_root' => false];
    $past = gmdate('Y-m-d H:i:s', time() - 120);
    $future = gmdate('Y-m-d H:i:s', time() + 7200);

    $created = $reminders->create([
        'remind_at' => $past,
        'status' => 'new',
    ], $actor);
    $publicId = (string)($created['public_id'] ?? '');
    unitAssert($publicId !== '', 'Reminder public_id required');

    $codesStmt = $pdo->query("SELECT action_code FROM notifications ORDER BY id ASC");
    $codes = $codesStmt ? $codesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    unitAssert(in_array('reminder_due', $codes, true), 'Immediate reminder_due notification must be created');

    $reminders->update($publicId, ['remind_at' => $future], $actor);
    $codesStmt = $pdo->query("SELECT action_code FROM notifications ORDER BY id ASC");
    $codes = $codesStmt ? $codesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    unitAssert(in_array('reminder_rescheduled', $codes, true), 'reminder_rescheduled must be created');

    $reminders->update($publicId, ['status' => 'completed'], $actor);
    $codesStmt = $pdo->query("SELECT action_code FROM notifications ORDER BY id ASC");
    $codes = $codesStmt ? $codesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    unitAssert(in_array('reminder_completed', $codes, true), 'reminder_completed must be created');

    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE action_code = 'reminder_due'")->fetchColumn();
    $reminders->dispatchDueNotificationsForUser($actor, gmdate('Y-m-d H:i:s'));
    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE action_code = 'reminder_due'")->fetchColumn();
    unitAssert($afterCount === $beforeCount, 'Due dispatch must not spam duplicates in dedupe window');

    echo "[OK] reminder_notification_flow_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] reminder_notification_flow_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
