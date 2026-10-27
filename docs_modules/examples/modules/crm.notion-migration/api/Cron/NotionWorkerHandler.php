<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Cron;

use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Support\Autoloader;
use Api\System\Library\Support\EnvLoader;
use Api\Model\Knowledge\KnowledgeRepository;
use Module\Crm\NotionMigration\Repository\NotionMigrationRepository;
use Module\Crm\NotionMigration\Service\NotionClient;
use Module\Crm\NotionMigration\Service\NotionImportService;
use Module\Crm\NotionMigration\Service\NotionTransformer;

final class NotionWorkerHandler
{
    public function run(): string
    {
        $basePath = dirname(__DIR__, 4);

        $autoloader = new Autoloader($basePath . '/api');
        $autoloader->register();

        if (class_exists(EnvLoader::class)) {
            EnvLoader::loadFiles([
                $basePath . '/.env',
                $basePath . '/.env.local',
                $basePath . '/api/.env',
                $basePath . '/api/.env.local',
            ]);
        }

        $config = new Config($basePath . '/api/config');
        $config->load($basePath . '/api/config/database.php', 'database');

        $connectionManager = new ConnectionManager($config);
        $pdo = $connectionManager->connect();

        $moduleAutoloader = new ModuleAutoloader($basePath);
        $moduleAutoloader->registerModule('crm.notion-migration', 'crm');
        $moduleAutoloader->register();

        $migrationRepo = new NotionMigrationRepository($pdo);
        $knowledgeRepo = new KnowledgeRepository($pdo);
        $client = new NotionClient();
        $transformer = new NotionTransformer();

        $importService = new NotionImportService($knowledgeRepo, $migrationRepo, $client, $transformer);

        $job = $migrationRepo->claimNextJob();
        if ($job === null) return json_encode(['processed' => []], JSON_UNESCAPED_UNICODE);
        $publicId = (string)($job['public_id'] ?? '');
        $organizationId = (int)($job['organization_id'] ?? 0);
        $leaseToken = (string)($job['lease_token'] ?? '');
        if ($publicId === '' || $organizationId < 1 || $leaseToken === '') return json_encode(['processed' => [], 'failed' => 1], JSON_UNESCAPED_UNICODE);
        try {
            $importService->processJob($publicId, $organizationId, $leaseToken);
            $migrationRepo->releaseLease($publicId, $leaseToken);
            return json_encode(['processed' => [$publicId]], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[NotionWorkerHandler] job ' . $publicId . ' failed: ' . $e->getMessage());
            if ($migrationRepo->ownsLease($publicId, $leaseToken)) {
                try {
                    $migrationRepo->addJobLog($publicId, 'error', 'worker', 'Worker failed. Check server logs for details.');
                    $migrationRepo->updateJobStatus($publicId, 'failed');
                } catch (\Throwable) {
                    // A concurrent status transition or revoked worker lease wins.
                }
            }
            // A worker may have transitioned the job to paused/cancelled before throwing
            // the stop sentinel. In that state ownsLease() is intentionally false, but
            // the exact token must still be released so a resumed job is claimable now.
            $migrationRepo->releaseLease($publicId, $leaseToken);
            return json_encode(['processed' => [$publicId], 'failed' => 1], JSON_UNESCAPED_UNICODE);
        }
    }
}
