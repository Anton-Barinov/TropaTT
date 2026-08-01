<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../model/user/UserManagementRepository.php';
require_once __DIR__ . '/../../system/library/policy/HierarchyPolicy.php';

use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT, login TEXT, email TEXT, full_name TEXT, locale TEXT, is_active INTEGER DEFAULT 1, is_root INTEGER DEFAULT 0, cost_rate TEXT, bill_rate TEXT, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT NULL)');

    $insert = $pdo->prepare('INSERT INTO users (id, public_id, created_by_user_id, is_root, deleted_at) VALUES (:id, :public_id, :created_by_user_id, :is_root, NULL)');
    $insert->execute(['id' => 1, 'public_id' => 'usr_root', 'created_by_user_id' => null, 'is_root' => 1]);
    $insert->execute(['id' => 2, 'public_id' => 'usr_parent', 'created_by_user_id' => 1, 'is_root' => 0]);
    $insert->execute(['id' => 3, 'public_id' => 'usr_child', 'created_by_user_id' => 2, 'is_root' => 0]);
    $insert->execute(['id' => 4, 'public_id' => 'usr_other', 'created_by_user_id' => 1, 'is_root' => 0]);

    $repo = new UserManagementRepository($pdo);
    $policy = new HierarchyPolicy($repo);

    unitAssert($policy->canManageUser(['id' => 1, 'is_root' => 1], ['id' => 2, 'is_root' => 0]) === true, 'Root must manage regular user');
    unitAssert($policy->canManageUser(['id' => 2, 'is_root' => 0], ['id' => 1, 'is_root' => 1]) === false, 'Non-root must not manage root');
    unitAssert($policy->canManageUser(['id' => 2, 'is_root' => 0], ['id' => 2, 'is_root' => 0]) === true, 'User must manage self');
    unitAssert($policy->canManageUser(['id' => 3, 'is_root' => 0], ['id' => 2, 'is_root' => 0]) === false, 'Descendant must not manage ancestor');
    unitAssert($policy->canManageUser(['id' => 2, 'is_root' => 0], ['id' => 3, 'is_root' => 0]) === true, 'Ancestor must manage descendant');

    unitAssert($policy->isAncestor(2, 3) === true, 'Parent must be ancestor of child');
    unitAssert($policy->isAncestor(1, 3) === true, 'Root must be ancestor of child');
    unitAssert($policy->isAncestor(3, 2) === false, 'Child must not be ancestor of parent');

    echo "[OK] hierarchy_policy_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] hierarchy_policy_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
