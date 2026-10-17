<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateStatusService
{
    public function __construct(
        private readonly string $storageDir,
        private readonly ?CoreUpdateClient $client = null,
        private readonly array $config = [],
        private readonly ?CoreVersion $version = null
    )
    {
    }

    public function status(): array
    {
        $audit = $this->read('update-center-audit.json');
        $latestJob = null;
        $jobs = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        // Newest state file first (mtime), never string sort on job ids: an
        // old failed id like upd_e2e_... would otherwise surface as the
        // "latest" job forever and keep showing its stale error in the UI.
        usort($jobs, static fn(string $a, string $b): int => @filemtime($b) <=> @filemtime($a));
        if ($jobs) {
            $latestJob = json_decode((string)file_get_contents($jobs[0]), true);
            if (is_array($latestJob)) {
                $latestJob = $this->reconcileTerminalMaintenanceState($jobs[0], $latestJob);
            }
        }
        return [
            'audit' => $audit,
            'update_center' => $this->updateCenter(),
            'latest_job' => is_array($latestJob) ? $latestJob : null,
            'maintenance' => is_file(dirname($this->storageDir) . '/maintenance.flag'),
            // This reports only whether the installation-scoped mutex is held
            // at this instant. The release host guard still serializes runs;
            // the updater re-acquires its exclusive mutex for every mutation.
            'deployment_busy' => \Updater\State\DeploymentMutex::isBusy(
                dirname($this->storageDir, 2)
            ),
            // SEC (audit 2026-10, finding #13): storage_dir (an absolute
            // filesystem path) is no longer returned. It is reconnaissance
            // material for building traversal payloads and nothing in the web
            // UI reads it. The value stays available internally — the
            // constructor argument and CoreUpdateController still use it.
            'installed_core' => $this->version?->current(),
        ];
    }

    private function reconcileTerminalMaintenanceState(string $statePath, array $job): array
    {
        if (!in_array((string)($job['state'] ?? ''), ['applied', 'rolled_back'], true)
            || ($job['maintenance_held'] ?? false) !== true
            || !is_string($job['job_id'] ?? null)
            || preg_match('/\A[A-Za-z0-9._-]{1,128}\z/D', $job['job_id']) !== 1
            || is_link($statePath)) {
            return $job;
        }

        $storageApi = dirname($this->storageDir);
        $maintenanceFlag = $storageApi . '/maintenance.flag';
        $updateLock = $this->storageDir . '/locks/update.lock';
        $basePath = dirname($this->storageDir, 2);
        if (is_link($storageApi) || is_link($maintenanceFlag) || file_exists($maintenanceFlag)
            || is_link(dirname($updateLock)) || is_link($updateLock) || file_exists($updateLock)
            || \Updater\State\DeploymentMutex::isBusy($basePath)) {
            return $job;
        }

        $state = new \Updater\State\JobState($this->storageDir, $job['job_id']);
        $current = $state->readFile('state.json');
        if (!is_array($current) || ($current['job_id'] ?? null) !== $job['job_id']
            || !in_array((string)($current['state'] ?? ''), ['applied', 'rolled_back'], true)
            || ($current['maintenance_held'] ?? false) !== true) {
            return is_array($current) ? $current : $job;
        }

        try {
            $state->write(['maintenance_held' => false]);
            return $state->readFile('state.json') ?? array_merge($current, ['maintenance_held' => false]);
        } catch (\Throwable) {
            return $current;
        }
    }

    private function updateCenter(): array
    {
        $url = rtrim((string)($this->config['update_center_url'] ?? ''), '/');
        if (!$this->client) {
            return [
                'url' => $url,
                'ok' => null,
                'status' => null,
                'error' => null,
                'message' => null,
            ];
        }
        $health = $this->client->health();
        return [
            'url' => $url,
            'ok' => (bool)($health['ok'] ?? false),
            'status' => (int)($health['status'] ?? 0),
            'error' => (string)($health['error'] ?? ''),
            'message' => (string)($health['message'] ?? ''),
            'health' => $health,
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
