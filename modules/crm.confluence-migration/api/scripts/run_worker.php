<?php
declare(strict_types=1);

/**
 * Confluence Migration Worker Script
 *
 * Processes queued confluence import jobs in the background.
 *
 * Usage:
 *   php run_worker.php                          # process next job and exit
 *   php run_worker.php --limit=5                # process up to 5 jobs
 *   php run_worker.php --job=cij_xxx            # process specific job
 *   php run_worker.php --watch                  # run continuously (daemon)
 *   php run_worker.php --watch --interval=10    # poll every 10 seconds
 *
 * Recommended cron entry (every minute):
 *   * * * * * php /path/to/run_worker.php --limit=10
 */

// Bootstrap CRM
$basePath = dirname(__DIR__, 4);
require_once $basePath . '/api/index.php';

// Actually we need a proper bootstrap without HTTP
// Use the same pattern as jobs_worker_run.php

$options = getopt('', ['job:', 'limit:', 'watch', 'interval:']);
$jobPublicId = $options['job'] ?? '';
$limit = (int)($options['limit'] ?? 1);
$watch = isset($options['watch']);
$interval = (int)($options['interval'] ?? 10);

fwrite(STDOUT, "Confluence Migration Worker started\n");

if ($jobPublicId !== '') {
    fwrite(STDOUT, "Processing specific job: {$jobPublicId}\n");
    processSingleJob($jobPublicId);
    exit(0);
}

if ($watch) {
    fwrite(STDOUT, "Watch mode: polling every {$interval}s\n");
    while (true) {
        $result = processBatch($limit);
        if ($result['processed'] > 0) {
            fwrite(STDOUT, date('Y-m-d H:i:s') . " Batch: {$result['processed']} processed, {$result['completed']} completed, {$result['failed']} failed\n");
        }
        sleep($interval);
    }
} else {
    $result = processBatch($limit);
    fwrite(STDOUT, "Batch done: {$result['processed']} processed, {$result['completed']} completed, {$result['failed']} failed\n");
    if ($result['errors'] !== []) {
        foreach ($result['errors'] as $err) {
            fwrite(STDERR, "  Error: {$err}\n");
        }
    }
}

function processBatch(int $limit): array
{
    $app = buildApp();
    if ($app === null) {
        return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'errors' => ['Bootstrap failed']];
    }

    $jobService = buildJobService($app);
    return $jobService->runQueued($limit);
}

function processSingleJob(string $publicId): void
{
    $app = buildApp();
    if ($app === null) {
        fwrite(STDERR, "Bootstrap failed\n");
        exit(1);
    }

    $migrationRepo = $app->getContainer()->get('repository.confluence_migration');
    $job = $migrationRepo->getJob($publicId);
    if (!$job) {
        fwrite(STDERR, "Job not found: {$publicId}\n");
        exit(1);
    }

    $jobService = buildJobService($app);
    $claimed = $jobService->claimNextRunnable();
    if (!$claimed || (string)$claimed['public_id'] !== $publicId) {
        $migrationRepo->updateJobStatus($publicId, 'queued');
    }

    $importService = buildImportService($app);
    $importService->processJob($publicId);
    fwrite(STDOUT, "Job {$publicId} processed\n");
}

function buildApp(): ?object
{
    static $app = null;
    if ($app !== null) {
        return $app;
    }

    try {
        $basePath = dirname(__DIR__, 4);
        require_once $basePath . '/api/system/library/support/Autoloader.php';

        $autoloader = new Api\System\Library\Support\Autoloader($basePath . '/api');
        $autoloader->register();

        if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
            Api\System\Library\Support\EnvLoader::loadFiles([
                $basePath . '/.env',
                $basePath . '/.env.local',
                $basePath . '/api/.env',
                $basePath . '/api/.env.local',
            ]);
        }

        $config = new Api\System\Library\Config($basePath . '/api/config');
        $config->load($basePath . '/api/config/database.php', 'database');

        $connectionManager = new Api\System\Library\Database\ConnectionManager($config);
        $pdo = $connectionManager->connect();

        $container = new Api\System\Library\Container();
        $container->set('db.pdo', $pdo);

        // Register common services
        $container->factory('file.service', fn() => new FileService($pdo));

        // Module autoloader
        $moduleAutoloader = new Api\System\Library\Module\ModuleAutoloader($basePath);
        $moduleAutoloader->registerModule('crm.confluence-migration', 'crm');
        $moduleAutoloader->register();

        // Core repositories
        $container->factory('repository.knowledge', fn() => new KnowledgeRepository($pdo, $container));
        $container->factory('repository.tag', fn() => new TagRepository($pdo));
        $container->factory('repository.confluence_migration', fn() => new ConfluenceMigrationRepository($pdo));

        $app = (object)[
            'container' => $container,
            'pdo' => $pdo,
        ];
        return $app;
    } catch (\Throwable $e) {
        error_log('[ConfluenceWorker::buildApp] Bootstrap error: ' . $e->getMessage());
        fwrite(STDERR, "Bootstrap error. Check server logs for details.\n");
        return null;
    }
}

function buildJobService(object $app): ConfluenceJobService
{
    return new ConfluenceJobService(
        $app->container->get('repository.confluence_migration'),
        $app->container->get('repository.knowledge'),
        $app->container->get('file.service'),
        $app->container->get('repository.tag'),
        $app->pdo,
    );
}

function buildImportService(object $app): ConfluenceImportService
{
    $migrationRepo = $app->container->get('repository.confluence_migration');
    $knowledgeRepo = $app->container->get('repository.knowledge');
    $fileService = $app->container->get('file.service');
    $tagRepo = $app->container->get('repository.tag');

    return new ConfluenceImportService(
        $knowledgeRepo,
        $migrationRepo,
        new ConfluenceClient(repo: $migrationRepo),
        new ConfluenceTransformer(
            new ConfluenceMacroRenderer(),
            new ConfluenceLinkRewriter(),
        ),
        new ConfluenceAttachmentService($fileService, $migrationRepo, $app->pdo),
        $fileService,
        $tagRepo,
        $app->pdo,
    );
}
