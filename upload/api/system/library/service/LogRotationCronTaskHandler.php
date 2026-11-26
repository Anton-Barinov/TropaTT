<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Support\AppLog;
use PDO;

/**
 * Cron task handler for application log rotation and database log retention pruning.
 * Designed to be instantiated by ModuleCronScheduler with no constructor args.
 */
final class LogRotationCronTaskHandler
{
    private ?PDO $pdo = null;
    private ?Config $config = null;

    private function basePath(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getConfig(): Config
    {
        if ($this->config === null) {
            $config = new Config();
            $config->load($this->basePath() . '/config/database.php', 'database');
            $config->load($this->basePath() . '/config/logging.php', 'logging');
            if (is_file($this->basePath() . '/config/logging.local.php')) {
                $config->load($this->basePath() . '/config/logging.local.php', 'logging');
            }
            $this->config = $config;
        }

        return $this->config;
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $connectionManager = new ConnectionManager($this->getConfig());
            $this->pdo = $connectionManager->connect();
        }

        return $this->pdo;
    }

    /**
     * Rotate and compress log files and prune expired database logs.
     * Called by cron: system.logs.rotate
     *
     * @return array<string, mixed>
     */
    public function rotateLogs(): array
    {
        try {
            $service = new LogRotationService($this->getPdo(), $this->getConfig());
            return $service->run();
        } catch (\Throwable $e) {
            AppLog::error('[LogRotationCronTaskHandler::rotateLogs] ' . $e->getMessage());
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
