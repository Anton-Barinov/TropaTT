<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use PDO;

/**
 * Cron task handler for cycle burndown snapshots.
 * Designed to be instantiated by ModuleCronScheduler with no constructor args.
 */
final class CycleSnapshotCronHandler
{
    private ?PDO $pdo = null;

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $basePath = dirname(__DIR__, 3);
            $config = new Config();
            $config->load($basePath . '/config/database.php', 'database');
            $connectionManager = new ConnectionManager($config);
            $this->pdo = $connectionManager->connect();
        }
        return $this->pdo;
    }

    /**
     * Capture daily snapshots for all active cycles.
     * Called by cron: cycles.snapshots.capture_daily
     *
     * @return array<string, mixed>
     */
    public function captureDaily(): array
    {
        $service = new CycleSnapshotCronService($this->getPdo());
        return $service->captureActiveDailySnapshots();
    }
}
