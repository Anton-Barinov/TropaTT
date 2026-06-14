<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use PDO;

/**
 * Cron task handler for Knowledge Base.
 * Designed to be instantiated by ModuleCronScheduler with no constructor args.
 * Each public method maps to a scheduled task.
 */
final class KnowledgeCronTaskHandler
{
    private ?PDO $pdo = null;
    private ?KnowledgeCronService $service = null;

    private function getService(): KnowledgeCronService
    {
        if ($this->service === null) {
            $pdo = $this->getPdo();
            $this->service = new KnowledgeCronService($pdo, null);
        }
        return $this->service;
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $basePath = dirname(__DIR__, 3);
            $config = new Config($basePath . '/config');
            $config->load($basePath . '/config/database.php', 'database');
            $connectionManager = new ConnectionManager($config);
            $this->pdo = $connectionManager->connect();
        }
        return $this->pdo;
    }

    /**
     * Scan published pages for freshness.
     * Called by cron: knowledge.freshness.scan
     */
    public function freshnessScan(): array
    {
        return $this->getService()->freshnessScan();
    }

    /**
     * Clean up old drafts.
     * Called by cron: knowledge.drafts.cleanup
     */
    public function draftsCleanup(): array
    {
        return $this->getService()->draftsCleanup(30);
    }

    /**
     * Clean up old page versions.
     * Called by cron: knowledge.versions.cleanup
     */
    public function versionsCleanup(): array
    {
        return $this->getService()->versionsCleanup(50);
    }

    /**
     * Rebuild search index.
     * Called by cron: knowledge.search.reindex
     */
    public function reindexSearch(): array
    {
        return $this->getService()->reindexSearch();
    }
}
