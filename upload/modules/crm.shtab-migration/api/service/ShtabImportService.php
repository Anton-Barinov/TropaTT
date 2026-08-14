<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Service;

use Module\Crm\ShtabMigration\Repository\ShtabMigrationRepository;
use RuntimeException;

final class ShtabImportService
{
    public function __construct(
        private readonly ShtabMigrationRepository $repo,
        private readonly ShtabExportCrawler $crawler,
        private readonly ShtabTargetWriter $writer,
    ) {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) return;

        $owner = $this->repo->actor((int)($job['created_by_user_id'] ?? 0));
        if (($job['mode'] ?? 'import') !== 'dry_run' && empty($owner['is_root'])) {
            throw new RuntimeException('SHTAB_ROOT_REQUIRED');
        }

        $heartbeat = $leaseToken !== null
            ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken)
            : null;
        $cursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $cursor = is_array($cursor) ? $cursor : [];
        $resumeImport = ($job['mode'] ?? 'import') !== 'dry_run'
            && ($cursor['phase'] ?? '') === 'import'
            && $this->repo->itemCount((int)$job['id']) > 0
            && str_starts_with((string)($job['current_step'] ?? ''), 'import_');

        if ($resumeImport) {
            $previousSummary = (array)($job['summary'] ?? []);
            $crawl = (array)($previousSummary['crawled'] ?? []);
            $crawl['resumed'] = true;
            $crawl['warnings'] = array_values(array_unique((array)($crawl['warnings'] ?? [])));
        } else {
            $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message' => 'Reading Shtab export file'], $leaseToken);
            $sourcePath = (string)($job['source_file_path'] ?? '');
            if (is_file($sourcePath)) {
                $crawl = $this->crawler->crawl($job, $heartbeat);
                $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Shtab export parsed.', $crawl);
            } else {
                $counts = $this->repo->itemCounts((int)$job['id']);
                $existingItems = array_sum($counts);
                $previous = (array)($job['summary'] ?? []);
                $crawl = (array)($previous['crawled'] ?? []);
                if ($existingItems === 0) {
                    throw new RuntimeException('SHTAB_SOURCE_FILE_NOT_FOUND');
                }
                $crawl['items'] = (int)($crawl['items'] ?? $existingItems);
                $crawl['warnings'] = array_values(array_unique(array_merge(
                    (array)($crawl['warnings'] ?? []),
                    ['Source export file is unavailable; queued items from the previous crawl are being resumed.'],
                )));
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl', 'Source file is unavailable; resumed stored export items.', ['items' => $existingItems]);
            }
            if (($job['mode'] ?? 'import') !== 'dry_run') {
                $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'import', 'priority' => 0, 'id' => 0], JSON_UNESCAPED_UNICODE), $leaseToken);
            }
        }

        if (($job['mode'] ?? 'import') === 'dry_run') {
            $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id'])];
            $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'dry_run_complete', 100, $summary, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, 'completed_with_warnings', $leaseToken);
            $this->cleanup($job);
            return;
        }

        $actor = $owner;
        $total = max(1, $this->repo->itemCount((int)$job['id']));
        $counts = $this->repo->itemCounts((int)$job['id']);
        $done = 0;
        foreach (['imported', 'updated', 'skipped', 'failed'] as $status) {
            $done += (int)($counts[$status] ?? 0);
        }
        $warnings = (array)($crawl['warnings'] ?? []);
        $priority = $resumeImport ? max(0, (int)($cursor['priority'] ?? 0)) : 0;
        $lastId = $resumeImport ? max(0, (int)($cursor['id'] ?? 0)) : 0;

        while (($items = $this->repo->importItemsBatch((int)$job['id'], $priority, $lastId, 100)) !== []) {
            foreach ($items as $item) {
                if ($heartbeat !== null && !$heartbeat()) {
                    throw new RuntimeException('SHTAB_JOB_LEASE_LOST');
                }
                $current = $this->repo->getJob($jobPublicId);
                $status = (string)($current['status'] ?? '');
                if (in_array($status, ['pausing', 'paused', 'cancelling', 'cancelled'], true)) {
                    if ($status === 'pausing') $this->repo->updateJobStatus($jobPublicId, 'paused', $leaseToken);
                    if ($status === 'cancelling') {
                        $this->repo->updateJobStatus($jobPublicId, 'cancelled', $leaseToken);
                        $this->cleanup($job);
                    }
                    return;
                }

                $type = (string)$item['source_type'];
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                $payload['_source_id'] = (string)$item['source_id'];

                try {
                    $result = match ($type) {
                        'project' => $this->writer->project($job, $payload, $actor),
                        'tag' => $this->writer->tag($job, $payload),
                        'user' => $this->writer->user($job, $payload),
                        'task', 'subtask' => $this->writer->task($job, $payload, $actor),
                        'comment' => $this->writer->comment($job, $payload, $actor),
                        'workspace', 'organization', 'team', 'contact', 'deal', 'event', 'file' => $this->writer->unsupported($job, $payload, $type),
                        default => $this->writer->unsupported($job, $payload, $type),
                    };
                    $warnings = array_merge($warnings, (array)($result['warnings'] ?? []));
                    $target = (string)($result['target_public_id'] ?? '');
                    $mapType = $type === 'subtask' ? 'task' : $type;
                    if ($target !== '') {
                        $this->repo->upsertMapping((int)$job['connection_id'], $mapType, (string)$item['source_id'], [
                            'source_parent_id' => $item['source_parent_id'] ?: null,
                            'target_type' => $result['target_type'],
                            'target_public_id' => $target,
                            'source_checksum' => $item['checksum'] ?? null,
                            'created_by_job_id' => (int)$job['id'],
                        ]);
                    }
                    if ($target === '' && ($result['state'] ?? '') === 'skipped') {
                        $this->repo->unresolved(
                            (int)$job['id'],
                            $type,
                            (string)$item['source_id'],
                            'UNSUPPORTED_ENTITY',
                            implode(' ', (array)($result['warnings'] ?? ['No verified CRM mapping exists for this entity.'])),
                            $payload,
                        );
                    }
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], [
                        'target_type' => $result['target_type'],
                        'target_public_id' => $target,
                        'created_by_job' => ($result['state'] ?? '') === 'imported' ? 1 : (int)($item['created_by_job'] ?? 0),
                        'status' => $result['state'],
                        'error_code' => null,
                        'error_message' => null,
                        'payload_json' => $payload,
                    ]);
                } catch (\Throwable $error) {
                    $code = $this->errorCode($error->getMessage());
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], [
                        'status' => 'failed',
                        'attempts' => (int)($item['attempts'] ?? 0) + 1,
                        'error_code' => $code,
                        'error_message' => 'Item import failed. Check the migration log.',
                    ]);
                    $this->repo->unresolved((int)$job['id'], $type, (string)$item['source_id'], $code, 'Shtab entity could not be mapped or imported.', $payload);
                    $this->repo->addLog((int)$job['id'], 'error', 'import_' . $type, 'Shtab item import failed.', [
                        'source_type' => $type,
                        'source_id' => $item['source_id'],
                        'error_code' => $code,
                    ]);
                }

                ++$done;
                $priority = (int)($item['import_priority'] ?? $priority);
                $lastId = (int)$item['id'];
                $this->repo->updateProgress(
                    $jobPublicId,
                    'import_' . $type,
                    min(99, ($done / $total) * 100),
                    ['processed' => $done, 'total' => $total, 'warnings' => count($warnings)],
                    $leaseToken,
                );
            }
            // Persist only after a complete batch. A worker crash retries at
            // most the current batch, while completed items remain idempotent.
            $this->repo->updateCursor($jobPublicId, json_encode([
                'phase' => 'import',
                'priority' => $priority,
                'id' => $lastId,
            ], JSON_UNESCAPED_UNICODE), $leaseToken);
        }

        // A reclaimed lease may belong to a job whose pause/cancel request was
        // made before the previous worker crashed. Handle it even when there
        // are no pending rows, otherwise the job could be finalized as done.
        $control = $this->repo->getJob($jobPublicId);
        $controlStatus = (string)($control['status'] ?? '');
        if ($controlStatus === 'pausing') {
            $this->repo->updateJobStatus($jobPublicId, 'paused', $leaseToken);
            return;
        }
        if ($controlStatus === 'cancelling') {
            $this->repo->updateJobStatus($jobPublicId, 'cancelled', $leaseToken);
            $this->cleanup($job);
            return;
        }

        $summary = [
            'crawled' => $crawl,
            'items' => $this->repo->itemCounts((int)$job['id']),
            'warnings' => array_values(array_unique($warnings)),
        ];
        $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, 'completed', 100, $summary, $leaseToken);
        $failed = (int)($summary['items']['failed'] ?? 0);
        $finalStatus = $failed > 0 || $summary['warnings'] !== [] ? 'completed_with_warnings' : 'completed';
        $this->repo->updateJobStatus($jobPublicId, $finalStatus, $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Shtab export migration completed.', [
            'failed' => $failed,
            'warnings' => count($summary['warnings']),
        ]);
        if (in_array($finalStatus, ['completed', 'completed_with_warnings'], true)) {
            // All parsed rows are persisted in job_items, so a retry does not
            // need the original upload. Remove the private temporary file for
            // every successful terminal import, including warning-only jobs.
            $this->cleanup($job);
        }
    }

    public function rollback(string $jobPublicId, array $actor): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) throw new RuntimeException('SHTAB_JOB_NOT_FOUND');
        if (!in_array((string)($job['status'] ?? ''), ['completed', 'completed_with_warnings', 'failed', 'cancelled', 'rolled_back', 'rolled_back_with_warnings'], true)) {
            throw new RuntimeException('SHTAB_ROLLBACK_JOB_NOT_FINISHED');
        }
        $warnings = [];
        $cursor = PHP_INT_MAX;
        while (($items = $this->repo->rollbackItems((int)$job['id'], $cursor, 500)) !== []) {
            $lastId = $cursor;
            foreach ($items as $item) {
                $lastId = min($lastId, (int)$item['id']);
                if ((int)($item['created_by_job'] ?? 0) !== 1 || empty($item['target_public_id'])) continue;
                $targetType = (string)$item['target_type'];
                $serviceId = match ($targetType) {
                    'project' => 'service.project',
                    'task' => 'service.task',
                    'tag' => 'service.tag',
                    'comment' => 'service.comment',
                    'checklist', 'checklist_item' => 'service.checklist',
                    'worklog' => 'service.worklog',
                    default => '',
                };
                if ($serviceId === '') continue;
                try {
                    $service = $this->writer->service($serviceId);
                    $deleted = match ($targetType) {
                        'tag' => $service->delete((string)$item['target_public_id']),
                        'checklist_item' => $service->deleteItem((string)$item['target_public_id'], $actor),
                        default => $service->delete((string)$item['target_public_id'], $actor),
                    };
                    if ($deleted !== true) throw new RuntimeException('SHTAB_ROLLBACK_DELETE_FAILED');
                    $mappingType = (string)$item['source_type'] === 'subtask' ? 'task' : (string)$item['source_type'];
                    $this->repo->markMappingRolledBack((int)$job['connection_id'], $mappingType, (string)$item['source_id']);
                    $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'rolled_back']);
                } catch (\Throwable) {
                    $warnings[] = (string)$item['source_id'];
                    $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'rollback_failed', 'error_code' => 'ROLLBACK_FAILED']);
                }
            }
            $cursor = $lastId;
        }
        $summary = (array)($job['summary'] ?? []);
        $summary['rollback_warnings'] = array_values(array_unique($warnings));
        $this->repo->updateSummary($jobPublicId, $summary);
        $this->repo->updateProgress($jobPublicId, 'rolled_back', 100, ['warnings' => count($warnings)]);
        $this->repo->updateJobStatus($jobPublicId, $warnings === [] ? 'rolled_back' : 'rolled_back_with_warnings');
        $this->cleanup($job);
    }

    private function errorCode(string $message): string
    {
        return str_contains($message, 'PARENT_TASK')
            ? 'PARENT_TASK_NOT_READY'
            : (str_contains($message, 'PROJECT_NOT_READY')
                ? 'PROJECT_NOT_READY'
                : (str_contains($message, 'SOURCE_ID_COLLISION') ? 'SOURCE_ID_COLLISION' : 'IMPORT_FAILED'));
    }

    private function cleanup(array $job): void
    {
        $path = (string)($job['source_file_path'] ?? '');
        if ($path !== '' && is_file($path)) @unlink($path);
    }
}
