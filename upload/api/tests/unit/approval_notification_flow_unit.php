<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/common/UserRepository.php';
require_once __DIR__ . '/../../model/approval/ApprovalRepository.php';
require_once __DIR__ . '/../../model/notification/NotificationRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/language/LanguageManager.php';
require_once __DIR__ . '/../../system/library/language/TranslatableTrait.php';
require_once __DIR__ . '/../../system/library/service/ApprovalService.php';
require_once __DIR__ . '/../../system/library/service/NotificationService.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';

use Api\Model\Approval\ApprovalRepository;
use Api\Model\Common\UserRepository;
use Api\Model\Notification\NotificationRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\ApprovalService;
use Api\System\Library\Service\NotificationService;

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
    $pdo->exec('CREATE TABLE approval_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, entity_type TEXT, entity_public_id TEXT, title TEXT, comment TEXT, requester_user_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE approval_steps (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, request_id INTEGER, reviewer_user_id INTEGER, status TEXT, comment TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, category TEXT, title TEXT, body TEXT, entity_type TEXT NULL, entity_public_id TEXT NULL, action_code TEXT NULL, actor_user_id INTEGER NULL, actor_public_id TEXT NULL, actor_name TEXT NULL, link TEXT NULL, payload_json TEXT NULL, is_read INTEGER DEFAULT 0, created_at TEXT, read_at TEXT NULL)');

    $insertUser = $pdo->prepare('INSERT INTO users (id, public_id, login, email, full_name, is_active, is_root, deleted_at) VALUES (:id, :public_id, :login, :email, :full_name, 1, 0, NULL)');
    $insertUser->execute(['id' => 1, 'public_id' => 'usr_req', 'login' => 'requester', 'email' => 'req@local', 'full_name' => 'Requester']);
    $insertUser->execute(['id' => 2, 'public_id' => 'usr_rev', 'login' => 'reviewer', 'email' => 'rev@local', 'full_name' => 'Reviewer']);

    $notifications = new NotificationService(
        new NotificationRepository($pdo),
        new UserRepository($pdo),
        new JsonLogger([])
    );
    $service = new ApprovalService(
        new ApprovalRepository($pdo),
        new UserRepository($pdo),
        new JsonLogger([]),
        $notifications
    );

    $requester = ['id' => 1, 'public_id' => 'usr_req', 'is_root' => false];
    $reviewer = ['id' => 2, 'public_id' => 'usr_rev', 'is_root' => false];

    $created = $service->create([
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_unit',
        'reviewer_public_ids' => ['usr_rev'],
        'comment' => 'Please review',
    ], $requester);
    unitAssert(($created['ok'] ?? false) === true, 'Approval create must succeed');

    $codesStmt = $pdo->query("SELECT action_code FROM notifications ORDER BY id ASC");
    $codes = $codesStmt ? $codesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    unitAssert(in_array('approval_requested', $codes, true), 'approval_requested notification must be created');

    $approvalPublicId = (string)($created['approval']['public_id'] ?? '');
    $approved = $service->approve($approvalPublicId, ['comment' => 'ok'], $reviewer);
    unitAssert(($approved['ok'] ?? false) === true, 'Approval approve must succeed');

    $codesStmt = $pdo->query("SELECT action_code FROM notifications ORDER BY id ASC");
    $codes = $codesStmt ? $codesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    unitAssert(in_array('approval_step_approved', $codes, true), 'approval_step_approved notification must be created');
    unitAssert(in_array('approval_finalized', $codes, true), 'approval_finalized notification must be created');

    echo "[OK] approval_notification_flow_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] approval_notification_flow_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
