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

        $now = time();
        $windowStart = $now - ($now % $this->windowSeconds);

        if ($this->driver === 'mysql') {
            return $this->hitMysql($key, $now, $windowStart);
        }

        return $this->hitGeneric($key, $now, $windowStart);
    }

    private function hitMysql(string $key, int $now, int $windowStart): array
    {
        $this->pdo->exec('SAVEPOINT rl_hit');

        $inserted = $this->tryInsert($key, $windowStart);
        if ($inserted) {
            $this->pdo->exec('RELEASE SAVEPOINT rl_hit');
            return ['blocked' => false, 'retry_after' => 0];
        }

        $row = $this->fetchForUpdate($key);
        if ($row === null) {
            $this->pdo->exec('RELEASE SAVEPOINT rl_hit');
            return ['blocked' => false, 'retry_after' => 0];
        }

        $blockedUntil = (int)$row['blocked_until'];
        if ($blockedUntil > $now) {
            $this->pdo->exec('RELEASE SAVEPOINT rl_hit');
            return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)];
        }

        $rowWindowStart = (int)$row['window_start'];
        if ($rowWindowStart < $windowStart) {
            $newBlocked = 0;
            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits SET attempts_count = 1, window_start = :ws, blocked_until = :bu, updated_at = NOW() WHERE `key` = :k'
            );
            $stmt->execute([':ws' => $windowStart, ':bu' => $newBlocked, ':k' => $key]);
            $this->pdo->exec('RELEASE SAVEPOINT rl_hit');
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
        $this->pdo->exec('RELEASE SAVEPOINT rl_hit');

        if ($newBlocked > $now) {
            return ['blocked' => true, 'retry_after' => max(1, $newBlocked - $now)];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    private function hitGeneric(string $key, int $now, int $windowStart): array
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->fetchForUpdate($key);

            if ($row === null) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO rate_limits (`key`, attempts_count, window_start, blocked_until, updated_at) VALUES (?, 1, ?, 0, datetime(\'now\'))'
                );
                $stmt->execute([$key, $windowStart]);
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
            $stmt = $this->pdo->prepare(
                'INSERT INTO rate_limits (`key`, attempts_count, window_start, blocked_until, updated_at) VALUES (?, 1, ?, 0, NOW())'
            );
            return $stmt->execute([$key, $windowStart]);
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetch(string $key): ?array
    {
        if (!$this->ensureSchema()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function fetchForUpdate(string $key): ?array
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ? FOR UPDATE');
        } else {
            $stmt = $this->pdo->prepare('SELECT `key`, attempts_count, window_start, blocked_until FROM rate_limits WHERE `key` = ?');
        }
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
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
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
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
        }

        $this->migrateFromLegacy();
    }

    private function migrateFromLegacy(): void
    {
        try {
            $cols = $this->pdo->query("PRAGMA table_info(rate_limits)")->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($cols) || in_array('attempts_count', $cols, true)) {
                return;
            }

            $rows = $this->pdo->query("SELECT `key`, attempts, blocked_until FROM rate_limits")->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return;
            }

            $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN attempts_count INT NOT NULL DEFAULT 0');
            $this->pdo->exec('ALTER TABLE rate_limits ADD COLUMN window_start INT NOT NULL DEFAULT 0');

            foreach ($rows as $row) {
                $attempts = json_decode((string)($row['attempts'] ?? '[]'), true);
                $count = is_array($attempts) ? count($attempts) : 0;
                $stmt = $this->pdo->prepare('UPDATE rate_limits SET attempts_count = ?, window_start = ? WHERE `key` = ?');
                $stmt->execute([$count, time(), $row['key']]);
            }
        } catch (\Throwable) {
            // Migration is best-effort
        }
    }
}
