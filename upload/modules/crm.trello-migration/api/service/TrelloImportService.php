<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Service;

use Module\Crm\TrelloMigration\Repository\TrelloMigrationRepository;
use RuntimeException;

final class TrelloImportService
{
    public function __construct(
        private readonly TrelloMigrationRepository $repo,
        private readonly TrelloClient $client,
        private readonly TrelloCrawler $crawler,
        private readonly TrelloTargetWriter $writer,
    ) {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) return;
        $connection = $this->repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) throw new RuntimeException('TRELLO_CONNECTION_NOT_FOUND');
        $key = EncryptionService::decrypt((string)$connection['api_key_encrypted']);
        $token = EncryptionService::decrypt((string)$connection['token_encrypted']);
        if ($key === null || $token === null) throw new RuntimeException('TRELLO_CREDENTIAL_DECRYPT_FAILED');
        $this->client->setConnectionId((int)$connection['id']);
        $actor = $this->repo->actor((int)$job['created_by_user_id']);
        $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message' => 'Loading Trello snapshot'], $leaseToken);
        $crawl = $this->crawler->crawl(
            $job,
            $key,
            $token,
            $leaseToken !== null ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken) : null,
        );
        if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) {
            throw new RuntimeException('TRELLO_JOB_LEASE_LOST');
        }
        $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Trello snapshot loaded.', $crawl);
        if (($job['mode'] ?? 'import') === 'dry_run') {
            $this->repo->updateSummary($jobPublicId, $crawl, $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'dry_run_complete', 100, $crawl, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, 'completed_with_warnings');
            return;
        }

        $types = ['board' => 10, 'list' => 25, 'label' => 35, 'card' => 55, 'member' => 0];
        $all = $this->repo->items((int)$job['id'], null, 5000);
        usort($all, static fn(array $a, array $b): int => ($types[$a['source_type']] ?? 80) <=> ($types[$b['source_type']] ?? 80));
        $total = max(1, count(array_filter($all, static fn(array $i): bool => $i['source_type'] !== 'member')));
        $done = 0;
        $warnings = $crawl['warnings'] ?? [];
        foreach ($all as $item) {
            if ($item['source_type'] === 'member') continue;
            if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) {
                throw new RuntimeException('TRELLO_JOB_LEASE_LOST');
            }
            $current = $this->repo->getJob($jobPublicId);
            if (!$current || in_array((string)$current['status'], ['paused', 'pausing', 'cancelling', 'cancelled'], true)) {
                if (($current['status'] ?? '') === 'pausing') $this->repo->updateJobStatus($jobPublicId, 'paused');
                if (($current['status'] ?? '') === 'cancelling') $this->repo->updateJobStatus($jobPublicId, 'cancelled');
                return;
            }
            try {
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true) ?: [];
                $result = match ((string)$item['source_type']) {
                    'board' => $this->writer->board($job, $payload, $actor),
                    'list' => $this->writer->list($job, $payload, $actor),
                    'label' => $this->writer->label($job, $payload),
                    'card' => $this->writer->card($job, $payload, $actor),
                    default => ['target_type' => '', 'target_public_id' => '', 'state' => 'skipped', 'warnings' => []],
                };
                if (($result['target_public_id'] ?? '') !== '') {
                    $this->repo->upsertMapping((int)$job['connection_id'], (string)$item['source_type'], (string)$item['source_id'], [
                        'source_parent_id' => $item['source_parent_id'] ?: null,
                        'target_type' => $result['target_type'],
                        'target_public_id' => $result['target_public_id'],
                        'source_checksum' => $item['checksum'],
                        'target_checksum' => hash('sha256', (string)$result['target_public_id']),
                        'source_updated_at' => $item['source_updated_at'] ?: null,
                        'created_by_job_id' => (int)$job['id'],
                    ]);
                }
                $warnings = array_merge($warnings, $result['warnings'] ?? []);
                $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], [
                    'target_type' => $result['target_type'],
                    'target_public_id' => $result['target_public_id'],
                    'created_by_job' => $result['state'] === 'imported' ? 1 : 0,
                    'status' => $result['state'],
                    'error_code' => null,
                    'error_message' => null,
                ]);
                if ((string)$item['source_type'] === 'card' && (string)($result['target_public_id'] ?? '') !== '') {
                    $childrenWarnings = $this->writer->children($job, $payload, $actor);
                    $warnings = array_merge($warnings, $childrenWarnings);
                    $options = (array)($job['target_options'] ?? []);
                    if (!empty($options['download_attachments'])) {
                        $attachmentWarnings = $this->writer->attachments($job, $payload, $actor, $key, $token, max(1, (int)($options['max_attachment_size_mb'] ?? 20)) * 1024 * 1024);
                        $warnings = array_merge($warnings, $attachmentWarnings);
                    }
                }
            } catch (\Throwable $e) {
                $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'failed', 'attempts' => (int)$item['attempts'] + 1, 'error_code' => 'IMPORT_FAILED', 'error_message' => 'Item import failed. Check the migration log.']);
                $this->repo->addLog((int)$job['id'], 'error', 'import_' . $item['source_type'], 'Source item import failed.', ['source_type' => $item['source_type'], 'source_id' => $item['source_id']]);
            }
            $done++;
            $this->repo->updateProgress($jobPublicId, 'import_' . $item['source_type'], ($done / $total) * 100, ['processed' => $done, 'total' => $total, 'warnings' => count($warnings)], $leaseToken);
            if ($leaseToken !== null) $this->repo->heartbeat($jobPublicId, $leaseToken);
        }
        $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id']), 'warnings' => array_values(array_unique($warnings))];
        $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, 'completed', 100, $summary, $leaseToken);
        $this->repo->updateJobStatus($jobPublicId, ((int)($summary['items']['failed'] ?? 0) > 0 || $summary['warnings'] !== []) ? 'completed_with_warnings' : 'completed', $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Trello migration completed.', ['failed' => $summary['items']['failed'] ?? 0, 'warnings' => count($summary['warnings'])]);
    }

    public function rollback(string $jobPublicId, array $actor): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) throw new RuntimeException('TRELLO_JOB_NOT_FOUND');
        $items = $this->repo->items((int)$job['id'], null, 5000);
        foreach (array_reverse($items) as $item) {
            if ((int)($item['created_by_job'] ?? 0) !== 1
                || !in_array((string)$item['target_type'], ['project', 'task', 'file', 'project_module', 'status', 'tag', 'checklist', 'checklist_item', 'comment'], true)
                || empty($item['target_public_id'])
            ) continue;
            try {
                $serviceId = match ((string)$item['target_type']) {
                    'project' => 'service.project',
                    'task' => 'service.task',
                    'file' => 'service.file',
                    'project_module' => 'service.project_module',
                    'status' => 'service.status',
                    'tag' => 'service.tag',
                    'checklist', 'checklist_item' => 'service.checklist',
                    'comment' => 'service.comment',
                    default => '',
                };
                if ($serviceId === '') continue;
                $service = $this->writer->service($serviceId);
                if (in_array((string)$item['target_type'], ['status', 'tag'], true)) {
                    $service->delete((string)$item['target_public_id']);
                } else {
                    $service->delete((string)$item['target_public_id'], $actor);
                }
            } catch (\Throwable) {
                $this->repo->addLog((int)$job['id'], 'warning', 'rollback', 'Target item was not removed; it may have been edited or is no longer accessible.');
            }
        }
        $this->repo->updateJobStatus($jobPublicId, 'rolled_back');
        $this->repo->updateProgress($jobPublicId, 'rolled_back', 100, ['policy' => 'soft_delete_only']);
    }

    private function containerDelete(string $serviceId, string $publicId, array $actor): void
    {
        $service = $this->writer->service($serviceId);
        $service->delete($publicId, $actor);
    }
}
