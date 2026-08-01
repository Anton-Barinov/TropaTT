<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/workflow/WorkflowRepository.php';
require_once __DIR__ . '/../../model/user/UserManagementRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/database/builder/Expression.php';
require_once __DIR__ . '/../../system/library/policy/HierarchyPolicy.php';
require_once __DIR__ . '/../../system/library/language/LanguageManager.php';
require_once __DIR__ . '/../../system/library/language/TranslatableTrait.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/WorkflowService.php';

use Api\Model\User\UserManagementRepository;
use Api\Model\Workflow\WorkflowRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Service\WorkflowService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, login TEXT, email TEXT NULL, full_name TEXT NULL, locale TEXT NULL, is_active INTEGER DEFAULT 1, is_root INTEGER DEFAULT 0, created_by_user_id INTEGER NULL, deleted_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
    $pdo->exec('CREATE TABLE automation_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, trigger_code TEXT, action_code TEXT, payload TEXT, is_enabled INTEGER, created_by_user_id INTEGER NULL, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE automation_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, rule_id INTEGER, status TEXT, error TEXT NULL, created_at TEXT)');
    $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, category TEXT, title TEXT, body TEXT, entity_type TEXT NULL, entity_public_id TEXT NULL, action_code TEXT NULL, is_read INTEGER DEFAULT 0, created_at TEXT)');

    $pdo->prepare('INSERT INTO users (id, public_id, login, is_active, is_root, created_by_user_id, created_at, updated_at) VALUES (1, :public_id, :login, 1, 1, NULL, :created_at, :updated_at)')
        ->execute([
            'public_id' => 'usr_root',
            'login' => 'root',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

    $repo = new WorkflowRepository($pdo);
    $userRepo = new UserManagementRepository($pdo);
    $hierarchy = new HierarchyPolicy($userRepo);
    $service = new WorkflowService($repo, $userRepo, $hierarchy, new LanguageManager(__DIR__ . '/../../language'));
    $root = ['id' => 1, 'public_id' => 'usr_root', 'is_root' => true];

    $missing = $service->runTest('wfr_missing', [], $root);
    unitAssert($missing === 'RULE_NOT_FOUND', 'runTest must return RULE_NOT_FOUND for unknown rule');

    $rule = $service->createRule([
        'title' => 'Unit Workflow',
        'trigger_code' => 'task_created',
        'action_code' => 'send_notification',
        'payload' => ['user_ids' => [1], 'title' => 'Unit notification'],
        'is_enabled' => 1,
    ], $root);
    $rulePublicId = (string)($rule['public_id'] ?? '');
    unitAssert($rulePublicId !== '', 'Created workflow rule must have public_id');

    $okRun = $service->runTest($rulePublicId, ['simulate_error' => 0], $root);
    unitAssert(is_array($okRun), 'runTest must return array for existing rule');
    unitAssert((string)($okRun['status'] ?? '') === 'success', 'runTest without simulate_error must be success');

    $service->updateRule($rulePublicId, ['action_code' => 'invalid_action'], $root);
    $failRun = $service->runTest($rulePublicId, [], $root);
    unitAssert(is_array($failRun), 'runTest with an unsupported action must return array');
    unitAssert((string)($failRun['status'] ?? '') === 'failed', 'runTest with an unsupported action must fail');

    $runs = $service->listRuns(['status' => 'failed'], $root);
    $items = (array)($runs['items'] ?? []);
    unitAssert(count($items) >= 1, 'listRuns(status=failed) must return at least one item');

    $updated = $service->updateRule($rulePublicId, ['title' => 'Unit Workflow Updated', 'is_enabled' => 0], $root);
    unitAssert(is_array($updated), 'updateRule must return updated rule');
    unitAssert((string)($updated['title'] ?? '') === 'Unit Workflow Updated', 'Updated workflow title mismatch');
    unitAssert(($updated['is_enabled'] ?? true) === false, 'Updated workflow must be disabled');

    $deleted = $service->deleteRule($rulePublicId, $root);
    unitAssert($deleted === true, 'deleteRule must return true for existing rule');
    $deletedAgain = $service->deleteRule($rulePublicId, $root);
    unitAssert($deletedAgain === false, 'deleteRule must return false for missing rule');

    echo "[OK] workflow_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] workflow_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
