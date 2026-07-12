<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Admin\OperationalWidgetRepository;
use Api\System\Library\Config;

final class AdminWidgetService
{
    public function __construct(
        private readonly OperationalWidgetRepository $repository,
        private readonly Config $config
    ) {
    }

    public function summary(): array
    {
        return [
            'counts' => $this->repository->countsSummary(),
            'logs' => $this->repository->logsSummary(),
            'api_metrics' => $this->repository->requestMetrics24h(),
            'ai_metrics' => $this->repository->aiMetrics24h(),
            'queues' => $this->repository->queueSummary(),
            'migrations' => $this->repository->migrationStatus(),
            'generated_at' => gmdate('c'),
        ];
    }

    public function system(): array
    {
        $storage = (array)$this->config->get('default.storage', []);
        $paths = [
            'logs' => (string)($storage['logs'] ?? ''),
            'uploads' => (string)($storage['uploads'] ?? ''),
            'sessions' => (string)($storage['sessions'] ?? ''),
            'temp' => (string)($storage['temp'] ?? ''),
        ];

        $directories = [];
        foreach ($paths as $key => $path) {
            if ($path === '') {
                $directories[$key] = [
                    'configured' => false,
                    'exists' => false,
                    'size_bytes' => 0,
                ];
                continue;
            }

            $directories[$key] = [
                'configured' => true,
                'exists' => is_dir($path),
                'size_bytes' => $this->directorySize($path),
            ];
        }

        return [
            'app' => [
                'name' => (string)$this->config->get('default.app.name', 'TropaTT API'),
                'version' => (string)$this->config->get('default.app.version', 'v1'),
                'timezone' => (string)$this->config->get('default.app.timezone', 'UTC'),
            ],
            'install' => [
                'lock_file_exists' => is_file((string)$this->config->get('install.lock_file', '')),
            ],
            'database' => [
                'connected' => $this->repository->dbPing(),
            ],
            'storage' => [
                'directories' => $directories,
            ],
            'generated_at' => gmdate('c'),
        ];
    }

    private function directorySize(string $path): int
    {
        if ($path === '' || !is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += (int)$item->getSize();
            }
        }

        return $size;
    }
}
