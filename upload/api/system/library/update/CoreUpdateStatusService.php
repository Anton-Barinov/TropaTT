<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateStatusService
{
    public function __construct(
        private readonly string $storageDir,
        private readonly ?CoreUpdateClient $client = null,
        private readonly array $config = []
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
        }
        return [
            'audit' => $audit,
            'update_center' => $this->updateCenter(),
            'latest_job' => is_array($latestJob) ? $latestJob : null,
            'maintenance' => is_file(dirname($this->storageDir) . '/maintenance.flag'),
            'storage_dir' => $this->storageDir,
        ];
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
