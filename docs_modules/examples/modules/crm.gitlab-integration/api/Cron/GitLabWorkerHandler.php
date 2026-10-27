<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Cron;

use Module\Crm\GitlabIntegration\Repository\GitLabRepository;
use Module\Crm\GitlabIntegration\Service\EncryptionService;
use Module\Crm\GitlabIntegration\Service\GitLabClient;
use Module\Crm\GitlabIntegration\Service\GitLabSyncService;

/**
 * Cron fallback poller: syncs dirty links (webhook-triggered) and, periodically,
 * every active link, so the module works without a publicly reachable webhook.
 */
final class GitLabWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container instanceof \Api\System\Library\Container) {
            return json_encode(['processed' => 0], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $pdo = $container->get('db.pdo');
        $settings = (new GitLabRepository($pdo, 0))->getSettings();
        $batchSize = max(1, (int)($settings['batch_size'] ?? 100));
        $pollIntervalMinutes = max(1, (int)($settings['poll_interval_minutes'] ?? 15));
        $syncComments = (bool)($settings['sync_comments'] ?? true);
        $maxLinksPerRun = 10;

        $links = GitLabRepository::listActiveLinksForWorker($pdo, 500);
        $dueCutoff = time() - ($pollIntervalMinutes * 60);

        $toSync = [];
        foreach ($links as $linkRef) {
            $organizationId = (int)($linkRef['organization_id'] ?? 0);
            if ($organizationId <= 0) continue;
            $tenantRepo = new GitLabRepository($pdo, $organizationId);
            $link = $tenantRepo->getLink((string)$linkRef['public_id']);
            if (!$link) continue;
            $isDirty = (int)($link['is_dirty'] ?? 0) === 1;
            $lastSynced = strtotime((string)($link['last_synced_at'] ?? ''));
            $isDue = $lastSynced === false || $lastSynced <= $dueCutoff;
            if ($isDirty || $isDue) {
                $toSync[] = $link;
            }
        }

        $processed = 0;
        $synced = 0;
        $failed = 0;

        foreach (array_slice($toSync, 0, $maxLinksPerRun) as $link) {
            $organizationId = (int)($link['organization_id'] ?? 0);
            if ($organizationId <= 0) continue;
            $repo = new GitLabRepository($pdo, $organizationId);
            $actor = self::systemActor($pdo, $organizationId);
            $processed++;
            $publicId = (string)$link['public_id'];
            try {
                $connection = $repo->getConnectionById((int)$link['connection_id']);
                if (!$connection) {
                    $repo->addLog((int)$link['id'], 'error', 'Connection not found');
                    $failed++;
                    continue;
                }
                $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
                if ($token === null || $token === '') {
                    $repo->addLog((int)$link['id'], 'error', 'Failed to decrypt token');
                    $failed++;
                    continue;
                }

                $sync = new GitLabSyncService($container, $repo, new GitLabClient(30, 3, self::allowedHosts($container)));
                $counts = $sync->syncLink($link, $token, $actor, $batchSize, $syncComments);
                $repo->markSynced($publicId);
                $repo->addLog((int)$link['id'], 'info', 'Poll sync completed: ' . json_encode($counts, JSON_UNESCAPED_UNICODE));
                $synced++;
            } catch (\Throwable $e) {
                error_log('[GitLabWorkerHandler] link ' . $publicId . ' failed: ' . $e->getMessage());
                $repo->addLog((int)$link['id'], 'error', 'Poll sync failed. Check server logs.');
                $failed++;
            }
        }

        return json_encode(['processed' => $processed, 'synced' => $synced, 'failed' => $failed], JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /** @return list<string> */
    private static function allowedHosts(\Api\System\Library\Container $container): array
    {
        try {
            $config = $container->has('module.config') ? $container->get('module.config')->getAll('crm.gitlab-integration') : [];
            return GitLabClient::normalizeAllowedHosts(is_array($config) ? (array)($config['allowed_api_hosts'] ?? []) : []);
        } catch (\Throwable) {
            return ['gitlab.com'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function systemActor(\PDO $pdo, int $organizationId): array
    {
        try {
            $stmt = $pdo->query('SELECT id, public_id, login, full_name FROM users WHERE is_root = 1 ORDER BY id ASC LIMIT 1');
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row) && $row !== []) {
                return ['id' => (int)$row['id'], 'public_id' => (string)$row['public_id'], 'is_root' => true, 'organization_id' => $organizationId];
            }
        } catch (\Throwable $e) {
            error_log('[GitLabWorkerHandler] systemActor: ' . $e->getMessage());
        }
        return ['id' => 0, 'public_id' => '', 'is_root' => false, 'organization_id' => $organizationId];
    }
}
