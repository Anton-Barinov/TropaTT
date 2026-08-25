<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Notification\PushDispatchQueueRepository;
use Api\Model\Notification\PushSubscriptionRepository;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Logger\JsonLogger;
use PDO;

/**
 * Cron task handler that drains the web-push dispatch queue.
 *
 * Designed to be instantiated by ModuleCronScheduler with no constructor args,
 * mirroring CycleSnapshotCronHandler and FinanceCronTaskHandler.
 */
final class PushCronTaskHandler
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
            $config->load($this->basePath() . '/config/notifications.php', 'notifications');
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
     * Deliver queued web-push notifications.
     * Called by cron: notifications.push.dispatch_queue
     *
     * @return array<string, mixed>
     */
    public function dispatchQueue(): array
    {
        $pdo = $this->getPdo();
        $config = $this->getConfig();

        $service = new NotificationPushService(
            new PushSubscriptionRepository($pdo),
            new PushDispatchQueueRepository($pdo),
            new JsonLogger(['push' => $this->basePath() . '/logs/cron_push.log']),
            $config
        );

        return $service->runQueued(
            max(1, min(200, (int)$config->get('notifications.push.cron_batch_size', 50)))
        );
    }
}
