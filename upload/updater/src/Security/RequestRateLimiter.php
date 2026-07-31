<?php
declare(strict_types=1);

namespace Updater\Security;

/**
 * Minimal file-based rate limiter for the updater's anonymous endpoints.
 *
 * The updater's preflight/download actions accept dry_run requests without a
 * one-time token, because the admin-updates page drives them straight from the
 * browser. That same openness is a DoS / disk-fill vector on shared hosting:
 * an anonymous caller could repeatedly download and stage package files.
 *
 * This limiter keys on (action, client IP) and uses a fixed-window counter
 * stored as a small JSON file with flock(), exactly like the web entry point's
 * webRateLimitCheck(). It needs no DB and no shared cache, so it works on the
 * simplest virtual hosting.
 */
final class RequestRateLimiter
{
    private const SWEEP_INTERVAL = 3600;
    private const MAX_COUNTER_AGE = 86400;

    private string $dir;
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockSeconds;

    /**
     * @param array{max_attempts?:int, window_seconds?:int, lock_seconds?:int, enabled?:bool} $limits
     */
    public function __construct(string $storageDir, array $limits = [])
    {
        $this->dir = $storageDir . '/ratelimit';
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            $this->dir = $storageDir . '/locks';
        }
        $this->maxAttempts = max(1, (int)($limits['max_attempts'] ?? 10));
        $this->windowSeconds = max(1, (int)($limits['window_seconds'] ?? 300));
        $this->lockSeconds = max(1, (int)($limits['lock_seconds'] ?? 900));
    }

    /**
     * Semantics: up to max_attempts requests are allowed per window; the
     * (max_attempts + 1)th request is rejected and locks the client out for
     * lock_seconds.
     *
     * @return array{blocked:bool, retry_after:int}
     */
    public function check(string $action, string $clientIp): array
    {
        $this->sweepStaleCounters();
        $fileName = $this->dir . '/updater_' . hash('sha256', $action . ':' . $clientIp) . '.counter';
        $now = time();

        $fp = @fopen($fileName, 'c+');
        if (!$fp) {
            // Cannot open the counter file (read-only storage?). Fail open so
            // legitimate updates still work; the preflight writability checks
            // will surface real storage problems anyway.
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

        if (($now - $data['window_start']) > $this->windowSeconds) {
            // New window: reset.
            $data = ['count' => 1, 'window_start' => $now, 'blocked_until' => 0];
        } else {
            $data['count']++;
            if ($data['count'] > $this->maxAttempts) {
                // Exceeded the allowed attempts within the window: lock out.
                $data['blocked_until'] = $now + $this->lockSeconds;
            }
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));

        flock($fp, LOCK_UN);
        fclose($fp);

        if ($data['blocked_until'] > $now) {
            return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
        }
        return ['blocked' => false, 'retry_after' => 0];
    }

    /**
     * Occasionally remove counter files that have not been touched for a day.
     * Without this, a burst of many distinct IPs (e.g. an attack) would leave
     * tiny files behind forever; one sweep per hour keeps storage bounded.
     */
    private function sweepStaleCounters(): void
    {
        $marker = $this->dir . '/.sweep';
        $now = time();
        $last = is_file($marker) ? (int)@filemtime($marker) : 0;
        if ($now - $last < self::SWEEP_INTERVAL) {
            return;
        }
        @touch($marker);
        foreach (glob($this->dir . '/updater_*.counter') ?: [] as $file) {
            if (is_file($file) && $now - (int)@filemtime($file) > self::MAX_COUNTER_AGE) {
                @unlink($file);
            }
        }
    }
}
