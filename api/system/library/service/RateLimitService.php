<?php

declare(strict_types=1);

namespace Api\System\Library\Service;

final class RateLimitService
{
    private ?string $storageDir = null;

    public function check(string $prefix, string $key, int $maxAttempts, int $windowSeconds, int $lockSeconds, bool $increment = true): array
    {
        $now = time();
        $file = $this->storageDir() . '/crm_' . $prefix . '_' . hash('sha256', $key) . '.counter';

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return ['blocked' => false, 'retry_after' => 0];
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return ['blocked' => false, 'retry_after' => 0];
        }

        $raw = stream_get_contents($fp);
        $data = ($raw !== false && $raw !== '') ? @json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = ['count' => 0, 'window_start' => 0, 'blocked_until' => 0];
        }
        $data['count'] = (int)($data['count'] ?? 0);
        $data['window_start'] = (int)($data['window_start'] ?? 0);
        $data['blocked_until'] = (int)($data['blocked_until'] ?? 0);

        if ($data['blocked_until'] > $now) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
        }

        if ($increment) {
            if (($now - $data['window_start']) > $windowSeconds) {
                $data = ['count' => 1, 'window_start' => $now, 'blocked_until' => 0];
            } else {
                $data['count']++;
                if ($data['count'] >= $maxAttempts) {
                    $data['blocked_until'] = $now + $lockSeconds;
                }
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        if ($data['blocked_until'] > $now) {
            return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    public function clear(string $prefix, string $key): void
    {
        $file = $this->storageDir() . '/crm_' . $prefix . '_' . hash('sha256', $key) . '.counter';
        @unlink($file);
    }

    private function storageDir(): string
    {
        if ($this->storageDir !== null) {
            return $this->storageDir;
        }

        $dir = dirname(__DIR__, 3) . '/../storage_api/cache/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $this->storageDir = realpath($dir) ?: $dir;

        return $this->storageDir;
    }
}
