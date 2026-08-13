<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Cron;

use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use Module\Crm\AsanaMigration\Service\AsanaClient;
use Module\Crm\AsanaMigration\Service\AsanaCrawler;
use Module\Crm\AsanaMigration\Service\AsanaImportService;
use Module\Crm\AsanaMigration\Service\AsanaTargetWriter;

final class AsanaWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $repo = new AsanaMigrationRepository($container->get('db.pdo'));
        $job = $repo->claimNextJob();
        if ($job === null) return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $token = (string)($job['lease_token'] ?? '');
        $client = new AsanaClient($repo);
        $crawler = new AsanaCrawler($client, $repo);
        $writer = new AsanaTargetWriter($container, $repo, $client);
        $service = new AsanaImportService($repo, $client, $crawler, $writer);
        try {
            $service->processJob((string)$job['public_id'], $token);
            $repo->releaseLease((string)$job['public_id'], $token);
            return json_encode(['processed' => 1, 'job' => $job['public_id']], JSON_UNESCAPED_UNICODE) ?: '{}';
        } catch (\Throwable $e) {
            $repo->addLog((int)$job['id'], 'error', 'worker', 'Asana worker failed.');
            if ($token === '' || $repo->ownsLease((string)$job['public_id'], $token)) {
                try { $repo->updateJobStatus((string)$job['public_id'], 'failed', $token !== '' ? $token : null); } catch (\Throwable) { }
                $repo->releaseLease((string)$job['public_id'], $token);
            }
            return json_encode(['processed' => 1, 'failed' => 1], JSON_UNESCAPED_UNICODE) ?: '{}';
        }
    }
}
