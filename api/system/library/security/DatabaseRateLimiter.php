<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

use PDO;

final class DatabaseRateLimiter implements RateLimiterInterface
{
    private readonly string $driver;
    private bool $schemaReady = false;

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
            return ['blocked' => false, 'retry_after' => 0];
        }

        // Lazy garbage collection: on ~1% of writes, delete expired rows
        // to prevent unbounded table growth (DoS via DB exhaustion).
        // Uses mt_rand because hit count resets per-request (each instance handles
        // at most a few hits), so a counter-based approach would never fire.
        if (mt_rand(1, 100) === 1) {
            $this->collectGarbage();
        }

        $now = time();
        $windowStart = $now - ($now % $this->windowSeconds);

        try {
            if ($this->driver === 'mysql') {
                return $this->hitMysql($key, $now, $windowStart);
            }

            return $this->hitGeneric($key, $now, $windowStart);
        } catch (\Throwable) {
            return ['blocked' => false, 'retry_after' => 0];
        }
    }

    private function hitMysql(string $key, int $now, int $windowStart): array
    {
        // Use explicit transaction with FOR UPDATE row-level lock
        // to prevent race conditions (Task 1.4).
        // SAVEPOINT-based approach was vulnerable to concurrent overwrites.
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
                $expiresAt = $now + $this->windowSeconds + $this->lockSeconds + 60;
                $stmt = $this->pdo->prepare(
                    'UPDATE rate_limits SET attempts_count = 1, window_start = :ws, blocked_until = :bu, expires_at = :ex, updated_at = NOW() WHERE `key` = :k'
                );
                $stmt->execute([':ws' => $windowStart, ':bu' => $newBlocked, ':ex' => $expiresAt, ':k' => $key]);
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $newCount = (int)$row['attempts_count'] + 1;
            $newBlocked = 0;
            $expiresAt = $now + $this->windowSeconds + $this->lockSeconds + 60;
            if ($newCount >= $this->maxAttempts) {
                $newBlocked = $now + $this->lockSeconds;
                $expiresAt = $newBlocked + $this->windowSeconds + 60;
            }

            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits SET attempts_count = :cnt, blocked_until = :bu, expires_at = :ex, updated_at = NOW() WHERE `key` = :k'
            );
            $stmt->execute([':cnt' => $newCount, ':bu' => $newBlocked, ':ex' => $expiresAt, ':k' => $key]);
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
                $expiresAt = $now + $this->windowSeconds + $this->lockSeconds + 60;
                $stmt = $this->pdo->prepare(
                    'INSERT INTO rate_limits (`key`, attempts_count, window_start, blocked_until, expires_at, updated_at) VALUES (?, 1, ?, 0, ?, datetime(\'now\'))'
                );
                $stmt->execute([$key, $windowStart, $expiresAt]);
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
                $expiresAt = $now + $this->windowSeconds + $this->lockSeconds + 60;
                $stmt = $this->pdo->prepare(
                    'UPDATE rate_limits SET attempts_count = 1, window_start = ?, blocked_until = 0, expires_at = ?, updated_at = datetime(\'now\') WHERE `key` = ?'
                );
                $stmt->execute([$windowStart, $expiresAt, $key]);
                $this->pdo->commit();
                return ['blocked' => false, 'retry_after' => 0];
            }

            $newCount = (int)$row['attempts_count'] + 1;
            $newBlocked = 0;
            $expiresAt = $now + $this->windowSeconds + $this->lockSeconds + 60;
            if ($newCount >= $this->maxAttempts) {
                $newBlocked = $now + $this->lockSeconds;
                $expiresAt = $newBlocked + $this->windowSeconds + 60;
            }

            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits SET attempts_count = ?, blocked_until = ?, expires_at = ?, updated_at = datetime(\'now\') WHERE `key` = ?'
            );
            $stmt->execute([$newCount, $newBlocked, $expiresAt, $key]);
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
            $expiresAt = time() + $this->windowSeconds + $this->lockSeconds + 60;
            $stmt = $this->pdo->prepare(
                'INSERT INTO rate_limits (`key`, attempts_count, window_start, blocked_until, expires_at, updated_at) VALUES (?, 1, ?, 0, ?, NOW())'
            );
            return $stmt->execute([$key, $windowStart, $expiresAt]);
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetch(string $key): ?array
    {
        if (!$this->ensureSchema()) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\Throwable) {
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
        } catch (\Throwable) {
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
            $this->migrateSchema();
            $this->schemaReady = true;
            return true;
        } catch (\Throwable) {
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
                expires_at INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`),
                INDEX idx_rate_limits_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts_count INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL DEFAULT 0,
                blocked_until INTEGER NOT NULL DEFAULT 0,
                expires_at INTEGER NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
            )');
            try {
                $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_expires ON rate_limits (expires_at)');
            } catch (\Throwable) {
                // Index may already exist
            }
        }

        $this->ensureNewColumns();
    }

    private function ensureNewColumns(): void
    {
        try {
            if ($this->driver === 'mysql') {
                $cols = $this->pdo->query("SHOW COLUMNS FROM rate_limits")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $cols = $this->pdo->query("PRAGMA table_info(rate_limits)")->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!is_array($cols)) {
                return;
            }

            if (!in_array('attempts_count', $cols, true)) {
                $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN attempts_count INT NOT NULL DEFAULT 0');
            }
            if (!in_array('window_start', $cols, true)) {
                $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN window_start INT NOT NULL DEFAULT 0');
            }
            if (!in_array('expires_at', $cols, true)) {
                if ($this->driver === 'mysql') {
                    $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN expires_at INT NOT NULL DEFAULT 0, ADD INDEX idx_rate_limits_expires (expires_at)');
                } else {
                    $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN expires_at INTEGER NOT NULL DEFAULT 0');
                    try {
                        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_expires ON rate_limits (expires_at)');
                    } catch (\Throwable) {
                        // Index may already exist
                    }
                }
                // Backfill existing rows: set expires_at = blocked_until if > 0, otherwise now + 3600
                $now = time();
                $this->pdo->exec("UPDATE rate_limits SET expires_at = CASE WHEN blocked_until > 0 THEN blocked_until ELSE {$now} + 3600 END WHERE expires_at = 0");
            }
        } catch (\Throwable) {
            // Best-effort migration
        }
    }

    private function collectGarbage(): void
    {
        $now = time();

        try {
            if ($this->driver === 'mysql') {
                $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE expires_at > 0 AND expires_at < ? LIMIT 1000');
            } else {
                $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE expires_at > 0 AND expires_at < ?');
            }
            $stmt->execute([$now]);
        } catch (\Throwable) {
            // Best-effort cleanup
        }
    }
}
