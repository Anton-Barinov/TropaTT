<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleBulkhead
{
    private int $maxConcurrentRequests = 5;
    private int $queueSize = 100;

    /** @var array<string, int> */
    private array $activeRequests = [];

    /** @var array<string, array<int, callable>> */
    private array $queues = [];

    /**
     * Execute a callable within bulkhead constraints.
     * @return mixed
     */
    public function execute(string $moduleName, callable $fn): mixed
    {
        if (($this->activeRequests[$moduleName] ?? 0) >= $this->maxConcurrentRequests) {
            if (count($this->queues[$moduleName] ?? []) >= $this->queueSize) {
                throw new \RuntimeException("Module '{$moduleName}' request queue full");
            }

            return $this->enqueue($moduleName, $fn);
        }

        $this->activeRequests[$moduleName] = ($this->activeRequests[$moduleName] ?? 0) + 1;

        try {
            return $fn();
        } finally {
            $this->activeRequests[$moduleName]--;
            $this->dequeue($moduleName);
        }
    }

    public function getLoad(string $moduleName): array
    {
        return [
            'active' => $this->activeRequests[$moduleName] ?? 0,
            'queued' => count($this->queues[$moduleName] ?? []),
            'max_concurrent' => $this->maxConcurrentRequests,
        ];
    }

    public function release(string $moduleName): void
    {
        $this->activeRequests[$moduleName] = max(0, ($this->activeRequests[$moduleName] ?? 1) - 1);
        $this->dequeue($moduleName);
    }

    /**
     * @return mixed
     */
    private function enqueue(string $moduleName, callable $fn): mixed
    {
        if (!isset($this->queues[$moduleName])) {
            $this->queues[$moduleName] = [];
        }

        $this->queues[$moduleName][] = $fn;

        return null;
    }

    private function dequeue(string $moduleName): void
    {
        if (($this->queues[$moduleName] ?? []) === []) {
            return;
        }

        $fn = array_shift($this->queues[$moduleName]);
        if ($this->activeRequests[$moduleName] < $this->maxConcurrentRequests) {
            $this->activeRequests[$moduleName] = ($this->activeRequests[$moduleName] ?? 0) + 1;
            try {
                $fn();
            } finally {
                $this->activeRequests[$moduleName]--;
            }
        }
    }
}
