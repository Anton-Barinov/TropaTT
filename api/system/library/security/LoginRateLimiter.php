<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

final class LoginRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly string $storageFile,
        private readonly int $windowSeconds,
        private readonly int $maxAttempts,
        private readonly int $lockSeconds
    ) {
    }

    /** @return array{blocked:bool,retry_after:int} */
    public function check(string $key): array
    {
        return $this->mutate(function (array $data) use ($key): array {
            $now = time();
            $entry = $data[$key] ?? ['attempts' => [], 'blocked_until' => 0];

            $attempts = array_values(array_filter(
                is_array($entry['attempts'] ?? null) ? $entry['attempts'] : [],
                static fn($ts): bool => is_int($ts) || ctype_digit((string)$ts)
            ));
            $attempts = array_map(static fn($ts): int => (int)$ts, $attempts);
            $attempts = array_values(array_filter($attempts, fn(int $ts): bool => ($now - $ts) <= $this->windowSeconds));

            $blockedUntil = (int)($entry['blocked_until'] ?? 0);
            if ($blockedUntil > $now) {
                $entry['attempts'] = $attempts;
                $data[$key] = $entry;
                return [$data, ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)]];
            }

            $entry['blocked_until'] = 0;
            $entry['attempts'] = $attempts;
            $data[$key] = $entry;

            return [$data, ['blocked' => false, 'retry_after' => 0]];
        });
    }

    /** @return array{blocked:bool,retry_after:int} */
    public function hit(string $key): array
    {
        return $this->mutate(function (array $data) use ($key): array {
            $now = time();
            $entry = $data[$key] ?? ['attempts' => [], 'blocked_until' => 0];

            $attempts = array_values(array_filter(
                is_array($entry['attempts'] ?? null) ? $entry['attempts'] : [],
                static fn($ts): bool => is_int($ts) || ctype_digit((string)$ts)
            ));
            $attempts = array_map(static fn($ts): int => (int)$ts, $attempts);
            $attempts = array_values(array_filter($attempts, fn(int $ts): bool => ($now - $ts) <= $this->windowSeconds));
            $attempts[] = $now;

            $blockedUntil = 0;
            if (count($attempts) >= $this->maxAttempts) {
                $blockedUntil = $now + $this->lockSeconds;
            }

            $entry['attempts'] = $attempts;
            $entry['blocked_until'] = $blockedUntil;
            $data[$key] = $entry;

            if ($blockedUntil > $now) {
                return [$data, ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)]];
            }

            return [$data, ['blocked' => false, 'retry_after' => 0]];
        });
    }

    public function clear(string $key): void
    {
        $this->mutate(function (array $data) use ($key): array {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }

            return [$data, ['blocked' => false, 'retry_after' => 0]];
        });
    }

    /**
     * @param callable(array<string,array{attempts:array<int,int>,blocked_until:int}>):array{0:array<string,array{attempts:array<int,int>,blocked_until:int}>,1:array{blocked:bool,retry_after:int}} $mutator
     * @return array{blocked:bool,retry_after:int}
     */
    private function mutate(callable $mutator): array
    {
        $file = $this->storageFile;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return ['blocked' => false, 'retry_after' => 0];
        }

        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return ['blocked' => false, 'retry_after' => 0];
        }

        $json = stream_get_contents($fp);
        $decoded = json_decode(is_string($json) ? $json : '', true);
        $data = is_array($decoded) ? $decoded : [];

        [$nextData, $result] = $mutator($data);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($nextData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        return $result;
    }
}
