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

        $processed = [];
        $jobs = $migrationRepo->listJobs();
        foreach ($jobs as $job) {
            if ($job['status'] !== 'queued') {
                continue;
            }
            $publicId = (string)($job['public_id'] ?? '');
            if ($publicId === '') {
                continue;
            }
            try {
                $importService->processJob($publicId);
                $processed[] = $publicId;
            } catch (\Throwable $e) {
                error_log('[NotionWorkerHandler] job ' . $publicId . ' failed: ' . $e->getMessage());
                $migrationRepo->addJobLog($publicId, 'error', 'worker', 'Worker failed. Check server logs for details.');
                $migrationRepo->updateJobStatus($publicId, 'failed');
            }
        }

        return json_encode(['processed' => $processed], JSON_UNESCAPED_UNICODE);
    }
}
