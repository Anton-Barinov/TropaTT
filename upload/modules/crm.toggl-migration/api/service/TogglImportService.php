<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Service;

use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;
use RuntimeException;

final class TogglImportService
{
    public function __construct(
        private readonly TogglMigrationRepository $repo,
        private readonly TogglClient $client,
        private readonly TogglCrawler $crawler,
        private readonly TogglTargetWriter $writer,
    ) {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) return;
        if (($job['mode'] ?? 'import') !== 'dry_run') {
            $owner = $this->repo->actor((int)($job['created_by_user_id'] ?? 0));
            if (empty($owner['is_root'])) throw new RuntimeException('TOGGL_ROOT_REQUIRED');
        }
        $connection = $this->repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) throw new RuntimeException('TOGGL_CONNECTION_NOT_FOUND');
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) throw new RuntimeException('TOGGL_CREDENTIAL_DECRYPT_FAILED');
        $this->client->setConnectionId((int)$connection['id']);
        $heartbeat = $leaseToken !== null ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken) : null;
        $cursorRaw = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $resume = is_array($cursorRaw) && ($cursorRaw['phase'] ?? '') === 'import' && $this->repo->itemCount((int)$job['id']) > 0 && str_starts_with((string)($job['current_step'] ?? ''), 'import_');
        if ($resume) {
            $crawl = ['resumed' => true, 'warnings' => []];
        } else {
            $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message' => 'Загрузка данных Toggl'], $leaseToken);
            $crawl = $this->crawler->crawl($job, $token, $heartbeat);
            if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('TOGGL_JOB_LEASE_LOST');
            $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Toggl source graph loaded.', $crawl);
            if (($job['mode'] ?? 'import') !== 'dry_run') $this->repo->updateCursor($jobPublicId, json_encode(['phase'=>'import','priority'=>0,'id'=>0], JSON_UNESCAPED_UNICODE), $leaseToken);
        }
        if (($job['mode'] ?? 'import') === 'dry_run') {
            $summary = ['crawled'=>$crawl, 'items'=>$this->repo->itemCounts((int)$job['id'])];
            $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'dry_run_complete', 100, $summary, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, 'completed', $leaseToken);
            return;
        }

        $actor = $this->repo->actor((int)$job['created_by_user_id']);
        $total = max(1, $this->repo->itemCount((int)$job['id']));
        $counts = $this->repo->itemCounts((int)$job['id']);
        $done = array_sum(array_map('intval', array_intersect_key($counts, array_flip(['imported','updated','skipped','failed']))));
        $warnings = (array)($crawl['warnings'] ?? []);
        $cursor = $resume ? $cursorRaw : ['phase'=>'import','priority'=>0,'id'=>0];
        $priority = max(0, (int)($cursor['priority'] ?? 0));
        $lastId = max(0, (int)($cursor['id'] ?? 0));

        while (($items = $this->repo->importItemsBatch((int)$job['id'], $priority, $lastId, 250)) !== []) {
            foreach ($items as $item) {
                if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('TOGGL_JOB_LEASE_LOST');
                $current = $this->repo->getJob($jobPublicId);
                $status = (string)($current['status'] ?? '');
                if (in_array($status, ['pausing','paused','cancelling','cancelled'], true)) {
                    if ($status === 'pausing') $this->repo->updateJobStatus($jobPublicId, 'paused', $leaseToken);
                    elseif ($status === 'cancelling') $this->repo->updateJobStatus($jobPublicId, 'cancelled', $leaseToken);
                    return;
                }
                $type = (string)$item['source_type'];
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                if ($type === 'project') $payload['_source_project_id'] = (string)($item['source_project_id'] ?? '');
                if ($type === 'task') {
                    $payload['_source_project_id'] = (string)($item['source_project_id'] ?? $payload['project_id'] ?? $payload['pid'] ?? '');
                    $payload['_source_parent_id'] = (string)($item['source_parent_id'] ?? $payload['parent_id'] ?? '');
                }
                if ($type === 'time_entry') {
                    $payload['_source_project_id'] = (string)($item['source_project_id'] ?? '');
                    $payload['_source_user_id'] = (string)($item['source_parent_id'] ?? '');
                }
                $mappingType = $type;
                $existingMapping = $this->repo->findMapping((int)$job['connection_id'], (string)$job['workspace_gid'], $mappingType, (string)$item['source_id']);
                if (empty($item['target_public_id']) && !empty($existingMapping['target_public_id'])) {
                    $item['target_public_id'] = (string)$existingMapping['target_public_id'];
                    $item['created_by_job'] = (int)($existingMapping['created_by_job_id'] ?? 0) === (int)$job['id'] ? 1 : 0;
                }
                try {
                    $result = match ($type) {
                        'client' => $this->writer->client($job, $payload, $actor),
                        'project' => $this->writer->project($job, $payload, $actor),
                        'tag' => $this->writer->tag($job, $payload),
                        'task' => $this->writer->task($job, $payload, $actor),
                        'time_entry' => $this->writer->timeEntry($job, $payload, $actor),
                        default => ['target_type'=>'','target_public_id'=>'','state'=>'skipped','warnings'=>['Unknown source item skipped.']],
                    };
                    $warnings = array_merge($warnings, (array)($result['warnings'] ?? []));
                    $target = (string)($result['target_public_id'] ?? '');
                    if ($target !== '') {
                        $this->repo->upsertMapping((int)$job['connection_id'], (string)$job['workspace_gid'], $mappingType, (string)$item['source_id'], [
                            'source_parent_id' => $item['source_parent_id'] ?: null,
                            'target_type' => $result['target_type'], 'target_public_id' => $target,
                            'source_checksum' => $item['checksum'] ?? null, 'target_checksum' => hash('sha256', $target),
                            'created_by_job_id' => (int)$job['id'],
                        ]);
                    }
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], [
                        'target_type'=>$result['target_type'], 'target_public_id'=>$target,
                        'created_by_job'=>($result['state'] === 'imported' ? 1 : (int)($item['created_by_job'] ?? 0)),
                        'status'=>$result['state'], 'error_code'=>null, 'error_message'=>null,
                    ]);
                } catch (\Throwable $e) {
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], [
                        'status'=>'failed', 'attempts'=>(int)($item['attempts'] ?? 0) + 1,
                        'error_code'=>'IMPORT_FAILED', 'error_message'=>'Не удалось импортировать элемент. Подробности доступны в логе.',
                    ]);
                    if ($type === 'time_entry' && in_array($e->getMessage(), ['TOGGL_TIME_ENTRY_USER_UNMAPPED', 'TOGGL_TIME_ENTRY_USER_FORBIDDEN'], true)) {
                        $this->repo->unresolved((int)$job['id'], $type, (string)$item['source_id'], $e->getMessage() === 'TOGGL_TIME_ENTRY_USER_FORBIDDEN' ? 'USER_FORBIDDEN' : 'USER_UNMAPPED', 'Toggl user could not be safely assigned to a CRM user.', $payload);
                    }

                    $this->repo->addLog((int)$job['id'], 'error', 'import_' . $type, 'Toggl item import failed.', ['source_type'=>$type,'source_id'=>$item['source_id'],'error_code'=>$e->getCode() ?: $e->getMessage()]);
                }
                ++$done;
                $priority = (int)($item['import_priority'] ?? $priority);
                $lastId = (int)$item['id'];
                $this->repo->updateProgress($jobPublicId, 'import_' . $type, min(99, ($done / $total) * 100), ['processed'=>$done,'total'=>$total,'warnings'=>count($warnings)], $leaseToken);
            }
            $this->repo->updateCursor($jobPublicId, json_encode(['phase'=>'import','priority'=>$priority,'id'=>$lastId], JSON_UNESCAPED_UNICODE), $leaseToken);
        }
        $summary = ['crawled'=>$crawl,'items'=>$this->repo->itemCounts((int)$job['id']),'warnings'=>array_values(array_unique($warnings))];
        $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, 'completed', 100, $summary, $leaseToken);
        $failed = (int)($summary['items']['failed'] ?? 0);
        $this->repo->updateJobStatus($jobPublicId, ($failed > 0 || $summary['warnings'] !== []) ? 'completed_with_warnings' : 'completed', $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Toggl migration completed.', ['failed'=>$failed,'warnings'=>count($summary['warnings'])]);
    }

    public function rollback(string $jobPublicId, array $actor): void
    {
        $job = $this->repo->beginRollback($jobPublicId);
        if (!$job) throw new RuntimeException('TOGGL_ROLLBACK_REQUIRES_TERMINAL_JOB');
        $lease = (string)($job['lease_token'] ?? '');
        // Target services authorize against the original import owner. The
        // controller already authorizes the rollback initiator separately.
        $actor = $this->repo->actor((int)$job['created_by_user_id']);
        $warnings = [];
        try {
            $cursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
            $before = is_array($cursor) ? max(1, (int)($cursor['before_id'] ?? PHP_INT_MAX)) : PHP_INT_MAX;
            while (($items = $this->repo->rollbackItemsBatch((int)$job['id'], $before, 250)) !== []) {
                foreach ($items as $item) {
                    if (!$this->repo->heartbeat($jobPublicId, $lease)) throw new RuntimeException('TOGGL_ROLLBACK_LEASE_LOST');
                    if ((int)($item['created_by_job'] ?? 0) !== 1 || empty($item['target_public_id'])) continue;
                    try {
                        $type = (string)$item['target_type'];
                        if ($this->repo->targetReferencedByOtherJob((int)$job['id'], $type, (string)$item['target_public_id'])) {
                            $warnings[] = (string)$item['source_id'];
                            $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status'=>'rollback_preserved_shared','error_code'=>'TARGET_SHARED_BY_OTHER_JOB','error_message'=>'Target preserved because another migration job still refers to it.']);
                            $this->repo->addLog((int)$job['id'], 'warning', 'rollback', 'Toggl target was preserved because another job still refers to it.', ['source_id'=>$item['source_id'],'target_type'=>$type]);
                            continue;
                        }
                        if ($type === 'worklog') {
                            $deleted = $this->writer->service('service.worklog')->delete((string)$item['target_public_id'], $actor);
                            if ($deleted !== true) throw new RuntimeException('TOGGL_WORKLOG_NOT_DELETED');
                        } else {
                            $serviceId = match ($type) { 'client'=>'service.client', 'project'=>'service.project', 'task'=>'service.task', 'tag'=>'service.tag', default=>'' };
                            if ($serviceId === '') continue;
                            $service = $this->writer->service($serviceId);
                            $deleted = $type === 'tag' ? $service->delete((string)$item['target_public_id']) : $service->delete((string)$item['target_public_id'], $actor);
                            if ($deleted !== true) throw new RuntimeException('TOGGL_TARGET_NOT_DELETED');
                        }
                        $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status'=>'rolled_back']);
                    } catch (\Throwable $e) {
                        $warnings[] = (string)$item['source_id'];
                        $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status'=>'rollback_failed','error_code'=>'ROLLBACK_FAILED','error_message'=>'Target could not be removed.']);
                        $this->repo->addLog((int)$job['id'], 'warning', 'rollback', 'Toggl target was not removed.', ['source_id'=>$item['source_id'],'error_code'=>$e->getMessage()]);
                    }
                }
                if ($warnings !== []) break;
                $before = min(array_map('intval', array_column($items, 'id')));
                $this->repo->updateCursor($jobPublicId, json_encode(['phase'=>'rollback','before_id'=>$before], JSON_UNESCAPED_UNICODE), $lease);
            }
            $warnings = array_values(array_unique($warnings));
            $this->repo->updateSummary($jobPublicId, ['rollback_warnings'=>$warnings], $lease);
            $this->repo->updateProgress($jobPublicId, 'rolled_back', 100, ['warnings'=>count($warnings)], $lease);
            $this->repo->updateJobStatus($jobPublicId, $warnings === [] ? 'rolled_back' : 'rolled_back_with_warnings', $lease);
            $this->repo->releaseLease($jobPublicId, $lease);
        } catch (\Throwable $e) {
            if ($this->repo->ownsLease($jobPublicId, $lease)) {
                try { $this->repo->updateJobStatus($jobPublicId, 'rollback_failed', $lease); } catch (\Throwable) { }
                $this->repo->releaseLease($jobPublicId, $lease);
            }
            throw $e;
        }
    }
}
