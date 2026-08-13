<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Cron;

use Module\Crm\TrelloMigration\Repository\TrelloMigrationRepository;
use Module\Crm\TrelloMigration\Service\TrelloClient;
use Module\Crm\TrelloMigration\Service\TrelloCrawler;
use Module\Crm\TrelloMigration\Service\TrelloImportService;
use Module\Crm\TrelloMigration\Service\TrelloTargetWriter;

final class TrelloWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $repo = new TrelloMigrationRepository($container->get('db.pdo'));
        $job = $repo->claimNextJob();
        if ($job === null) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $client = new TrelloClient($repo);
        $crawler = new TrelloCrawler($client, $repo);
        $writer = new TrelloTargetWriter($container, $repo, $client);
        $service = new TrelloImportService($repo, $client, $crawler, $writer);
        try {
            $service->processJob((string)$job['public_id'], (string)($job['lease_token'] ?? ''));
            $repo->releaseLease((string)$job['public_id'], (string)($job['lease_token'] ?? ''));
            return json_encode(['processed' => 1, 'job' => $job['public_id']], JSON_UNESCAPED_UNICODE) ?: '{}';
        } catch (\Throwable $e) {
            $repo->addLog((int)$job['id'], 'error', 'worker', 'Worker failed. Check server logs for details.');
            $leaseToken = (string)($job['lease_token'] ?? '');
            // A worker whose lease was taken over must not mark the newer
            // worker's job as failed or clear its lease.
            if ($leaseToken === '' || $repo->ownsLease((string)$job['public_id'], $leaseToken)) {
                $repo->updateJobStatus((string)$job['public_id'], 'failed');
                $repo->releaseLease((string)$job['public_id'], $leaseToken);
            }
            return json_encode(['processed' => 1, 'failed' => 1], JSON_UNESCAPED_UNICODE) ?: '{}';
        }
    }
}
