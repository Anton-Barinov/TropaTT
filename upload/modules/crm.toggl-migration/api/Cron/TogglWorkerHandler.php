<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Cron;

use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;
use Module\Crm\TogglMigration\Service\TogglClient;
use Module\Crm\TogglMigration\Service\TogglCrawler;
use Module\Crm\TogglMigration\Service\TogglImportService;
use Module\Crm\TogglMigration\Service\TogglTargetWriter;

final class TogglWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $repo = new TogglMigrationRepository($container->get('db.pdo'));
        $job = $repo->claimNextJob();
        if ($job === null) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $token = (string)($job['lease_token'] ?? '');
        $client = new TogglClient($repo);
        $crawler = new TogglCrawler($client, $repo);
        $writer = new TogglTargetWriter($container, $repo, $client);
        $service = new TogglImportService($repo, $client, $crawler, $writer);
        try {
            $service->processJob((string)$job['public_id'], $token);
            $repo->releaseLease((string)$job['public_id'], $token);
            return json_encode(['processed' => 1, 'job' => $job['public_id']], JSON_UNESCAPED_UNICODE) ?: '{}';
        } catch (\Throwable $e) {
            $repo->addLog((int)$job['id'], 'error', 'worker', 'Toggl worker failed.');
            if ($token === '' || $repo->ownsLease((string)$job['public_id'], $token)) {
                try { $repo->updateJobStatus((string)$job['public_id'], 'failed', $token !== '' ? $token : null); } catch (\Throwable) { }
                $repo->releaseLease((string)$job['public_id'], $token);
            }
            return json_encode(['processed' => 1, 'failed' => 1], JSON_UNESCAPED_UNICODE) ?: '{}';
        }
    }
}
