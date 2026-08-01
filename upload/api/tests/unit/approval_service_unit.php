<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/common/UserRepository.php';
require_once __DIR__ . '/../../model/approval/ApprovalRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/ApprovalService.php';

use Api\Model\Approval\ApprovalRepository;
use Api\Model\Common\UserRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\ApprovalService;

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
    $pdo->exec('CREATE TABLE approval_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, entity_type TEXT, entity_public_id TEXT, title TEXT NOT NULL DEFAULT \'\', comment TEXT NOT NULL DEFAULT \'\', requester_user_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE approval_steps (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, request_id INTEGER, reviewer_user_id INTEGER, status TEXT, comment TEXT, created_at TEXT, updated_at TEXT)');

    $insertUser = $pdo->prepare('INSERT INTO users (id, public_id, login, email, full_name, is_active, is_root, deleted_at) VALUES (:id, :public_id, :login, :email, :full_name, :is_active, :is_root, NULL)');
    $insertUser->execute(['id' => 1, 'public_id' => 'usr_req', 'login' => 'requester', 'email' => 'req@local', 'full_name' => 'Requester', 'is_active' => 1, 'is_root' => 0]);
    $insertUser->execute(['id' => 2, 'public_id' => 'usr_rev', 'login' => 'reviewer', 'email' => 'rev@local', 'full_name' => 'Reviewer', 'is_active' => 1, 'is_root' => 0]);
    $insertUser->execute(['id' => 3, 'public_id' => 'usr_inactive', 'login' => 'inactive', 'email' => 'in@local', 'full_name' => 'Inactive', 'is_active' => 0, 'is_root' => 0]);
    $insertUser->execute(['id' => 4, 'public_id' => 'usr_other', 'login' => 'other', 'email' => 'other@local', 'full_name' => 'Other', 'is_active' => 1, 'is_root' => 0]);

    $service = new ApprovalService(
        new ApprovalRepository($pdo),
        new UserRepository($pdo),
        new JsonLogger([])
    );

    $actorRequester = ['id' => 1, 'public_id' => 'usr_req', 'is_root' => false];
    $actorReviewer = ['id' => 2, 'public_id' => 'usr_rev', 'is_root' => false];
    $actorOther = ['id' => 4, 'public_id' => 'usr_other', 'is_root' => false];

    $missingReviewers = $service->create([
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_missing_reviewers',
        'reviewer_public_ids' => [],
    ], $actorRequester);
    unitAssert(($missingReviewers['ok'] ?? true) === false && (string)($missingReviewers['code'] ?? '') === 'APPROVAL_REVIEWERS_REQUIRED', 'Create must fail without reviewers');

    $missingReviewer = $service->create([
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_missing_reviewer',
        'reviewer_public_ids' => ['usr_unknown'],
    ], $actorRequester);
    unitAssert(($missingReviewer['ok'] ?? true) === false && (string)($missingReviewer['code'] ?? '') === 'REVIEWER_NOT_FOUND', 'Create must fail for unknown reviewer');

    $inactiveReviewer = $service->create([
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_inactive_reviewer',
        'reviewer_public_ids' => ['usr_inactive'],
    ], $actorRequester);
    unitAssert(($inactiveReviewer['ok'] ?? true) === false && (string)($inactiveReviewer['code'] ?? '') === 'REVIEWER_INACTIVE', 'Create must fail for inactive reviewer');

    $created = $service->create([
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_ok',
        'reviewer_public_ids' => ['usr_rev'],
        'comment' => 'Please review',
    ], $actorRequester);
    unitAssert(($created['ok'] ?? false) === true, 'Create must succeed for valid reviewer');
    $approvalPublicId = (string)($created['approval']['public_id'] ?? '');
    unitAssert($approvalPublicId !== '', 'Created approval must contain public_id');

    $forbiddenGet = $service->get($approvalPublicId, $actorOther);
    unitAssert(($forbiddenGet['ok'] ?? true) === false && (string)($forbiddenGet['code'] ?? '') === 'FORBIDDEN', 'Unrelated actor must be forbidden');

    $notReviewerApprove = $service->approve($approvalPublicId, ['comment' => 'try'], $actorOther);
    unitAssert(($notReviewerApprove['ok'] ?? true) === false && (string)($notReviewerApprove['code'] ?? '') === 'APPROVAL_REVIEWER_FORBIDDEN', 'Approve must fail for non-reviewer');

    $approved = $service->approve($approvalPublicId, ['comment' => 'ok'], $actorReviewer);
    unitAssert(($approved['ok'] ?? false) === true, 'Reviewer must approve successfully');
    unitAssert((string)($approved['approval']['status'] ?? '') === 'approved', 'Approval status must become approved');

    $approveAgain = $service->approve($approvalPublicId, ['comment' => 'again'], $actorReviewer);
    unitAssert(($approveAgain['ok'] ?? true) === false && (string)($approveAgain['code'] ?? '') === 'APPROVAL_FINALIZED', 'Approve must fail for finalized request');

    echo "[OK] approval_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] approval_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
