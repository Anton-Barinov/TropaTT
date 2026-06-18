<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateStatusService
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function status(): array
    {
        $audit = $this->read('update-center-audit.json');
        $latestJob = null;
        $jobs = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        rsort($jobs);
        if ($jobs) {
            $latestJob = json_decode((string)file_get_contents($jobs[0]), true);
        }
        return [
            'audit' => $audit,
            'latest_job' => is_array($latestJob) ? $latestJob : null,
            'maintenance' => is_file(dirname($this->storageDir) . '/maintenance.flag'),
            'storage_dir' => $this->storageDir,
        ];
    }

    private function read(string $file): ?array
    {
        $path = $this->storageDir . '/' . $file;
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }
}
