<?php
declare(strict_types=1);

namespace Updater\State;

final class JobState
{
    public function __construct(private readonly string $storageDir, private readonly ?string $jobId = null)
    {
    }

    public function write(array $patch): void
    {
        $current = $this->readFile('state.json') ?: [];
        // Stamp every progress update with the wall-clock time of the write.
        // The admin page uses it to tell "the update is working" from "the job
        // is stranded because the browser/connection was lost", which is the
        // shared-hosting failure mode: no daemon, no cron, nothing else runs
        // between requests. Doing it here keeps every phase transition honest
        // without each one having to remember.
        if (is_array($patch['progress'] ?? null) && !isset($patch['progress']['at'])) {
            $patch['progress']['at'] = gmdate('c');
        }
        $data = array_merge($current, $patch, [
            'job_id' => $this->jobId,
            'updated_at' => gmdate('c'),
        ]);
        if (!isset($data['started_at'])) {
            $data['started_at'] = gmdate('c');
        }
        $this->writeFile('state.json', $data);
    }

    public function latest(): ?array
    {
        $states = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        if (!$states) {
            return null;
        }
        usort($states, static function (string $a, string $b): int {
            $diff = self::resolveJobTimestamp($b) <=> self::resolveJobTimestamp($a);
            if ($diff !== 0) {
                return $diff;
            }
            return @filemtime($b) <=> @filemtime($a);
        });
        $data = json_decode((string)file_get_contents($states[0]), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Resolves the creation/start timestamp of an update job.
     *
     * Order of priority:
     * 1. Standard job ID prefix: upd_YYYYMMDD_HHMMSS (zero-cost, avoids disk reads)
     * 2. `started_at` or `created_at` in state data (parsed via strtotime)
     * 3. Filesystem modification time (@filemtime)
     *
     * Sorting by this timestamp ensures that modifying an abandoned job's state.json
     * (e.g. during retention cleanup or reconciliation) never falsely promotes it
     * above newer updates.
     */
    public static function resolveJobTimestamp(string $stateFile, ?array $state = null): int
    {
        $jobId = basename(dirname($stateFile));
        if (preg_match('/^upd_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', $jobId, $m)) {
            $t = (int)gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
            if ($t > 0) {
                return $t;
            }
        }
        if ($state === null && is_file($stateFile)) {
            $raw = @file_get_contents($stateFile);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $state = $decoded;
                }
            }
        }
        if (is_array($state)) {
            if (!empty($state['started_at'])) {
                $t = is_numeric($state['started_at']) ? (int)$state['started_at'] : (int)strtotime((string)$state['started_at']);
                if ($t > 0) {
                    return $t;
                }
            }
            if (!empty($state['created_at'])) {
                $t = is_numeric($state['created_at']) ? (int)$state['created_at'] : (int)strtotime((string)$state['created_at']);
                if ($t > 0) {
                    return $t;
                }
            }
            if (!empty($state['job_id']) && preg_match('/^upd_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', (string)$state['job_id'], $m)) {
                $t = (int)gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
                if ($t > 0) {
                    return $t;
                }
            }
        }
        return (int)@filemtime($stateFile);
    }

    public function readFile(string $file): ?array
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public function writeFile(string $file, array $data): void
    {
        $dir = $this->storageDir . '/jobs/' . basename((string)$this->jobId);
        $jobs = dirname($dir);
        if (!is_dir($jobs) && !@mkdir($jobs, 0700, true) && !is_dir($jobs)) {
            throw new \RuntimeException('Unable to create updater jobs directory.');
        }
        if (is_link($jobs)) { throw new \RuntimeException('Updater jobs directory cannot be a symlink.'); }
        @chmod($jobs, 0700);
        if (!is_dir($dir) && !@mkdir($dir, 0700) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create updater job directory.');
        }
        if (is_link($dir)) { throw new \RuntimeException('Updater job directory cannot be a symlink.'); }
        @chmod($dir, 0700);
        $this->atomicWrite($dir . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function path(string $file): string
    {
        return $this->storageDir . '/jobs/' . basename((string)$this->jobId) . '/' . $file;
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to persist updater state.');
        }
        @chmod($tmp, 0600);
    }
}
