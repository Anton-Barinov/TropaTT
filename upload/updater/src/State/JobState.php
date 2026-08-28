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
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
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
    }
}
