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
        // Sort by the state file's modification time (newest first), NOT by
        // the job id string: an old failed job id (e.g. upd_e2e_...) sorts
        // ABOVE current upd_YYYYMMDD... ids under plain string rsort, which
        // would surface a stale failed job as the "latest" forever and keep
        // showing its error on the admin-updates page.
        usort($states, static function (string $a, string $b): int {
            return @filemtime($b) <=> @filemtime($a);
        });
        $data = json_decode((string)file_get_contents($states[0]), true);
        return is_array($data) ? $data : null;
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
