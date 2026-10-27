<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration\Service;

use Module\Crm\Bitrix24Migration\Repository\Bitrix24MigrationRepository;
use RuntimeException;

final class Bitrix24ImportService
{
    public function __construct(
        private readonly Bitrix24MigrationRepository $repo,
        private readonly Bitrix24Crawler $crawler,
        private readonly Bitrix24TargetWriter $writer,
    ) {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job || (int)($job['organization_id'] ?? 0) < 1) return;
        $this->repo->setOrganizationId((int)$job['organization_id']);
        if (!$this->repo->actor((int)$job['created_by_user_id'], (int)$job['organization_id'])) { $this->repo->updateJobStatus($jobPublicId, 'failed'); return; }
        if (!$job) return;
        $owner = $this->repo->actor((int)$job['created_by_user_id'], (int)($job['organization_id'] ?? 0));
        $heartbeat = $leaseToken !== null ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken) : null;
        $cursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $cursor = is_array($cursor) ? $cursor : [];
        $resume = ($cursor['phase'] ?? '') === 'import' && $this->repo->itemCounts((int)$job['id']) !== [];

        if ($resume) {
            $summary = (array)($job['summary'] ?? []);
            $crawl = (array)($summary['crawled'] ?? []);
            $crawl['resumed'] = true;
        } else {
            $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message' => 'Loading Bitrix24 collections'], $leaseToken);
            $crawl = $this->crawler->crawl($job, $heartbeat);
            $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Bitrix24 collections loaded.', $crawl);
            $this->repo->updateCursor($jobPublicId, ['phase' => 'import', 'priority' => 0, 'id' => 0], $leaseToken);
        }

        if (($job['mode'] ?? 'import') === 'dry_run') {
            $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id'])];
            $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'dry_run_complete', 100, $summary, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, 'completed_with_warnings', $leaseToken);
            return;
        }

        $actor = $owner;
        $counts = $this->repo->itemCounts((int)$job['id']);
        $done = 0;
        foreach (['imported', 'updated', 'skipped', 'failed'] as $status) $done += (int)($counts[$status] ?? 0);
        $total = max(1, array_sum($counts));
        $warnings = (array)($crawl['warnings'] ?? []);
        $priority = $resume ? max(0, (int)($cursor['priority'] ?? 0)) : 0;
        $lastId = $resume ? max(0, (int)($cursor['id'] ?? 0)) : 0;

        while (($items = $this->repo->importItemsBatch((int)$job['id'], $priority, $lastId, 100)) !== []) {
            foreach ($items as $item) {
                if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('BITRIX24_JOB_LEASE_LOST');
                $control = $this->repo->getJob($jobPublicId);
                $status = (string)($control['status'] ?? '');
                if (in_array($status, ['pausing', 'paused', 'cancelling', 'cancelled'], true)) {
                    if ($status === 'pausing') $this->repo->updateJobStatus($jobPublicId, 'paused', $leaseToken);
                    if ($status === 'cancelling') $this->repo->updateJobStatus($jobPublicId, 'cancelled', $leaseToken);
                    return;
                }

                $type = (string)$item['source_type'];
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                $payload['_source_id'] = (string)$item['source_id'];

                try {
                    $actor = $this->repo->actor((int)$job['created_by_user_id'], (int)$job['organization_id']);
                    if (!$actor) throw new RuntimeException('BITRIX24_OWNER_MEMBERSHIP_REVOKED');
                    $result = $this->writeItem($type, $job, $payload, $actor);
                    $warnings = array_merge($warnings, (array)($result['warnings'] ?? []));
                    $target = (string)($result['target_public_id'] ?? '');
                    if ($target !== '') {
                        $this->repo->upsertMapping((int)$job['connection_id'], $type, (string)$item['source_id'], [
                            'source_parent_id' => $item['source_parent_id'] ?: null,
                            'target_type' => $result['target_type'],
                            'target_public_id' => $target,
                            'source_checksum' => $item['checksum'] ?? null,
                            'created_by_job_id' => (int)$job['id'],
                        ]);
                    }
                    if ($target === '' && ($result['state'] ?? '') === 'skipped') {
                        $this->repo->unresolved((int)$job['id'], $type, (string)$item['source_id'], 'UNSUPPORTED_ENTITY', implode(' ', (array)($result['warnings'] ?? ['No verified CRM mapping exists.'])), $payload);
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
                        'error_message' => 'Bitrix24 item import failed. Check the migration log.',
                    ]);
                    $this->repo->unresolved((int)$job['id'], $type, (string)$item['source_id'], $code, 'Bitrix24 entity could not be imported.', $payload);
                    $this->repo->addLog((int)$job['id'], 'error', 'import_' . $type, 'Bitrix24 item import failed.', ['source_type' => $type, 'source_id' => $item['source_id'], 'error_code' => $code]);
                }

                ++$done;
                $priority = (int)($item['import_priority'] ?? $priority);
                $lastId = (int)$item['id'];
                $this->repo->updateProgress($jobPublicId, 'import_' . $type, min(99, ($done / $total) * 100), ['processed' => $done, 'total' => $total, 'warnings' => count($warnings)], $leaseToken);
            }
            $this->repo->updateCursor($jobPublicId, ['phase' => 'import', 'priority' => $priority, 'id' => $lastId], $leaseToken);
        }

        $control = $this->repo->getJob($jobPublicId);
        if (in_array((string)($control['status'] ?? ''), ['pausing', 'cancelling'], true)) {
            $this->repo->updateJobStatus($jobPublicId, (string)$control['status'] === 'pausing' ? 'paused' : 'cancelled', $leaseToken);
            return;
        }
        $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id']), 'warnings' => array_values(array_unique($warnings))];
        $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, 'completed', 100, $summary, $leaseToken);
        $finalStatus = ((int)($summary['items']['failed'] ?? 0) > 0 || $summary['warnings'] !== []) ? 'completed_with_warnings' : 'completed';
        $this->repo->updateJobStatus($jobPublicId, $finalStatus, $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Bitrix24 migration completed.', ['failed' => $summary['items']['failed'] ?? 0, 'warnings' => count($summary['warnings'])]);
    }

    private function writeItem(string $type, array $job, array $payload, array $actor): array
    {
        return match ($type) {
            'department' => $this->writer->department($job, $payload, $actor),
            'user' => $this->writer->user($job, $payload),
            'project', 'task_project' => $this->writer->project($job, $payload, $actor),
            'company' => $this->writer->company($job, $payload, $actor),
            'contact' => $this->writer->contact($job, $payload, $actor),
            'lead' => $this->writer->lead($job, $payload, $actor),
            'task', 'subtask' => $this->writer->task($job, $payload, $actor),
            'deal' => $this->writer->deal($job, $payload, $actor),
            'invoice' => $this->writer->invoice($job, $payload, $actor),
            'quote' => $this->writer->quote($job, $payload, $actor),
            'product' => $this->writer->product($job, $payload, $actor),
            'product_row' => $this->writer->productRow($job, $payload, $actor),
            'timeline_comment' => $this->writer->timelineComment($job, $payload, $actor),
            'comment' => $this->writer->comment($job, $payload, $actor),
            'file' => $this->writer->file($job, $payload, $actor),
            'activity' => $this->writer->activity($job, $payload, $actor),
            'event' => $this->writer->event($job, $payload, $actor),
            default => $this->writer->unsupported($job, $payload, $type),
        };
    }

    /**
     * Process one bounded rollback page. The persisted cursor and fenced lease
     * make the operation safe to resume after a browser/server timeout.
     *
     * @return array<string,mixed>
     */
    public function rollback(string $jobPublicId, array $actor): array
    {
        $job = $this->repo->claimRollbackJob($jobPublicId, 240);
        $jobId = (int)$job['id'];
        $lease = (string)$job['rollback_lease_token'];
        $cursor = (array)$job['rollback_cursor'];
        $organizationId = (int)($job['organization_id'] ?? 0);
        if ($organizationId < 1 || (int)($actor['organization_id'] ?? 0) !== $organizationId) {
            $this->repo->releaseRollbackLease($jobPublicId, $lease);
            throw new RuntimeException('BITRIX24_WORKSPACE_REQUIRED');
        }

        $total = $this->repo->rollbackTargetTotal($jobId);
        $items = $this->repo->rollbackItemsBatch($jobId, (int)$cursor['priority'], (int)$cursor['before_id'], 50);
        $processed = 0;
        $warnings = [];

        foreach ($items as $item) {
            if (!$this->repo->heartbeat($jobPublicId, $lease, 240)) throw new RuntimeException('BITRIX24_JOB_LEASE_LOST');
            $type = (string)($item['source_type'] ?? '');
            $sourceId = (string)($item['source_id'] ?? '');
            $targetType = (string)($item['target_type'] ?? '');
            $targetId = (string)($item['target_public_id'] ?? '');
            $serviceId = match ($targetType) {
                'project' => 'service.project', 'task' => 'service.task', 'comment' => 'service.comment', 'file' => 'service.file',
                'calendar_event' => 'service.calendar', 'company' => 'service.company', 'contact' => 'service.contact', 'counterparty' => 'service.counterparty',
                'department' => 'service.department', 'tag' => 'service.tag', default => '',
            };

            if ($serviceId === '') {
                $this->repo->markRollbackItem($jobId, $type, $sourceId, 'rollback_failed', 'ROLLBACK_TARGET_TYPE_UNSUPPORTED', 'No safe CRM deletion service exists for this target type.', $lease);
                $warnings[] = $sourceId;
            } elseif ($this->repo->rollbackHasOtherReference($jobId, $targetType, $targetId)) {
                $this->repo->markRollbackItem($jobId, $type, $sourceId, 'rollback_preserved_shared', 'SHARED_TARGET', 'Target is still referenced by another migration job and was preserved.', $lease);
                $warnings[] = $sourceId;
            } else {
                try {
                    $service = $this->writer->service($serviceId);
                    $deleted = $targetType === 'tag'
                        ? $service->delete($targetId)
                        : ($targetType === 'calendar_event' ? $service->deleteEvent($targetId, $actor) : $service->delete($targetId, $actor));
                    if ($deleted !== true) throw new RuntimeException('BITRIX24_ROLLBACK_DELETE_FAILED');
                    $this->repo->markMappingRolledBack((int)$job['connection_id'], $type, $sourceId);
                    $this->repo->markRollbackItem($jobId, $type, $sourceId, 'rolled_back', null, null, $lease);
                } catch (\Throwable $error) {
                    if ($error->getMessage() === 'BITRIX24_JOB_LEASE_LOST') throw $error;
                    $this->repo->markRollbackItem($jobId, $type, $sourceId, 'rollback_failed', $this->errorCode($error->getMessage()), 'Target could not be removed; retry rollback after reviewing access and dependencies.', $lease);
                    $warnings[] = $sourceId;
                }
            }

            $cursor = [
                'phase' => 'rollback',
                'priority' => (int)($item['rollback_priority'] ?? 80),
                'before_id' => (int)$item['id'],
            ];
            $this->repo->saveRollbackCursor($jobPublicId, $cursor, $lease);
            ++$processed;
        }

        $remaining = $this->repo->rollbackTargetCount($jobId);
        $total = max($total, $this->repo->rollbackTargetTotal($jobId));
        $done = max(0, $total - $remaining);
        $percent = $total === 0 ? 100 : min(99.99, ($done / $total) * 100);
        $summary = (array)($job['summary'] ?? []);
        $rollbackSummary = (array)($summary['rollback'] ?? []);
        $rollbackSummary['processed_total'] = (int)($rollbackSummary['processed_total'] ?? 0) + $processed;
        $rollbackSummary['remaining'] = $remaining;
        $rollbackSummary['last_batch_processed'] = $processed;
        $rollbackSummary['last_batch_warnings'] = array_values(array_unique($warnings));
        $summary['rollback'] = $rollbackSummary;
        $progress = ['phase' => 'rollback', 'processed' => $done, 'total' => $total, 'remaining' => $remaining, 'last_batch_processed' => $processed];

        $next = $this->repo->rollbackItemsBatch($jobId, (int)$cursor['priority'], (int)$cursor['before_id'], 1);
        if ($next !== []) {
            $this->repo->updateSummary($jobPublicId, $summary, $lease);
            $this->repo->saveRollbackProgress($jobPublicId, $lease, $progress, $percent);
            $this->repo->releaseRollbackLease($jobPublicId, $lease);
            return ['status' => 'rolling_back', 'processed' => $processed, 'remaining' => $remaining, 'progress_percent' => $percent, 'cursor' => $cursor];
        }

        // Final success is based on a fresh workspace-scoped count, not on an
        // empty page alone. Preserved/failed targets produce warning status.
        $remaining = $this->repo->rollbackTargetCount($jobId);
        $preserved = $this->repo->rollbackPreservedCount($jobId);
        $terminal = ($remaining === 0 && $preserved === 0) ? 'rolled_back' : 'rolled_back_with_warnings';
        $progress['remaining'] = $remaining;
        $progress['preserved_shared'] = $preserved;
        $progress['processed'] = max(0, $total - $remaining);
        $summary['rollback']['remaining'] = $remaining;
        $summary['rollback']['preserved_shared'] = $preserved;
        $this->repo->finishRollbackJob($jobPublicId, $lease, $terminal, $summary, $progress);
        return ['status' => $terminal, 'processed' => $processed, 'remaining' => $remaining, 'preserved_shared' => $preserved, 'progress_percent' => 100, 'cursor' => $cursor];
    }

    private function errorCode(string $message): string
    {
        $code = strtoupper(trim(strtok($message, ':') ?: ''));
        return preg_match('/^[A-Z0-9_]{3,64}$/', $code) === 1 ? $code : 'BITRIX24_IMPORT_FAILED';
    }
}
