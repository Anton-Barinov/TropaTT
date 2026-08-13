<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Service;

use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use RuntimeException;

final class AsanaImportService
{
    public function __construct(private readonly AsanaMigrationRepository $repo, private readonly AsanaClient $client, private readonly AsanaCrawler $crawler, private readonly AsanaTargetWriter $writer)
    {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) return;
        $connection = $this->repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) throw new RuntimeException('ASANA_CONNECTION_NOT_FOUND');
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) throw new RuntimeException('ASANA_CREDENTIAL_DECRYPT_FAILED');
        $this->client->setConnectionId((int)$connection['id']);
        $heartbeat = $leaseToken !== null ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken) : null;
        $existingCursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $resumeImport = ($job['mode'] ?? 'import') !== 'dry_run'
            && is_array($existingCursor)
            && ($existingCursor['phase'] ?? '') === 'import'
            && $this->repo->itemCount((int)$job['id']) > 0
            && str_starts_with((string)($job['current_step'] ?? ''), 'import_');

        if ($resumeImport) {
            $crawl = ['resumed' => true, 'warnings' => []];
        } else {
            $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message' => 'Loading Asana source graph'], $leaseToken);
            $crawl = $this->crawler->crawl($job, $token, $heartbeat);
            if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
            $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Asana source graph loaded.', $crawl);
            if (($job['mode'] ?? 'import') !== 'dry_run') $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'import', 'priority' => 0, 'id' => 0], JSON_UNESCAPED_UNICODE), $leaseToken);
        }

        if (($job['mode'] ?? 'import') === 'dry_run') {
            $this->repo->updateSummary($jobPublicId, ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id'])], $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'dry_run_complete', 100, $crawl, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, 'completed_with_warnings', $leaseToken);
            return;
        }

        $actor = $this->actor($job);
        $total = max(1, $this->repo->itemCount((int)$job['id']));
        $counts = $this->repo->itemCounts((int)$job['id']);
        $done = (int)($counts['imported'] ?? 0) + (int)($counts['updated'] ?? 0) + (int)($counts['skipped'] ?? 0) + (int)($counts['failed'] ?? 0);
        $warnings = (array)($crawl['warnings'] ?? []);
        $cursor = is_array($existingCursor) && $resumeImport ? $existingCursor : ['phase' => 'import', 'priority' => 0, 'id' => 0];
        $priority = max(0, (int)($cursor['priority'] ?? 0));
        $lastId = max(0, (int)($cursor['id'] ?? 0));

        while (($items = $this->repo->importItemsBatch((int)$job['id'], $priority, $lastId, 250)) !== []) {
            foreach ($items as $item) {
                if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
                $current = $this->repo->getJob($jobPublicId);
                $currentStatus = (string)($current['status'] ?? '');
                if (in_array($currentStatus, ['pausing', 'paused', 'cancelling', 'cancelled'], true)) {
                    if ($currentStatus === 'pausing') $this->repo->updateJobStatus($jobPublicId, 'paused');
                    if ($currentStatus === 'cancelling') $this->repo->updateJobStatus($jobPublicId, 'cancelled');
                    return;
                }
                $type = (string)$item['source_type'];
                $raw = json_decode((string)($item['payload_json'] ?? '{}'), true);
                $payload = is_array($raw) ? $raw : [];
                if (in_array($type, ['project', 'section', 'task', 'subtask'], true)) $payload['_source_project_gid'] = (string)($item['source_project_id'] ?? $payload['_source_project_gid'] ?? '');
                if (in_array($type, ['task', 'subtask'], true)) { $payload['_source_parent_gid'] = (string)($item['source_parent_id'] ?? ''); $typeForMapping = 'task'; } else $typeForMapping = $type;
                if (in_array($type, ['comment', 'attachment'], true)) $payload['_source_task_gid'] = (string)($item['source_parent_id'] ?? '');
                $existingMapping = $this->repo->findMapping((int)$job['connection_id'], (string)$job['workspace_gid'], $typeForMapping, (string)$item['source_id']);
                if (empty($item['target_public_id']) && !empty($existingMapping['target_public_id'])) {
                    $item['target_public_id'] = (string)$existingMapping['target_public_id'];
                    $item['created_by_job'] = (int)($existingMapping['created_by_job_id'] ?? 0) === (int)$job['id'] ? 1 : 0;
                    $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['target_type' => $existingMapping['target_type'] ?? null, 'target_public_id' => $item['target_public_id'], 'created_by_job' => $item['created_by_job']]);
                }
                try {
                    $result = match ($type) {
                        'project' => $this->writer->project($job, $payload, $actor),
                        'section' => $this->writer->section($job, $payload, $actor),
                        'tag' => $this->writer->tag($job, $payload),
                        'task', 'subtask' => $this->writer->task($job, $payload, $actor),
                        'dependency' => $this->writer->dependency($job, $payload, $actor),
                        'comment' => $this->writer->comment($job, $payload, $actor),
                        'attachment' => $this->writer->attachment($job, $payload, $actor, $token, max(1, (int)($job['target_options']['max_attachment_size_mb'] ?? 20)) * 1024 * 1024),
                        default => ['target_type' => '', 'target_public_id' => '', 'state' => 'skipped', 'warnings' => []],
                    };
                    $warnings = array_merge($warnings, (array)($result['warnings'] ?? []));
                    $target = (string)($result['target_public_id'] ?? '');
                    if ($target !== '') $this->repo->upsertMapping((int)$job['connection_id'], (string)$job['workspace_gid'], $typeForMapping, (string)$item['source_id'], ['source_parent_id' => $item['source_parent_id'] ?: null, 'target_type' => $result['target_type'], 'target_public_id' => $target, 'source_checksum' => $item['checksum'] ?? null, 'target_checksum' => hash('sha256', $target), 'created_by_job_id' => (int)$job['id']]);
                    $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['target_type' => $result['target_type'], 'target_public_id' => $target, 'created_by_job' => $result['state'] === 'imported' ? 1 : (int)($item['created_by_job'] ?? 0), 'status' => $result['state'], 'error_code' => null, 'error_message' => null]);
                } catch (\Throwable $e) {
                    $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'failed', 'attempts' => (int)($item['attempts'] ?? 0) + 1, 'error_code' => 'IMPORT_FAILED', 'error_message' => 'Item import failed. Check the migration log.']);
                    $this->repo->addLog((int)$job['id'], 'error', 'import_' . $type, 'Source item import failed.', ['source_type' => $type, 'source_id' => $item['source_id'], 'error_code' => $e->getCode() ?: 'ASANA_IMPORT_ERROR']);
                }
                $done++;
                $priority = (int)($item['import_priority'] ?? $priority);
                $lastId = (int)$item['id'];
                $this->repo->updateProgress($jobPublicId, 'import_' . $type, min(99, ($done / $total) * 100), ['processed' => $done, 'total' => $total, 'warnings' => count($warnings)], $leaseToken);
            }
            // The cursor is persisted only after a complete batch. A worker crash therefore retries the whole unfinished batch.
            $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'import', 'priority' => $priority, 'id' => $lastId], JSON_UNESCAPED_UNICODE), $leaseToken);
        }

        $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id']), 'warnings' => array_values(array_unique($warnings))];
        $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, 'completed', 100, $summary, $leaseToken);
        $failed = (int)($summary['items']['failed'] ?? 0);
        $this->repo->updateJobStatus($jobPublicId, $failed > 0 || $summary['warnings'] !== [] ? 'completed_with_warnings' : 'completed', $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Asana migration completed.', ['failed' => $failed, 'warnings' => count($summary['warnings'])]);
    }

    public function rollback(string $jobPublicId, array $actor): void
    {
        $job = $this->repo->beginRollback($jobPublicId);
        if (!$job) throw new RuntimeException('ASANA_ROLLBACK_REQUIRES_TERMINAL_JOB');
        $leaseToken = (string)($job['lease_token'] ?? '');
        $warnings = [];
        try {
            $rollbackCursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
            $beforeId = is_array($rollbackCursor) ? max(1, (int)($rollbackCursor['before_id'] ?? PHP_INT_MAX)) : PHP_INT_MAX;
            while (($items = $this->repo->rollbackItemsBatch((int)$job['id'], $beforeId, 250)) !== []) {
                $batchHadWarning = false;
                foreach ($items as $item) {
                    if (!$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('ASANA_ROLLBACK_LEASE_LOST');
                    if ((int)($item['created_by_job'] ?? 0) !== 1 || empty($item['target_public_id'])) continue;
                    try {
                        $targetType = (string)$item['target_type'];
                        $serviceId = match ($targetType) { 'project' => 'service.project', 'task' => 'service.task', 'file' => 'service.file', 'project_module' => 'service.project_module', 'tag' => 'service.tag', 'dependency' => 'service.dependency', 'comment' => 'service.comment', default => '' };
                        if ($serviceId === '') continue;
                        $service = $this->containerService($serviceId);
                        $deleted = $targetType === 'tag' ? $service->delete((string)$item['target_public_id']) : $service->delete((string)$item['target_public_id'], $actor);
                        if ($deleted !== true) throw new RuntimeException('ASANA_ROLLBACK_TARGET_NOT_DELETED');
                        $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'rolled_back']);
                    } catch (\Throwable $e) {
                        // Keep the item unrolled so a later rollback retry can try it again.
                        $batchHadWarning = true;
                        $warnings[] = (string)$item['source_id'];
                        $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'rollback_failed', 'error_code' => 'ROLLBACK_FAILED', 'error_message' => 'Target could not be removed.']);
                        $this->repo->addLog((int)$job['id'], 'warning', 'rollback', 'Target item was not removed; it may have been edited or is no longer accessible.', ['source_id' => $item['source_id'], 'error_code' => $e->getCode() ?: 'ASANA_ROLLBACK_TARGET_NOT_DELETED']);
                    }
                }
                // Do not advance the checkpoint when a batch contains a failed delete. The terminal warning state
                // can be retried from the beginning, while a worker crash before that state retries this batch.
                if ($batchHadWarning) break;
                $beforeId = min(array_map('intval', array_column($items, 'id')));
                $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'rollback', 'before_id' => $beforeId], JSON_UNESCAPED_UNICODE), $leaseToken);
            }
            $this->repo->updateSummary($jobPublicId, ['rollback_warnings' => array_values(array_unique($warnings))], $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'rolled_back', 100, ['warnings' => count($warnings)], $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, $warnings === [] ? 'rolled_back' : 'rolled_back_with_warnings', $leaseToken);
            $this->repo->releaseLease($jobPublicId, $leaseToken);
        } catch (\Throwable $e) {
            try {
                if ($this->repo->ownsLease($jobPublicId, $leaseToken)) {
                    $this->repo->updateJobStatus($jobPublicId, 'rollback_failed', $leaseToken);
                    $this->repo->releaseLease($jobPublicId, $leaseToken);
                }
            } catch (\Throwable) {
            }
            throw $e;
        }
    }

    private function containerService(string $id): mixed { return $this->writer->service($id); }
    private function actor(array $job): array { return $this->repo->actor((int)$job['created_by_user_id']); }
}
