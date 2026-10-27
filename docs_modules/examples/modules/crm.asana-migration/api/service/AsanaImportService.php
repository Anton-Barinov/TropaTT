<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Service;

use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use RuntimeException;

final class AsanaImportService
{
    public const MAX_ATTACHMENT_SIZE_MB = 20;

    public function __construct(private readonly AsanaMigrationRepository $repo, private readonly AsanaClient $client, private readonly AsanaCrawler $crawler, private readonly AsanaTargetWriter $writer)
    {
    }

    public function processJob(string $jobPublicId, int $organizationId, ?string $leaseToken = null): void
    {
        if ($organizationId < 1) throw new RuntimeException('ASANA_WORKSPACE_REQUIRED');
        if ($leaseToken === null || $leaseToken === '') throw new RuntimeException('ASANA_JOB_LEASE_REQUIRED');
        $job = $this->repo->getJob($jobPublicId, $organizationId);
        if (!$job) return;
        // Resolve the original owner against the persisted CRM workspace on every worker run.
        // A queued job must not become a way to retain access after membership is revoked.
        $actor = $this->actor($job, $organizationId);
        $connection = $this->repo->getConnectionById((int)$job['connection_id'], $organizationId);
        if (!$connection) throw new RuntimeException('ASANA_CONNECTION_NOT_FOUND');
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) throw new RuntimeException('ASANA_CREDENTIAL_DECRYPT_FAILED');
        $this->client->setConnectionId((int)$connection['id']);
        $heartbeat = function () use ($job, $organizationId, $jobPublicId, $leaseToken): bool {
            // The crawler can take many minutes; check membership between source pages/items too.
            $this->actor($job, $organizationId);
            return $leaseToken === null || $this->repo->heartbeat($jobPublicId, $organizationId, $leaseToken);
        };
        $existingCursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $resumeImport = ($job['mode'] ?? 'import') !== 'dry_run'
            && is_array($existingCursor)
            && ($existingCursor['phase'] ?? '') === 'import'
            && $this->repo->itemCount((int)$job['id']) > 0
            && str_starts_with((string)($job['current_step'] ?? ''), 'import_');

        if ($resumeImport) {
            $crawl = ['resumed' => true, 'warnings' => []];
        } else {
            $this->repo->updateProgress($jobPublicId, $organizationId, 'crawl', 0, ['message' => 'Loading Asana source graph'], $leaseToken);
            $crawl = $this->crawler->crawl($job, $token, $heartbeat);
            if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $organizationId, $leaseToken)) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
            $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Asana source graph loaded.', $crawl);
            if (($job['mode'] ?? 'import') !== 'dry_run') $this->repo->updateCursor($jobPublicId, $organizationId, json_encode(['phase' => 'import', 'priority' => 0, 'id' => 0], JSON_UNESCAPED_UNICODE), $leaseToken);
        }

        // A dry run still reads and persists source records; do not finish it after access is revoked.
        $this->actor($job, $organizationId);

        if (($job['mode'] ?? 'import') === 'dry_run') {
            $this->repo->updateSummary($jobPublicId, $organizationId, ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id'])], $leaseToken);
            $this->repo->updateProgress($jobPublicId, $organizationId, 'dry_run_complete', 100, $crawl, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, $organizationId, 'completed_with_warnings', $leaseToken);
            return;
        }

        $total = max(1, $this->repo->itemCount((int)$job['id']));
        $counts = $this->repo->itemCounts((int)$job['id']);
        $done = (int)($counts['imported'] ?? 0) + (int)($counts['updated'] ?? 0) + (int)($counts['skipped'] ?? 0) + (int)($counts['failed'] ?? 0);
        $warnings = (array)($crawl['warnings'] ?? []);
        $cursor = is_array($existingCursor) && $resumeImport ? $existingCursor : ['phase' => 'import', 'priority' => 0, 'id' => 0];
        $priority = max(0, (int)($cursor['priority'] ?? 0));
        $lastId = max(0, (int)($cursor['id'] ?? 0));

        while (($items = $this->repo->importItemsBatch((int)$job['id'], $priority, $lastId, 250)) !== []) {
            foreach ($items as $item) {
                if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $organizationId, $leaseToken)) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
                $current = $this->repo->getJob($jobPublicId, $organizationId);
                // Revalidate before every target write so membership removal during a long
                // import stops further writes. Core services receive the persisted org context.
                $actor = $this->actor($job, $organizationId);
                $currentStatus = (string)($current['status'] ?? '');
                if (in_array($currentStatus, ['pausing', 'paused', 'cancelling', 'cancelled'], true)) {
                    if ($currentStatus === 'pausing') $this->repo->updateJobStatus($jobPublicId, $organizationId, 'paused', $leaseToken);
                    if ($currentStatus === 'cancelling') $this->repo->updateJobStatus($jobPublicId, $organizationId, 'cancelled', $leaseToken);
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
                        'tag' => $this->writer->tag($job, $payload, $actor),
                        'task', 'subtask' => $this->writer->task($job, $payload, $actor),
                        'dependency' => $this->writer->dependency($job, $payload, $actor),
                        'comment' => $this->writer->comment($job, $payload, $actor),
                        'attachment' => $this->writer->attachment($job, $payload, $actor, $token, $this->attachmentMaxBytes($job)),
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
            $this->repo->updateProgress($jobPublicId, $organizationId, 'import_' . $type, min(99, ($done / $total) * 100), ['processed' => $done, 'total' => $total, 'warnings' => count($warnings)], $leaseToken);
            }
            // The cursor is persisted only after a complete batch. A worker crash therefore retries the whole unfinished batch.
            $this->repo->updateCursor($jobPublicId, $organizationId, json_encode(['phase' => 'import', 'priority' => $priority, 'id' => $lastId], JSON_UNESCAPED_UNICODE), $leaseToken);
        }

        $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id']), 'warnings' => array_values(array_unique($warnings))];
        $this->repo->updateSummary($jobPublicId, $organizationId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, $organizationId, 'completed', 100, $summary, $leaseToken);
        $failed = (int)($summary['items']['failed'] ?? 0);
        $this->repo->updateJobStatus($jobPublicId, $organizationId, $failed > 0 || $summary['warnings'] !== [] ? 'completed_with_warnings' : 'completed', $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Asana migration completed.', ['failed' => $failed, 'warnings' => count($summary['warnings'])]);
    }

    private function attachmentMaxBytes(array $job): int
    {
        $configuredMb = (int)($job['target_options']['max_attachment_size_mb'] ?? self::MAX_ATTACHMENT_SIZE_MB);
        return min(self::MAX_ATTACHMENT_SIZE_MB, max(1, $configuredMb)) * 1024 * 1024;
    }

    public function rollback(string $jobPublicId, array $actor): void
    {
        $organizationId = (int)($actor['organization_id'] ?? 0);
        $actor = $this->actor(['created_by_user_id' => (int)($actor['id'] ?? 0)], $organizationId);
        if ($organizationId < 1) throw new RuntimeException('ASANA_WORKSPACE_REQUIRED');
        $job = $this->repo->beginRollback($jobPublicId, $organizationId);
        if (!$job) throw new RuntimeException('ASANA_ROLLBACK_REQUIRES_TERMINAL_JOB');
        $leaseToken = (string)($job['lease_token'] ?? '');
        $warnings = [];
        try {
            $rollbackCursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
            $beforeId = is_array($rollbackCursor) ? max(1, (int)($rollbackCursor['before_id'] ?? PHP_INT_MAX)) : PHP_INT_MAX;
            while (($items = $this->repo->rollbackItemsBatch((int)$job['id'], $beforeId, 250)) !== []) {
                $batchHadWarning = false;
                foreach ($items as $item) {
                    if (!$this->repo->heartbeat($jobPublicId, $organizationId, $leaseToken)) throw new RuntimeException('ASANA_ROLLBACK_LEASE_LOST');
                    if ((int)($item['created_by_job'] ?? 0) !== 1 || empty($item['target_public_id'])) continue;
                    try {
                        $targetType = (string)$item['target_type'];
                        $serviceId = match ($targetType) { 'project' => 'service.project', 'task' => 'service.task', 'file' => 'service.file', 'project_module' => 'service.project_module', 'tag' => 'service.tag', 'dependency' => 'service.dependency', 'comment' => 'service.comment', default => '' };
                        if ($serviceId === '') continue;
                        $service = $this->containerService($serviceId);
                        $deleted = $service->delete((string)$item['target_public_id'], $actor);
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
                $this->repo->updateCursor($jobPublicId, $organizationId, json_encode(['phase' => 'rollback', 'before_id' => $beforeId], JSON_UNESCAPED_UNICODE), $leaseToken);
            }
            $this->repo->updateSummary($jobPublicId, $organizationId, ['rollback_warnings' => array_values(array_unique($warnings))], $leaseToken);
            $this->repo->updateProgress($jobPublicId, $organizationId, 'rolled_back', 100, ['warnings' => count($warnings)], $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, $organizationId, $warnings === [] ? 'rolled_back' : 'rolled_back_with_warnings', $leaseToken);
            $this->repo->releaseLease($jobPublicId, $organizationId, $leaseToken);
        } catch (\Throwable $e) {
            try {
                if ($this->repo->ownsLease($jobPublicId, $organizationId, $leaseToken)) {
                    $this->repo->updateJobStatus($jobPublicId, $organizationId, 'rollback_failed', $leaseToken);
                    $this->repo->releaseLease($jobPublicId, $organizationId, $leaseToken);
                }
            } catch (\Throwable) {
            }
            throw $e;
        }
    }

    private function containerService(string $id): mixed { return $this->writer->service($id); }
    private function actor(array $job, int $organizationId): array
    {
        $actor = $this->repo->actor((int)($job['created_by_user_id'] ?? 0), $organizationId);
        if ($actor === null) throw new RuntimeException('ASANA_WORKSPACE_MEMBERSHIP_REVOKED');
        return $actor;
    }
}
