<?php
declare(strict_types=1);

namespace Updater\Apply;

final class MaintenanceMode
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function enable(string $jobId): void
    {
        $flag = $this->flagPath();
        $dir = dirname($flag);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($flag, json_encode([
            'job_id' => $jobId,
            'enabled_at' => gmdate('c'),
            'reason' => 'core_update_apply',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function disable(): void
    {
        $flag = $this->flagPath();
        if (is_file($flag)) {
            @unlink($flag);
        }
    }

    private function flagPath(): string
    {
        return $this->basePath . '/storage_api/maintenance.flag';
    }
}
