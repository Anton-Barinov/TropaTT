<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Cron;

use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabClient;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabCrawler;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabImportService;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabTargetWriter;

final class ActiveCollabWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $repo = new ActiveCollabMigrationRepository($container->get('db.pdo'));
        $job = $repo->claimNextJob();
        if ($job === null) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $token = (string)($job['lease_token'] ?? '');
        $client = new ActiveCollabClient($repo);
        $crawler = new ActiveCollabCrawler($client, $repo);
        $writer = new ActiveCollabTargetWriter($container, $repo, $client);
        $service = new ActiveCollabImportService($repo, $client, $crawler, $writer);
        try {
            $service->processJob((string)$job['public_id'], $token);
            $repo->releaseLease((string)$job['public_id'], $token);
            return json_encode(['processed' => 1, 'job' => $job['public_id']], JSON_UNESCAPED_UNICODE) ?: '{}';
        } catch (\Throwable $e) {
            $repo->addLog((int)$job['id'], 'error', 'worker', 'ActiveCollab worker failed.');
            if ($token === '' || $repo->ownsLease((string)$job['public_id'], $token)) {
                try { $repo->updateJobStatus((string)$job['public_id'], 'failed', $token !== '' ? $token : null); } catch (\Throwable) { }
                $repo->releaseLease((string)$job['public_id'], $token);
            }
            return json_encode(['processed' => 1, 'failed' => 1], JSON_UNESCAPED_UNICODE) ?: '{}';
        }
    }
}
