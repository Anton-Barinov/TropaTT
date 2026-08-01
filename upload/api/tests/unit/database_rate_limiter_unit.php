<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/security/RateLimiterInterface.php';
require_once __DIR__ . '/../../system/library/security/DatabaseRateLimiter.php';

use Api\System\Library\Security\DatabaseRateLimiter;

function databaseRateLimiterAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $limiter = new DatabaseRateLimiter($pdo, 60, 2, 30);
    $key = str_repeat('a', 64);

    databaseRateLimiterAssert($limiter->check($key)['blocked'] === false, 'Fresh key must not be blocked');
    databaseRateLimiterAssert($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'rate_limits'")->fetchColumn() === 'rate_limits', 'Limiter must create missing schema lazily');

    databaseRateLimiterAssert($limiter->hit($key)['blocked'] === false, 'First hit must not block');

    $blocked = $limiter->hit($key);
    databaseRateLimiterAssert($blocked['blocked'] === true, 'Second hit must block when maxAttempts is 2');
    databaseRateLimiterAssert((int)$blocked['retry_after'] > 0, 'Blocked response must include retry_after');
    databaseRateLimiterAssert($limiter->check($key)['blocked'] === true, 'Persisted blocked state must be enforced');

    $limiter->clear($key);
    databaseRateLimiterAssert($limiter->check($key)['blocked'] === false, 'clear() must remove blocked state');

    // Old shared-hosting installations may retain a non-null attempts column.
    // The limiter must add the current columns without breaking first writes.
    $legacyPdo = new PDO('sqlite::memory:');
    $legacyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $legacyPdo->exec('CREATE TABLE rate_limits (`key` VARCHAR(64) PRIMARY KEY, attempts TEXT NOT NULL, blocked_until INTEGER NOT NULL DEFAULT 0, updated_at TEXT)');
    $legacyLimiter = new DatabaseRateLimiter($legacyPdo, 60, 2, 30);
    $legacyKey = str_repeat('b', 64);
    databaseRateLimiterAssert($legacyLimiter->check($legacyKey)['blocked'] === false, 'Legacy schema must be upgraded lazily');
    databaseRateLimiterAssert($legacyLimiter->hit($legacyKey)['blocked'] === false, 'First hit on legacy schema must be recorded');
    databaseRateLimiterAssert($legacyLimiter->hit($legacyKey)['blocked'] === true, 'Second hit on legacy schema must block');

    echo "[OK] database_rate_limiter_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] database_rate_limiter_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
