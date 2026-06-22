<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

use PDO;

final class DatabaseRateLimiter implements RateLimiterInterface
{
    private readonly string $driver;

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

        $attempts = $this->filterAttempts($this->decodeAttempts($row['attempts']), $now);
        if (count($attempts) >= $this->maxAttempts) {
            return ['blocked' => true, 'retry_after' => max(1, ($now + $this->lockSeconds) - $now)];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    public function hit(string $key): array
    {
        $row = $this->fetch($key);
        $now = time();
        $attempts = $row !== null ? $this->filterAttempts($this->decodeAttempts($row['attempts']), $now) : [];
        $blockedUntil = $row !== null ? (int)$row['blocked_until'] : 0;

        if ($blockedUntil > $now) {
            return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)];
        }

        $attempts[] = $now;
        $newBlockedUntil = 0;
        if (count($attempts) >= $this->maxAttempts) {
            $newBlockedUntil = $now + $this->lockSeconds;
        }

        $this->upsert($key, $this->encodeAttempts($attempts), $newBlockedUntil);

        if ($newBlockedUntil > $now) {
            return ['blocked' => true, 'retry_after' => max(1, $newBlockedUntil - $now)];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    public function clear(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE `key` = ?');
        $stmt->execute([$key]);
    }

    private function fetch(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT `key`, attempts, blocked_until FROM rate_limits WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function upsert(string $key, string $attemptsJson, int $blockedUntil): void
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rate_limits (`key`, attempts, blocked_until, updated_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), blocked_until = VALUES(blocked_until), updated_at = NOW()'
            );
            $stmt->execute([$key, $attemptsJson, $blockedUntil]);
        } else {
            $this->pdo->exec('INSERT OR REPLACE INTO rate_limits (`key`, attempts, blocked_until, updated_at)
                VALUES (' . $this->pdo->quote($key) . ', ' . $this->pdo->quote($attemptsJson) . ', ' . $blockedUntil . ', datetime(\'now\'))');
        }
    }

    private function filterAttempts(array $attempts, int $now): array
    {
        return array_values(array_filter($attempts, fn(int $ts): bool => ($now - $ts) <= $this->windowSeconds));
    }

    private function decodeAttempts(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter($decoded, static fn($v): bool => is_int($v) || (is_string($v) && ctype_digit($v))));
    }

    private function encodeAttempts(array $attempts): string
    {
        return json_encode(array_map(static fn(int $ts): int => $ts, $attempts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
