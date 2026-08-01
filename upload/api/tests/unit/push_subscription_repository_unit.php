<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/notification/PushSubscriptionRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';

use Api\Model\Notification\PushSubscriptionRepository;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $repo = new PushSubscriptionRepository($pdo);

    $now = gmdate('Y-m-d H:i:s');
    $repo->create([
        'public_id' => 'psh_repo_unit_1',
        'user_id' => 501,
        'endpoint' => 'https://push.example.local/sub/repo-1',
        'p256dh' => 'repo-p256dh',
        'auth' => 'repo-auth',
        'user_agent' => 'repo-test/1.0',
        'device_label' => 'Repo Device',
        'is_active' => 1,
        'last_seen_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $created = $repo->findByPublicIdForUser('psh_repo_unit_1', 501);
    unitAssert(is_array($created), 'Created subscription must be found');
    unitAssert((int)($created['is_active'] ?? 0) === 1, 'Created subscription must be active');
    unitAssert((string)($created['last_error'] ?? '') === '', 'Created subscription must not have last_error');

    $markedInactive = $repo->markInactiveByPublicIdForUser(
        'psh_repo_unit_1',
        501,
        'gateway_http_410',
        gmdate('Y-m-d H:i:s')
    );
    unitAssert($markedInactive === true, 'markInactiveByPublicIdForUser must return true');

    $inactive = $repo->findByPublicIdForUser('psh_repo_unit_1', 501);
    unitAssert(is_array($inactive), 'Inactive subscription must still be readable');
    unitAssert((int)($inactive['is_active'] ?? 1) === 0, 'Subscription must become inactive');
    unitAssert((string)($inactive['last_error'] ?? '') === 'gateway_http_410', 'last_error must be persisted');

    $touched = $repo->touchDeliverySuccessByPublicIdForUser(
        'psh_repo_unit_1',
        501,
        gmdate('Y-m-d H:i:s')
    );
    unitAssert($touched === true, 'touchDeliverySuccessByPublicIdForUser must return true');

    $activeAgain = $repo->findByPublicIdForUser('psh_repo_unit_1', 501);
    unitAssert(is_array($activeAgain), 'Reactivated subscription must be readable');
    unitAssert((int)($activeAgain['is_active'] ?? 0) === 1, 'Subscription must become active again');
    $clearedError = $activeAgain['last_error'] ?? null;
    unitAssert($clearedError === null || (string)$clearedError === '', 'last_error must be cleared after successful touch');

    echo "[OK] push_subscription_repository_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] push_subscription_repository_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
