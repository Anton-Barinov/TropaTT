<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Cron;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;
use Module\Crm\JiraMigration\Service\JiraClient;
use Module\Crm\JiraMigration\Service\JiraCrawler;
use Module\Crm\JiraMigration\Service\JiraImportService;
use Module\Crm\JiraMigration\Service\JiraAdfRenderer;
use PDO;

/**
 * Handles scheduled execution of Jira import jobs.
 * Called by the cron system every 5 minutes.
 */
final class JiraWorkerHandler
{
    public static function run(): void
    {
        global $container;
        if (!$container) {
            return;
        }

        $pdo = $container->get('db.pdo');
        $repo = new JiraMigrationRepository($pdo);

        // Find next queued job
        $stmt = $pdo->prepare("SELECT public_id FROM jira_jobs WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1");
        $stmt->execute();
        $jobPublicId = (string)$stmt->fetchColumn();

        if ($jobPublicId === '') {
            return;
        }

        $job = $repo->getJob($jobPublicId);
        if (!$job) {
            return;
        }

        $mode = (string)$job['mode'];

        if ($mode === 'dry_run') {
            self::runDryRun($job, $repo, $pdo);
        } elseif (in_array($mode, ['import', 'sync'], true)) {
            self::runImport($job, $repo, $pdo);
        }
    }

    private static function runDryRun(array $job, JiraMigrationRepository $repo, PDO $pdo): void
    {
        $connection = $repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) {
            $repo->updateJobStatus((string)$job['public_id'], 'failed');
            return;
        }

        $token = \Module\Crm\JiraMigration\Service\EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null) {
            $repo->updateJobStatus((string)$job['public_id'], 'failed');
            return;
        }

        $client = new JiraClient(repo: $repo);
        $client->setConnectionId((int)$connection['id']);

        $crawler = new JiraCrawler($client, $repo);
        $result = $crawler->crawlProjects($job, (string)$connection['site_url'], (string)$connection['email'], $token);

        $repo->updateJobStatus((string)$job['public_id'], 'completed');
        $repo->updateJobProgress((string)$job['public_id'], 'dry_run_complete', 100, $result);
    }

    private static function runImport(array $job, JiraMigrationRepository $repo, PDO $pdo): void
    {
        $client = new JiraClient(repo: $repo);
        $crawler = new JiraCrawler($client, $repo);
        $adfRenderer = new JiraAdfRenderer();

        $importService = new JiraImportService($repo, $client, $crawler, $adfRenderer, $pdo);
        $importService->processJob((string)$job['public_id']);
    }
}
