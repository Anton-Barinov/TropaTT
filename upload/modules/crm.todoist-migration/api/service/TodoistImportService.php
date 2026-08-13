<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Service;

use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;
use RuntimeException;

final class TodoistImportService
{
    public function __construct(
        private readonly TodoistMigrationRepository $repo,
        private readonly TodoistClient $client,
        private readonly TodoistCrawler $crawler,
        private readonly TodoistTargetWriter $writer
    ) {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) return;
        $connection = $this->repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) throw new RuntimeException('TODOIST_CONNECTION_NOT_FOUND');
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) throw new RuntimeException('TODOIST_CREDENTIAL_DECRYPT_FAILED');
        $this->client->setConnectionId((int)$connection['id']);
        $this->configureOAuthRefresh($connection, $token);
        $heartbeat = $leaseToken !== null ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken) : null;
        $existing = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $existing = is_array($existing) ? $existing : [];
        $sourceScope = (array)($job['source_scope'] ?? []);
        $configuredMaxTasks = max(0, (int)($sourceScope['max_tasks'] ?? 0));
        $alreadyCrawledTasks = max(0, (int)($existing['tasks_total'] ?? 0));
        $remainingTasks = $configuredMaxTasks > 0 ? max(0, $configuredMaxTasks - $alreadyCrawledTasks) : 0;
        if ($configuredMaxTasks > 0) $job['source_scope']['max_tasks'] = $remainingTasks;
        $resumeImport = ($job['mode'] ?? 'import') !== 'dry_run'
            && ($existing['phase'] ?? '') === 'import'
            && $this->repo->itemCount((int)$job['id']) > 0
            && str_starts_with((string)($job['current_step'] ?? ''), 'import_');

        if (($existing['phase'] ?? '') === 'crawl' && !empty($existing['after_project_id'])) {
            $job['source_scope']['_after_project_id'] = (string)$existing['after_project_id'];
        }

        $crawl = $resumeImport ? ['resumed' => true, 'warnings' => [], 'crawl_complete' => true] : null;
        if ($crawl === null) {
            $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message' => 'Loading Todoist source graph'], $leaseToken);
            $crawl = $remainingTasks === 0 && $configuredMaxTasks > 0
                ? ['crawl_complete' => true, 'tasks' => 0, 'tasks_total' => $alreadyCrawledTasks, 'warnings' => []]
                : $this->crawler->crawl($job, $token, $heartbeat);
            if ($configuredMaxTasks > 0) $crawl['tasks_total'] = $alreadyCrawledTasks + (int)($crawl['tasks'] ?? 0);
            if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('TODOIST_JOB_LEASE_LOST');
            $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'Todoist source graph batch loaded.', $crawl);

            if (($job['mode'] ?? 'import') !== 'dry_run' && ($crawl['crawl_complete'] ?? true) === false) {
                $after = (string)($crawl['last_project_id'] ?? '');
                if ($after === '') throw new RuntimeException('TODOIST_CRAWL_CHECKPOINT_FAILED');
                $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'crawl', 'after_project_id' => $after, 'tasks_total' => (int)($crawl['tasks_total'] ?? $alreadyCrawledTasks)], JSON_UNESCAPED_UNICODE), $leaseToken);
                $this->repo->updateProgress($jobPublicId, 'crawl_checkpoint', min(95, (float)($crawl['projects'] ?? 0)), $crawl, $leaseToken);
                // requestStatus intentionally performs the state transition without
                // the lease assertion; the worker releases the current lease next.
                if (!$this->repo->requestStatus($jobPublicId, 'queued')) throw new RuntimeException('TODOIST_CRAWL_REQUEUE_FAILED');
                return;
            }
            if (($job['mode'] ?? 'import') !== 'dry_run') {
                $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'import', 'priority' => 0, 'id' => 0], JSON_UNESCAPED_UNICODE), $leaseToken);
            }
        }

        if (($job['mode'] ?? 'import') === 'dry_run') {
            $this->repo->updateSummary($jobPublicId, ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id'])], $leaseToken);
            $this->repo->updateProgress($jobPublicId, 'dry_run_complete', 100, $crawl, $leaseToken);
            $this->repo->updateJobStatus($jobPublicId, 'completed_with_warnings', $leaseToken);
            return;
        }

        $actor = $this->repo->actor((int)$job['created_by_user_id']);
        $total = max(1, $this->repo->itemCount((int)$job['id']));
        $counts = $this->repo->itemCounts((int)$job['id']);
        $done = (int)($counts['imported'] ?? 0) + (int)($counts['updated'] ?? 0) + (int)($counts['skipped'] ?? 0) + (int)($counts['failed'] ?? 0);
        $warnings = (array)($crawl['warnings'] ?? []);
        $cursor = $resumeImport ? $existing : ['phase' => 'import', 'priority' => 0, 'id' => 0];
        $priority = max(0, (int)($cursor['priority'] ?? 0));
        $lastId = max(0, (int)($cursor['id'] ?? 0));

        while (($items = $this->repo->importItemsBatch((int)$job['id'], $priority, $lastId, 100)) !== []) {
            foreach ($items as $item) {
                if ($leaseToken !== null && !$this->repo->heartbeat($jobPublicId, $leaseToken)) throw new RuntimeException('TODOIST_JOB_LEASE_LOST');
                $current = $this->repo->getJob($jobPublicId);
                $status = (string)($current['status'] ?? '');
                if (in_array($status, ['pausing', 'paused', 'cancelling', 'cancelled'], true)) {
                    if ($status === 'pausing') $this->repo->updateJobStatus($jobPublicId, 'paused', $leaseToken);
                    if ($status === 'cancelling') $this->repo->updateJobStatus($jobPublicId, 'cancelled', $leaseToken);
                    return;
                }

                $type = (string)$item['source_type'];
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                if (in_array($type, ['project', 'section', 'task', 'subtask', 'comment', 'attachment'], true)) $payload['_source_project_id'] = (string)($item['source_project_id'] ?? $payload['_source_project_id'] ?? '');
                if (in_array($type, ['task', 'subtask'], true)) {
                    $payload['_source_parent_id'] = (string)($item['source_parent_id'] ?? $payload['parent_id'] ?? '');
                    $mapType = 'task';
                } else {
                    $mapType = $type;
                }
                if ($type === 'comment') $payload['_source_task_id'] = isset($payload['_source_task_id']) && $payload['_source_task_id'] !== null ? (string)$payload['_source_task_id'] : ((isset($payload['task_id']) && $payload['task_id'] !== '') ? (string)$payload['task_id'] : '');
                if ($type === 'attachment') {
                    $payload['_source_attachment_id'] = (string)($item['source_id'] ?? $payload['_source_attachment_id'] ?? $payload['id'] ?? '');
                    $payload['_source_task_id'] = (string)($payload['_source_task_id'] ?? '');
                }
                $existingMap = $this->repo->findMapping((int)$job['connection_id'], $mapType, (string)$item['source_id']);
                if (empty($item['target_public_id']) && !empty($existingMap['target_public_id'])) {
                    $item['target_public_id'] = $existingMap['target_public_id'];
                    $item['created_by_job'] = (int)($existingMap['created_by_job_id'] ?? 0) === (int)$job['id'] ? 1 : 0;
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], ['target_type' => $existingMap['target_type'] ?? null, 'target_public_id' => $item['target_public_id'], 'created_by_job' => $item['created_by_job']]);
                }

                try {
                    $result = match ($type) {
                        'project' => $this->writer->project($job, $payload, $actor),
                        'section' => $this->writer->section($job, $payload, $actor),
                        'label' => $this->writer->label($job, $payload),
                        'task', 'subtask' => $this->writer->task($job, $payload, $actor),
                        'comment' => $this->writer->comment($job, $payload, $actor),
                        'attachment' => $this->writer->attachment($job, $payload, $actor, $token, max(1, (int)($job['target_options']['max_attachment_size_mb'] ?? 20)) * 1024 * 1024),
                        default => ['target_type' => '', 'target_public_id' => '', 'state' => 'skipped', 'warnings' => []],
                    };
                    $warnings = array_merge($warnings, (array)($result['warnings'] ?? []));
                    $target = (string)($result['target_public_id'] ?? '');
                    if ($target !== '') $this->repo->upsertMapping((int)$job['connection_id'], $mapType, (string)$item['source_id'], ['source_parent_id' => $item['source_parent_id'] ?: null, 'target_type' => $result['target_type'], 'target_public_id' => $target, 'source_checksum' => $item['checksum'] ?? null, 'target_checksum' => hash('sha256', $target), 'created_by_job_id' => (int)$job['id']]);
                    $persistedPayload = array_merge($payload, (array)($result['rollback_payload'] ?? []));
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], ['target_type' => $result['target_type'], 'target_public_id' => $target, 'created_by_job' => $result['state'] === 'imported' ? 1 : (int)($item['created_by_job'] ?? 0), 'status' => $result['state'], 'error_code' => null, 'error_message' => null, 'payload_json' => $persistedPayload]);
                } catch (\Throwable $e) {
                    $this->repo->upsertItem((int)$job['id'], $type, (string)$item['source_id'], ['status' => 'failed', 'attempts' => (int)($item['attempts'] ?? 0) + 1, 'error_code' => 'IMPORT_FAILED', 'error_message' => 'Item import failed. Check the migration log.']);
                    $this->repo->addLog((int)$job['id'], 'error', 'import_' . $type, 'Source item import failed.', ['source_type' => $type, 'source_id' => $item['source_id'], 'error_code' => $e->getCode() ?: 'TODOIST_IMPORT_ERROR']);
                }
                ++$done;
                $priority = (int)($item['import_priority'] ?? $priority);
                $lastId = (int)$item['id'];
                $this->repo->updateProgress($jobPublicId, 'import_' . $type, min(99, ($done / $total) * 100), ['processed' => $done, 'total' => $total, 'warnings' => count($warnings)], $leaseToken);
            }
            $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'import', 'priority' => $priority, 'id' => $lastId], JSON_UNESCAPED_UNICODE), $leaseToken);
        }

        $summary = ['crawled' => $crawl, 'items' => $this->repo->itemCounts((int)$job['id']), 'warnings' => array_values(array_unique($warnings))];
        $this->repo->updateSummary($jobPublicId, $summary, $leaseToken);
        $this->repo->updateProgress($jobPublicId, 'completed', 100, $summary, $leaseToken);
        $failed = (int)($summary['items']['failed'] ?? 0);
        $this->repo->updateJobStatus($jobPublicId, $failed > 0 || $summary['warnings'] !== [] ? 'completed_with_warnings' : 'completed', $leaseToken);
        $this->repo->addLog((int)$job['id'], 'info', 'completed', 'Todoist migration completed.', ['failed' => $failed, 'warnings' => count($summary['warnings'])]);
    }

    private function configureOAuthRefresh(array &$connection, string &$token): void
    {
        if ((string)($connection['auth_type'] ?? '') !== 'oauth2' || empty($connection['refresh_token_encrypted'])) return;
        $this->client->setTokenRefreshHandler(function (string $currentToken) use (&$connection, &$token): ?string {
            $clientId = EncryptionService::decrypt((string)($connection['client_id_encrypted'] ?? ''));
            $clientSecret = EncryptionService::decrypt((string)($connection['client_secret_encrypted'] ?? ''));
            $refreshToken = EncryptionService::decrypt((string)($connection['refresh_token_encrypted'] ?? ''));
            if ($clientId === null || $clientSecret === null || $refreshToken === null) return null;
            $tokens = $this->client->refreshAccessToken($clientId, $clientSecret, $refreshToken);
            $token = (string)$tokens['access_token'];
            $newRefresh = !empty($tokens['refresh_token']) ? (string)$tokens['refresh_token'] : $refreshToken;
            $encryptedAccess = EncryptionService::encrypt($token);
            $encryptedRefresh = EncryptionService::encrypt($newRefresh);
            $this->repo->updateConnectionTokens((string)$connection['public_id'], $encryptedAccess, $encryptedRefresh);
            $connection['access_token_encrypted'] = $encryptedAccess;
            $connection['refresh_token_encrypted'] = $encryptedRefresh;
            return $token;
        });
    }

    public function rollback(string $jobPublicId, array $actor): void
    {
        $job = $this->repo->beginRollback($jobPublicId);
        if (!$job) throw new RuntimeException('TODOIST_ROLLBACK_REQUIRES_TERMINAL_JOB');
        $lease = (string)($job['lease_token'] ?? '');
        $warnings = [];
        try {
            $cursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
            $before = is_array($cursor) ? max(1, (int)($cursor['before_id'] ?? PHP_INT_MAX)) : PHP_INT_MAX;
            while (($items = $this->repo->rollbackItemsBatch((int)$job['id'], $before, 100)) !== []) {
                $batchWarning = false;
                foreach ($items as $item) {
                    if (!$this->repo->heartbeat($jobPublicId, $lease)) throw new RuntimeException('TODOIST_ROLLBACK_LEASE_LOST');
                    $itemPayload = json_decode((string)($item['payload_json'] ?? '{}'), true);
                    $itemPayload = is_array($itemPayload) ? $itemPayload : [];
                    $isProjectCommentMutation = (string)$item['source_type'] === 'comment' && (string)$item['target_type'] === 'project' && array_key_exists('project_description_before', $itemPayload);
                    if (!$isProjectCommentMutation && ((int)($item['created_by_job'] ?? 0) !== 1 || empty($item['target_public_id']))) continue;
                    try {
                        if ($isProjectCommentMutation) {
                            $restored = $this->writer->service('service.project')->update((string)$item['target_public_id'], ['description' => (string)$itemPayload['project_description_before']], $actor);
                            if (!is_array($restored)) throw new RuntimeException('TODOIST_ROLLBACK_PROJECT_UPDATE_FAILED');
                        } else {
                            $serviceId = match ((string)$item['target_type']) { 'project' => 'service.project', 'task' => 'service.task', 'file' => 'service.file', 'project_module' => 'service.project_module', 'tag' => 'service.tag', 'comment' => 'service.comment', default => '' };
                            if ($serviceId === '') continue;
                            $service = $this->writer->service($serviceId);
                            $deleted = $item['target_type'] === 'tag' ? $service->delete((string)$item['target_public_id']) : $service->delete((string)$item['target_public_id'], $actor);
                            if ($deleted !== true) throw new RuntimeException('TODOIST_ROLLBACK_TARGET_NOT_DELETED');
                        }
                        $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'rolled_back']);
                    } catch (\Throwable $e) {
                        $batchWarning = true;
                        $warnings[] = (string)$item['source_id'];
                        $this->repo->upsertItem((int)$job['id'], (string)$item['source_type'], (string)$item['source_id'], ['status' => 'rollback_failed', 'error_code' => 'ROLLBACK_FAILED', 'error_message' => 'Target could not be removed.']);
                        $this->repo->addLog((int)$job['id'], 'warning', 'rollback', 'Target was not removed.', ['source_id' => $item['source_id'], 'error_code' => $e->getCode() ?: 'TODOIST_ROLLBACK_TARGET_NOT_DELETED']);
                    }
                }
                if ($batchWarning) break;
                $before = min(array_map('intval', array_column($items, 'id')));
                $this->repo->updateCursor($jobPublicId, json_encode(['phase' => 'rollback', 'before_id' => $before], JSON_UNESCAPED_UNICODE), $lease);
            }
            $this->repo->updateSummary($jobPublicId, ['rollback_warnings' => array_values(array_unique($warnings))], $lease);
            $this->repo->updateProgress($jobPublicId, 'rolled_back', 100, ['warnings' => count($warnings)], $lease);
            $this->repo->updateJobStatus($jobPublicId, $warnings === [] ? 'rolled_back' : 'rolled_back_with_warnings', $lease);
            $this->repo->releaseLease($jobPublicId, $lease);
        } catch (\Throwable $e) {
            try {
                if ($this->repo->ownsLease($jobPublicId, $lease)) {
                    $this->repo->updateJobStatus($jobPublicId, 'rollback_failed', $lease);
                    $this->repo->releaseLease($jobPublicId, $lease);
                }
            } catch (\Throwable) {
            }
            throw $e;
        }
    }
}
