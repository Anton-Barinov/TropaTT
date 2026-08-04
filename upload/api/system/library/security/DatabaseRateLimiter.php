<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

use PDO;
use Api\System\Library\Database\IndexHelper;

final class DatabaseRateLimiter implements RateLimiterInterface
{
    private readonly string $driver;
    private bool $schemaReady = false;
    private ?bool $hasLegacyAttemptsColumn = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $windowSeconds,
        private readonly int $maxAttempts,
        private readonly int $lockSeconds
    ) {
        $this->driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function check(string $key): array
    {
        if (!$this->ensureSchema()) {
            return ['blocked' => true, 'retry_after' => 5];
        }

        $row = $this->fetch($key);
        $now = time();

        if ($row === null) {
            return ['blocked' => false, 'retry_after' => 0];
        }

        $blockedUntil = (int)$row['blocked_until'];
        if ($blockedUntil > $now) {
            return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)];
        }

        $attemptsCount = $this->getAttemptsCount($row, $now);
        if ($attemptsCount >= $this->maxAttempts) {
            return ['blocked' => true, 'retry_after' => max(1, ($now + $this->lockSeconds) - $now)];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    public function hit(string $key): array
    {
        if (!$this->ensureSchema()) {
            // Fail-closed: if schema is not ready, block with minimal retry
            return ['blocked' => true, 'retry_after' => 5];
        }

        // Lazy garbage collection: on ~1% of writes, delete rows that haven't
        // been updated in over an hour (safely beyond any lock period).
        if (random_int(1, 100) === 1) {
            $this->collectGarbage();
        }

        $now = time();
        $windowStart = $now - ($now % $this->windowSeconds);

        try {
            if ($this->driver === 'mysql') {
                return $this->hitMysql($key, $now, $windowStart);
            }

            return $this->hitGeneric($key, $now, $windowStart);
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::hit] ' . $e->getMessage());
            return ['blocked' => false, 'retry_after' => 0];
        }
    }

    private function hitMysql(string $key, int $now, int $windowStart): array
    {
        // Use explicit transaction with FOR UPDATE row-level lock
        // to prevent race conditions (Task 1.4).
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }

        try {
            $inserted = $this->tryInsert($key, $windowStart);
            if ($inserted) {
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $row = $this->fetchForUpdate($key);
            if ($row === null) {
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $blockedUntil = (int)$row['blocked_until'];
            if ($blockedUntil > $now) {
                $this->pdo->commit();
                return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)];
            }

            $rowWindowStart = (int)$row['window_start'];
            if ($rowWindowStart < $windowStart) {
                $newBlocked = 0;
                $stmt = $this->pdo->prepare(
                    'UPDATE rate_limits SET attempts_count = 1, window_start = :ws, blocked_until = :bu, updated_at = NOW() WHERE `key` = :k'
                );
                $stmt->execute([':ws' => $windowStart, ':bu' => $newBlocked, ':k' => $key]);
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $newCount = (int)$row['attempts_count'] + 1;
            $newBlocked = 0;
            if ($newCount >= $this->maxAttempts) {
                $newBlocked = $now + $this->lockSeconds;
            }

            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits SET attempts_count = :cnt, blocked_until = :bu, updated_at = NOW() WHERE `key` = :k'
            );
            $stmt->execute([':cnt' => $newCount, ':bu' => $newBlocked, ':k' => $key]);
            $this->pdo->commit();

            if ($newBlocked > $now) {
                return ['blocked' => true, 'retry_after' => max(1, $newBlocked - $now)];
            }

            return ['blocked' => false, 'retry_after' => 0];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function hitGeneric(string $key, int $now, int $windowStart): array
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->fetchForUpdate($key);

            if ($row === null) {
                $this->insertFirstAttempt($key, $windowStart, "datetime('now')");
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $blockedUntil = (int)$row['blocked_until'];
            if ($blockedUntil > $now) {
                $this->pdo->commit();
                return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)];
            }

            $rowWindowStart = (int)$row['window_start'];
            if ($rowWindowStart < $windowStart) {
                $stmt = $this->pdo->prepare(
                    'UPDATE rate_limits SET attempts_count = 1, window_start = ?, blocked_until = 0, updated_at = datetime(\'now\') WHERE `key` = ?'
                );
                $stmt->execute([$windowStart, $key]);
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $newCount = (int)$row['attempts_count'] + 1;
            $newBlocked = 0;
            if ($newCount >= $this->maxAttempts) {
                $newBlocked = $now + $this->lockSeconds;
            }

            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits SET attempts_count = ?, blocked_until = ?, updated_at = datetime(\'now\') WHERE `key` = ?'
            );
            $stmt->execute([$newCount, $newBlocked, $key]);
            $this->pdo->commit();

            if ($newBlocked > $now) {
                return ['blocked' => true, 'retry_after' => max(1, $newBlocked - $now)];
            }

            return ['blocked' => false, 'retry_after' => 0];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function clear(string $key): void
    {
        if (!$this->ensureSchema()) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE `key` = ?');
        $stmt->execute([$key]);
    }

    private function tryInsert(string $key, int $windowStart): bool
    {
        try {
            $this->insertFirstAttempt($key, $windowStart, 'NOW()');
            return true;
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::tryInsert] ' . $e->getMessage());
            return false;
        }
    }

    private function insertFirstAttempt(string $key, int $windowStart, string $currentTimeSql): void
    {
        if ($this->hasLegacyAttemptsColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rate_limits (`key`, attempts, attempts_count, window_start, blocked_until, updated_at)'
                . ' VALUES (?, ?, 1, ?, 0, ' . $currentTimeSql . ')'
            );
            $stmt->execute([$key, '[]', $windowStart]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (`key`, attempts_count, window_start, blocked_until, updated_at)'
            . ' VALUES (?, 1, ?, 0, ' . $currentTimeSql . ')'
        );
        $stmt->execute([$key, $windowStart]);
    }

    private function fetch(string $key): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::fetch] SELECT failed: ' . $e->getMessage());
            return null;
        }
    }

    private function fetchForUpdate(string $key): ?array
    {
        try {
            if ($this->driver === 'mysql') {
                $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ? FOR UPDATE');
            } else {
                $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ?');
            }
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::fetchForUpdate] SELECT failed: ' . $e->getMessage());
            return null;
        }
    }

    private function getAttemptsCount(array $row, int $now): int
    {
        $rowWindowStart = (int)($row['window_start'] ?? 0);
        if ($rowWindowStart === 0 || ($now - $rowWindowStart) > $this->windowSeconds) {
            return 0;
        }
        return (int)($row['attempts_count'] ?? 0);
    }

    private function ensureSchema(): bool
    {
        if ($this->schemaReady) {
            return true;
        }

        try {
            // This limiter is instantiated for every rate-limited request.
            // `CREATE TABLE IF NOT EXISTS` takes metadata locks in MySQL even
            // when the table already exists, which serialises otherwise
            // independent requests during concurrent logins and page loads.
            // Probe the lightweight schema first; run DDL only for a new or
            // legacy installation that genuinely needs an upgrade.
            if ($this->hasExpectedColumns()) {
                $this->schemaReady = true;
                return true;
            }

            $this->migrateSchema();
            $this->schemaReady = true;
            return true;
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::ensureSchema] ' . $e->getMessage());
            return false;
        }
    }

    private function migrateSchema(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts_count INT NOT NULL DEFAULT 0,
                window_start INT NOT NULL DEFAULT 0,
                blocked_until INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`),
                INDEX idx_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts_count INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL DEFAULT 0,
                blocked_until INTEGER NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
            )');
            IndexHelper::createIndexIfNotExists($this->pdo, 'rate_limits', 'idx_updated_at', 'updated_at');
        }

        $this->ensureNewColumns();
    }

    private function ensureNewColumns(): void
    {
        if ($this->hasExpectedColumns()) {
            return;
        }

        try {
            if ($this->driver === 'mysql') {
                $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN attempts_count INT NOT NULL DEFAULT 0');
            } else {
                $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN attempts_count INTEGER NOT NULL DEFAULT 0');
            }
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::ensureNewColumns] schema check failed: ' . $e->getMessage());
            // The column may already have been added by a concurrent request.
        }

        try {
            if ($this->driver === 'mysql') {
                $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN window_start INT NOT NULL DEFAULT 0');
            } else {
                $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN window_start INTEGER NOT NULL DEFAULT 0');
            }
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::ensureNewColumns] schema check failed: ' . $e->getMessage());
            // The column may already have been added by a concurrent request.
        }

        if (!$this->hasExpectedColumns()) {
            throw new \RuntimeException('Rate limiter schema is incompatible');
        }
    }

    private function hasExpectedColumns(): bool
    {
        try {
            $this->pdo->query('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits LIMIT 0');
            return true;
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::hasExpectedColumns] schema check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function hasLegacyAttemptsColumn(): bool
    {
        if ($this->hasLegacyAttemptsColumn !== null) {
            return $this->hasLegacyAttemptsColumn;
        }

        try {
            $this->pdo->query('SELECT attempts FROM rate_limits LIMIT 0');
            $this->hasLegacyAttemptsColumn = true;
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::hasLegacyAttemptsColumn] legacy column check failed: ' . $e->getMessage());
            $this->hasLegacyAttemptsColumn = false;
        }

        return $this->hasLegacyAttemptsColumn;
    }

    private function collectGarbage(): void
    {
        try {
            if ($this->driver === 'mysql') {
                // Delete rows not updated in over 1 hour (safely beyond any lock window)
                $this->pdo->exec("DELETE FROM rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 3600 SECOND) LIMIT 1000");
            } else {
                $this->pdo->exec("DELETE FROM rate_limits WHERE updated_at < datetime('now', '-3600 seconds')");
            }
        } catch (\Throwable $e) {
            error_log('[DatabaseRateLimiter::collectGarbage] cleanup failed: ' . $e->getMessage());
            // Best-effort cleanup
        }
    }
}
