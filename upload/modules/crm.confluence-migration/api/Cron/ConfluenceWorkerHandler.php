<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Cron;

use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Service\FileService;
use Api\System\Library\Support\Autoloader;
use Api\System\Library\Support\EnvLoader;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Tag\TagRepository;
use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use Module\Crm\ConfluenceMigration\Service\ConfluenceAttachmentService;
use Module\Crm\ConfluenceMigration\Service\ConfluenceClient;
use Module\Crm\ConfluenceMigration\Service\ConfluenceImportService;
use Module\Crm\ConfluenceMigration\Service\ConfluenceJobService;
use Module\Crm\ConfluenceMigration\Service\ConfluenceLinkRewriter;
use Module\Crm\ConfluenceMigration\Service\ConfluenceMacroRenderer;
use Module\Crm\ConfluenceMigration\Service\ConfluenceTransformer;

final class ConfluenceWorkerHandler
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
        $moduleAutoloader->registerModule('crm.confluence-migration', 'crm');
        $moduleAutoloader->register();

        $fileService = new FileService($pdo);
        $migrationRepo = new ConfluenceMigrationRepository($pdo);
        $knowledgeRepo = new KnowledgeRepository($pdo);
        $tagRepo = new TagRepository($pdo);

        $jobService = new ConfluenceJobService(
            $migrationRepo,
            $knowledgeRepo,
            $fileService,
            $tagRepo,
            $pdo,
        );

        $result = $jobService->runQueued(10);

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
