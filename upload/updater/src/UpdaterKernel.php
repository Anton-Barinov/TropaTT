<?php
declare(strict_types=1);

namespace Updater;

use Updater\Client\UpdateCenterClient;
use Updater\Apply\FileApplier;
use Updater\Apply\HealthChecker;
use Updater\Apply\MaintenanceMode;
use Updater\Apply\MigrationRunner;
use Updater\Backup\DatabaseBackupManager;
use Updater\Backup\FileBackupManager;
use Updater\Http\JsonResponse;
use Updater\Log\UpdateLogger;
use Updater\Package\PackageDownloader;
use Updater\Package\PackageExtractor;
use Updater\Rollback\RollbackManager;
use Updater\Security\ManifestVerifier;
use Updater\Security\RequestRateLimiter;
use Updater\Security\TokenVerifier;
use Updater\State\JobState;
use Updater\State\LocalState;
use Updater\State\LockManager;
use Updater\Util\WorkBudget;

final class UpdaterKernel
{
    private array $config;
    private string $storageDir;

    public function __construct(private readonly string $basePath)
    {
        $this->config = require $basePath . '/api/config/update.php';
        $this->storageDir = (string)$this->config['storage_dir'];
        foreach (['sessions', 'jobs', 'packages', 'staging', 'backups', 'locks', 'logs', 'ratelimit'] as $dir) {
            $path = $this->storageDir . '/' . $dir;
            if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException('Unable to create updater storage directory: ' . $path);
            }
        }
    }

    public function handle(): void
    {
        $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'status');
        $input = $this->input();
        if (isset($_GET['job_id']) && !isset($input['job_id'])) {
            $input['job_id'] = (string)$_GET['job_id'];
        }
        $response = $this->dispatch($action, $input);
        $response->send();
    }

    /**
     * Dispatch an updater action for either the HTTP entry point or the
     * in-process API bridge. Keeping one dispatcher prevents the two paths
     * from drifting on authentication, state handling, or error envelopes.
     *
     * @param array<string,mixed> $input
     */
    public function dispatch(string $action, array $input): JsonResponse
    {
        try {
            return match ($action) {
                'status' => $this->status(),
                'preflight' => $this->preflight($input),
                'download' => $this->download($input),
                'apply' => $this->apply($input),
                'resume' => $this->resume($input),
                'rollback' => $this->rollback($input),
                'force-unlock' => $this->forceUnlock(),
                'log' => $this->log((string)($input['job_id'] ?? '')),
                default => JsonResponse::error('UNKNOWN_ACTION', 'Unknown updater action', 404),
            };
        } catch (\Throwable $e) {
            return JsonResponse::error('UPDATER_ERROR', $this->safeDiagnosticMessage($e->getMessage()), 500);
        }
    }

    private function status(): JsonResponse
    {
        $local = new LocalState($this->storageDir);
        return JsonResponse::success([
            'ok' => true,
            'version' => trim((string)@file_get_contents($this->basePath . '/updater/VERSION')),
            'installed_core' => $local->read(),
            'audit' => $local->readJson('update-center-audit.json'),
            'latest_job' => (new JobState($this->storageDir))->latest(),
            'maintenance' => is_file($this->basePath . '/storage_api/maintenance.flag'),
        ]);
    }

    private function preflight(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'preflight');
        $limited = $this->rateLimitAnonymous($input, 'preflight');
        if ($limited !== null) {
            return $limited;
        }
        $jobId = $this->jobId($input);
        $logger = new UpdateLogger($this->storageDir, $jobId);
        $state = new JobState($this->storageDir, $jobId);
        $state->write(['state' => 'created', 'can_resume' => true, 'can_rollback' => false]);

        try {
            $client = new UpdateCenterClient($this->config);
        $local = new LocalState($this->storageDir);
        $current = (string)($input['current_build'] ?? $local->currentBuild() ?? '0');
        $plan = $client->updatePlan($current);
        $state->write(['state' => 'plan_loaded', 'plan' => $plan]);
        $logger->info('plan_loaded', 'Update plan loaded', ['target_build' => $plan['target_build'] ?? null]);

        $package = $plan['recommended_package'] ?? null;
        if (!is_array($package)) {
            $report = ['ok' => true, 'update_available' => false, 'checks' => ['no_update' => true]];
            $state->writeFile('preflight.json', $report);
            return JsonResponse::success(['job_id' => $jobId, 'preflight' => $report]);
        }

        $manifest = $client->getJson((string)$package['manifest_url']);
        $verifier = new ManifestVerifier((string)$this->config['public_key_path'], $this->effectiveProtectedPaths($manifest));
        $manifestReport = $verifier->verify($manifest, $package, (string)$this->config['product']);
        $packageHead = $this->packageHead((string)$package['url']);

        $checks = [
            'update_center' => true,
            'manifest_signature' => $manifestReport['manifest_signature'],
            'package_signature' => $manifestReport['package_signature'],
            'package_url_accessible' => $packageHead['status'] >= 200 && $packageHead['status'] < 400,
            'package_content_length' => $packageHead['content_length'] === null || $packageHead['content_length'] === (int)$package['size_bytes'],
            'package_size_limit' => ((int)$package['size_bytes'] <= (int)$this->config['limits']['max_package_bytes']),
            'zip_extension' => extension_loaded('zip'),
            'openssl_extension' => extension_loaded('openssl'),
            'storage_writable' => is_writable($this->storageDir),
            'api_writable' => is_writable($this->basePath . '/api'),
            'web_writable' => is_writable($this->basePath . '/web'),
            'no_forbidden_paths' => $manifestReport['no_forbidden_paths'],
            'free_space' => disk_free_space($this->basePath) > ((int)$package['size_bytes'] * (int)$this->config['limits']['min_free_space_multiplier']),
            'no_active_lock' => !(new LockManager($this->storageDir))->isLocked(),
        ];
        $ok = !in_array(false, $checks, true);

        $report = [
            'ok' => $ok,
            'dry_run' => (bool)($input['dry_run'] ?? true),
            'current_build' => $current,
            'target_build' => $plan['target_build'] ?? null,
            'package' => $package,
            'checks' => $checks,
            'package_head' => $packageHead,
            'manifest_report' => $manifestReport,
            'modules_note' => 'modules/** are delivered with core updates: module files are added/updated from the package and are never deleted unless the module was removed from the product.',
        ];
        $state->writeFile('plan.json', $plan);
        $state->writeFile('manifest.json', $manifest);
        $state->writeFile('preflight.json', $report);
            $failedChecks = array_keys(array_filter($checks, static fn(mixed $value): bool => $value === false));
            $state->write([
                'state' => $ok ? 'preflight_passed' : 'failed',
                'can_resume' => $ok,
                'can_rollback' => false,
                'error' => $ok ? null : 'Preflight checks failed.',
                'error_code' => $ok ? null : 'PREFLIGHT_FAILED',
                'failed_checks' => $failedChecks,
            ]);
            if (!$ok) {
                $logger->error('preflight_failed', 'Update preflight checks failed', ['failed_checks' => $failedChecks]);
            }
            return JsonResponse::success(['job_id' => $jobId, 'preflight' => $report]);
        } catch (\Throwable $e) {
            $safeMessage = $this->safeDiagnosticMessage($e->getMessage());
            $state->write(['state' => 'failed', 'can_resume' => false, 'can_rollback' => false, 'error' => $safeMessage, 'error_code' => 'PREFLIGHT_FAILED', 'maintenance_held' => false, 'failed_checks' => []]);
            $logger->error('preflight_failed', 'Update preflight failed', ['error' => $e->getMessage()]);
            return JsonResponse::error('PREFLIGHT_FAILED', $safeMessage, 500);
        }
    }

    private function download(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'download');
        $jobId = $this->jobId($input);
        $state = new JobState($this->storageDir, $jobId);
        $steps = $this->stepConfig();
        $budget = WorkBudget::forSeconds((float)$steps['max_seconds_per_request']);

        try {
            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : null;

            // Rate limit only the FIRST request of a download job. Continuation
            // steps of an already-started job (a large package extracts over
            // many requests) must not trip the per-IP attempt window.
            if ($progress === null) {
                $limited = $this->rateLimitAnonymous($input, 'download');
                if ($limited !== null) {
                    return $limited;
                }
            }

            if ($progress === null) {
                $plan = $state->readFile('plan.json');
                if (!$plan) {
                    $preflight = $this->preflight(array_merge($input, ['job_id' => $jobId, 'dry_run' => true]));
                    if ($preflight->status >= 400) {
                        return $preflight;
                    }
                    $plan = $state->readFile('plan.json');
                }
                $preflightReport = $state->readFile('preflight.json');
                if (!is_array($preflightReport) || ($preflightReport['ok'] ?? false) !== true) {
                    return JsonResponse::error('PREFLIGHT_REQUIRED', 'Successful preflight is required before package preparation.', 409);
                }
                $package = $plan['recommended_package'] ?? null;
                if (!is_array($package)) {
                    return JsonResponse::error('NO_PACKAGE', 'No package available for this job', 409);
                }
                // The package itself is downloaded in one streaming pass (PHP
                // keeps its own generous timeout; memory stays flat because the
                // downloader streams to disk). Extraction below is chunked.
                $path = (new PackageDownloader($this->storageDir))->download($jobId, $package);
                $state->write(['progress' => ['phase' => 'extract', 'cursor' => 0, 'done' => 0, 'total' => 0]]);
            }

            // Extraction step machine: unzip at most max_files_per_request
            // entries per request so a large package never trips a shared-host
            // proxy timeout. The protected-path list is the same one preflight
            // used (manifest-aware), so a package can never be rejected here
            // after preflight accepted it.
            $manifest = $state->readFile('manifest.json');
            for ($guard = 0; $guard < 1000; $guard++) {
                $stored = $state->readFile('state.json') ?: [];
                $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
                if (($progress['phase'] ?? '') !== 'extract') {
                    break;
                }
                $path = $this->storageDir . '/packages/' . basename($jobId) . '/package.zip';
                if (!is_file($path)) {
                    throw new \RuntimeException('Downloaded package is missing.');
                }
                $cursor = (int)($progress['cursor'] ?? 0);
                $result = (new PackageExtractor($this->storageDir, $this->effectiveProtectedPaths($manifest)))
                    ->extract($jobId, $path, $cursor, $budget, (int)$steps['max_files_per_request']);
                $state->write(['progress' => [
                    'phase' => 'extract',
                    'cursor' => $result['cursor'],
                    'done' => $result['cursor'],
                    'total' => $result['total'],
                ]]);
                if ($result['done'] || $budget->exhausted()) {
                    break;
                }
            }

            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            if (($progress['phase'] ?? '') === 'extract' && (int)($progress['done'] ?? 0) < (int)($progress['total'] ?? 0)) {
                return JsonResponse::success(['job_id' => $jobId, 'continue' => true, 'progress' => $progress]);
            }

            $path = $this->storageDir . '/packages/' . basename($jobId) . '/package.zip';
            $names = $this->stagedNames($jobId);
            $state->write([
                'state' => 'staging_ready',
                'can_resume' => true,
                'can_rollback' => false,
                'package_path' => $path,
                'staged_file_count' => count($names),
                'staged_files_preview' => array_slice($names, 0, 50),
                // Clear the extract progress: a finished download must not look
                // like an in-progress job to apply()/rollback() (they decide
                // continuation by the presence of progress in state.json).
                'progress' => null,
            ]);

            return JsonResponse::success([
                'job_id' => $jobId,
                'continue' => false,
                'package' => [
                    'path' => $path,
                    'exists' => is_file($path),
                    'size_bytes' => is_file($path) ? filesize($path) : null,
                ],
                'staging' => [
                    'file_count' => count($names),
                    'preview' => array_slice($names, 0, 20),
                    'preview_truncated' => count($names) > 20,
                ],
            ]);
        } catch (\Throwable $e) {
            $safeMessage = $this->safeDiagnosticMessage($e->getMessage());
            $state->write(['state' => 'failed', 'can_resume' => true, 'can_rollback' => false, 'error' => $safeMessage, 'error_code' => 'DOWNLOAD_FAILED', 'maintenance_held' => false, 'failed_checks' => []]);
            (new UpdateLogger($this->storageDir, $jobId))->error('download_failed', 'Update package preparation failed', ['error' => $e->getMessage()]);
            return JsonResponse::error('DOWNLOAD_FAILED', $safeMessage, 500);
        }
    }

    private function apply(array $input): JsonResponse
    {
        $jobId = $this->jobId($input);
        $state = new JobState($this->storageDir, $jobId);
        $logger = new UpdateLogger($this->storageDir, $jobId);
        $steps = $this->stepConfig();

        // Guard 3 (maintenance hold): once the file tree or the database has
        // been mutated, a failure must leave maintenance mode ON so the CRM
        // never comes back online with new files over an old or partially
        // migrated database. The admin resolves the state by rolling back or
        // retrying the update from the updates page (which stays reachable
        // during held maintenance). If maintenance was ALREADY held before
        // this run (a previous failed attempt), it must never be turned off
        // by this run, even if this run fails before mutating anything.
        $maintenanceWasOn = is_file($this->basePath . '/storage_api/maintenance.flag');

        try {
            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : null;
            // Re-post of an already-finished job: return its stored result
            // right away, WITHOUT touching the lock. Renewing the lock here
            // (via the continuation path below) would re-issue it and never
            // release it, blocking the follow-up rollback() from acquiring.
            if ($progress !== null
                && ($progress['phase'] ?? '') === 'finalized'
                && (($stored['state'] ?? '') === 'applied')
            ) {
                $this->verifyTokenIfPresent($input, 'apply_step');
                return $this->applyFinalResponse($state, $jobId);
            }
            // A job is an apply continuation only while it is inside the apply
            // flow (or a failed apply being retried). A download or a finished
            // job must start apply() fresh, never resume as a continuation.
            $isContinuation = $progress !== null
                && in_array((string)($stored['state'] ?? ''), ['applying', 'backup_created', 'applied', 'failed'], true);

            if ($isContinuation) {
                // Multi-request job: token is verified without being consumed
                // (apply_step), and the job's lock heartbeat is refreshed.
                $this->verifyTokenIfPresent($input, 'apply_step');
                if (!$this->renewLockForJob($jobId, $steps)) {
                    throw new \RuntimeException('Update lock was lost; another update may have started. Roll back or retry the update.');
                }
                // A failed attempt is being retried: clear the failure marker
                // so the page stops showing the stale error while the same
                // job resumes from its stored progress.
                if (($stored['state'] ?? '') === 'failed' && ($stored['error'] ?? null) !== null) {
                    $state->write(['state' => 'applying', 'error' => null]);
                }
            } else {
                if (($input['confirm_apply'] ?? false) !== true) {
                    return JsonResponse::error('CONFIRM_APPLY_REQUIRED', 'Real apply requires confirm_apply=true after successful dry-run preflight', 409);
                }
                $preflight = $state->readFile('preflight.json');
                $manifest = $state->readFile('manifest.json');
                if (!is_array($preflight) || ($preflight['ok'] ?? false) !== true || !is_array($manifest)) {
                    return JsonResponse::error('PREFLIGHT_REQUIRED', 'Successful preflight is required before apply.', 409);
                }
                $this->verifyTokenIfPresent($input, 'apply');
                (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->acquire($jobId);
                (new MaintenanceMode($this->basePath))->enable($jobId);
                $state->write(['state' => 'applying', 'can_resume' => false, 'can_rollback' => false]);
                $logger->info('maintenance_enabled', 'Maintenance mode enabled');

                $applier = new FileApplier($this->basePath, $this->storageDir, $this->effectiveProtectedPaths($manifest));
                $files = $applier->filesFromManifest($manifest);
                $filesForBackup = array_values(array_unique(array_merge($files['add'], $files['modify'], $files['delete'])));
                $applyTotal = count($files['delete']) + count($files['add']) + count($files['modify']);
                $state->writeFile('apply-plan.json', [
                    'files_for_backup' => $filesForBackup,
                    'apply_total' => $applyTotal,
                ]);
                $state->write(['progress' => [
                    'phase' => 'backup_files',
                    'cursor' => 0,
                    'done' => 0,
                    'total' => count($filesForBackup),
                ]]);
            }

            // Step machine: each phase performs only as much real work as the
            // request budget allows, then returns {continue:true} so the page
            // issues the next request. Every request stays far below shared
            // hosting web-server/proxy timeouts no matter how big the update
            // or the database is.
            $budget = WorkBudget::forSeconds((float)$steps['max_seconds_per_request']);
            for ($guard = 0; $guard < 1000; $guard++) {
                $stored = $state->readFile('state.json') ?: [];
                $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : null;
                if (!is_array($progress)) {
                    throw new \RuntimeException('Update progress is missing.');
                }
                $phase = (string)($progress['phase'] ?? '');
                $result = $this->applyPhase($phase, $state, $progress, $budget, $steps, $logger);
                if (($result['stop'] ?? false) === true) {
                    break;
                }
                if (($result['finished'] ?? false) === true) {
                    return $result['response'];
                }
                if (($result['next'] ?? false) === true) {
                    // ['next' => true] requires the phase to have advanced;
                    // without this check a missing transition would spin the
                    // loop forever on the same phase.
                    $stored = $state->readFile('state.json') ?: [];
                    $newPhase = (string)(is_array($stored['progress'] ?? null) ? ($stored['progress']['phase'] ?? '') : '');
                    if ($newPhase === $phase) {
                        throw new \RuntimeException('Apply phase did not advance: ' . $phase);
                    }
                }
                if ($budget->exhausted()) {
                    break;
                }
            }

            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            return JsonResponse::success(['job_id' => $jobId, 'continue' => true, 'progress' => $progress]);
        } catch (\Throwable $e) {
            // Guard 3: maintenance stays ON when it was already held before
            // this run (previous failed attempt) or when the failure happened
            // after the file tree / DB was mutated. Only a clean pre-mutation
            // failure with maintenance we enabled ourselves may turn it off.
            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            $phase = (string)($progress['phase'] ?? '');
            $systemMutated = $phase === '' || in_array($phase, ['apply_files', 'health', 'backup_db', 'migrate', 'finalize'], true);
            $maintenanceHeld = $maintenanceWasOn || $systemMutated;
            if (!$maintenanceHeld) {
                (new MaintenanceMode($this->basePath))->disable();
            }
            (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->release($jobId);
            $safeMessage = $this->safeDiagnosticMessage($e->getMessage());
            $state->write(['state' => 'failed', 'error' => $safeMessage, 'error_code' => 'APPLY_FAILED', 'can_rollback' => true, 'maintenance_held' => $maintenanceHeld]);
            $logger->error('apply_failed', 'Update apply failed', ['error' => $e->getMessage(), 'maintenance_held' => $maintenanceHeld]);
            return JsonResponse::error('APPLY_FAILED', $safeMessage, 500);
        }
    }

    /**
     * Dispatch one bounded slice of the apply job for the current phase.
     *
     * @return array{stop?:bool,next?:bool,finished?:bool,response?:JsonResponse}
     */
    private function applyPhase(string $phase, JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger): array
    {
        return match ($phase) {
            'backup_files' => $this->applyPhaseBackupFiles($state, $progress, $budget, $steps, $logger),
            'apply_files' => $this->applyPhaseApplyFiles($state, $progress, $budget, $steps, $logger),
            'health' => $this->applyPhaseHealth($state),
            'backup_db' => $this->applyPhaseBackupDb($state, $progress, $budget, $steps, $logger),
            'migrate' => $this->applyPhaseMigrate($state, $progress, $budget, $steps, $logger),
            'finalize' => $this->applyPhaseFinalize($state, $steps, $logger),
            default => throw new \RuntimeException('Unknown apply phase: ' . $phase),
        };
    }

    private function applyPhaseBackupFiles(JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger): array
    {
        $jobId = (string)($state->readFile('state.json')['job_id'] ?? '');
        // A failed apply may be retried. Reuse the COMPLETE backup from the
        // first attempt as the rollback point: re-backing up from the current
        // (possibly partially-updated) tree would silently destroy the original
        // pre-update snapshot, so a rollback after multiple failed attempts
        // would restore a broken half-updated state instead of the tree the
        // update started from.
        $existing = $state->readFile('backup.json') ?: [];
        if (($existing['backup_id'] ?? '') !== '' && is_array($existing['items'] ?? null)) {
            $state->write(['state' => 'backup_created', 'backup_id' => $existing['backup_id'], 'can_rollback' => true]);
            $logger->info('backup_reused', 'Reusing backup from a previous attempt', ['backup_id' => $existing['backup_id']]);
            return $this->beginApplyFiles($state, $logger);
        }
        $plan = $state->readFile('apply-plan.json') ?: [];
        $files = is_array($plan['files_for_backup'] ?? null) ? $plan['files_for_backup'] : [];
        $cursor = (int)($progress['cursor'] ?? 0);
        if ($cursor >= count($files)) {
            // Nothing left to back up (empty file list, or a completed backup
            // whose transition crashed). Emit a consistent empty backup
            // manifest if needed, then move to the apply_files phase.
            if (!$state->readFile('backup.json')) {
                $backupId = 'backup_' . basename($jobId) . '_' . gmdate('Ymd_His');
                $state->writeFile('backup.json', [
                    'backup_id' => $backupId,
                    'job_id' => $jobId,
                    'created_at' => gmdate('c'),
                    'items' => [],
                ]);
                $state->write(['state' => 'backup_created', 'backup_id' => $backupId, 'can_rollback' => true]);
            }
            return $this->beginApplyFiles($state, $logger);
        }
        $backup = (new FileBackupManager($this->basePath, $this->storageDir))
            ->backup($jobId, $files, $cursor, $budget, (int)$steps['max_files_per_request']);
        $state->write(['progress' => [
            'phase' => 'backup_files',
            'cursor' => $backup['cursor'],
            'done' => $backup['cursor'],
            'total' => $backup['total'] ?? count($files),
        ]]);
        // Log chunk progress so slow shared-hosting backups leave a
        // diagnostic trail: if the backup "hangs" at step N, the last
        // log entry shows exactly where it stopped.
        $logger->info('backup_chunk', 'File backup chunk', [
            'cursor' => $backup['cursor'],
            'total' => $backup['total'] ?? count($files),
            'done' => $backup['done'],
        ]);
        if (!$backup['done']) {
            return ['stop' => true];
        }
        $state->writeFile('backup.json', $backup['manifest']);
        $state->write(['state' => 'backup_created', 'backup_id' => $backup['backup_id'], 'can_rollback' => true]);
        $logger->info('backup_created', 'File backup created', ['backup_id' => $backup['backup_id']]);
        return $this->beginApplyFiles($state, $logger);
    }

    private function beginApplyFiles(JobState $state, UpdateLogger $logger): array
    {
        $plan = $state->readFile('apply-plan.json') ?: [];
        $total = (int)($plan['apply_total'] ?? 0);
        $state->write(['progress' => ['phase' => 'apply_files', 'cursor' => 0, 'done' => 0, 'total' => $total]]);
        $logger->info('files_apply_started', 'Applying package files');
        return ['next' => true];
    }

    private function applyPhaseApplyFiles(JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger): array
    {
        $jobId = (string)($state->readFile('state.json')['job_id'] ?? '');
        $manifest = $state->readFile('manifest.json') ?: [];
        $cursor = (int)($progress['cursor'] ?? 0);
        $dir = $this->storageDir . '/jobs/' . basename($jobId);
        // A failed chunk leaves applied.jsonl AHEAD of the committed cursor
        // (partial entries from the attempt that threw). Trim back to the
        // cursor before appending so a retry never duplicates entries.
        $this->trimJsonlToCursor($dir . '/applied.jsonl', $cursor);
        $result = (new FileApplier($this->basePath, $this->storageDir, $this->effectiveProtectedPaths($manifest)))
            ->apply($jobId, $manifest, $cursor, $budget, (int)$steps['max_files_per_request']);
        foreach ($result['files'] as $item) {
            $this->appendJsonl($dir, 'applied.jsonl', $item);
        }
        $state->write(['progress' => [
            'phase' => 'apply_files',
            'cursor' => $result['cursor'],
            'done' => $result['cursor'],
            'total' => $result['total'],
        ]]);
        if (!$result['done']) {
            return ['stop' => true];
        }
        $files = $this->readJsonl($dir . '/applied.jsonl');
        $state->writeFile('applied.json', ['count' => count($files), 'files' => $files]);
        $logger->info('files_applied', 'Files applied', ['count' => count($files)]);
        // Explicitly advance to the health phase: ['next' => true] must never
        // be returned without a phase transition, or the step loop would spin
        // on the finished phase forever.
        $state->write(['progress' => ['phase' => 'health', 'cursor' => [], 'done' => 0, 'total' => 0]]);
        return ['next' => true];
    }

    private function applyPhaseHealth(JobState $state): array
    {
        $health = (new HealthChecker($this->basePath))->check();
        $state->writeFile('health.json', $health);
        if (($health['ok'] ?? false) !== true) {
            throw new \RuntimeException('Post-apply health check failed.');
        }
        // Cursor null on purpose: applyPhaseBackupDb() treats a null cursor as
        // the FIRST backup_db request and runs its pre-checks (db_backup
        // enabled toggle + "no pending migrations" skip).
        $state->write(['progress' => ['phase' => 'backup_db', 'cursor' => null, 'done' => 0, 'total' => 0]]);
        return ['next' => true];
    }

    private function applyPhaseBackupDb(JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger): array
    {
        $jobId = (string)($state->readFile('state.json')['job_id'] ?? '');
        $backupId = (string)($state->readFile('state.json')['backup_id'] ?? '');
        if ($backupId === '') {
            throw new \RuntimeException('File backup id is missing; cannot snapshot the database.');
        }
        $cursor = is_array($progress['cursor'] ?? null) ? $progress['cursor'] : null;
        $manager = new DatabaseBackupManager($this->basePath);

        // Pre-checks run once, on the first backup_db request (cursor null).
        if ($cursor === null) {
            if (($this->config['db_backup']['enabled'] ?? true) !== true) {
                $report = ['ok' => false, 'done' => true, 'skipped' => true, 'reason' => 'db backup disabled in config'];
                $state->writeFile('db_backup.json', $report);
                return $this->beginMigrations($state, $logger, $report);
            }
            if ($this->pendingMigrations() === []) {
                $report = ['ok' => false, 'done' => true, 'skipped' => true, 'reason' => 'no pending migrations'];
                $state->writeFile('db_backup.json', $report);
                $logger->info('db_backup_skipped', 'Database backup skipped (no pending migrations)');
                return $this->beginMigrations($state, $logger, $report);
            }
        }

        $backupDir = $this->storageDir . '/backups/' . basename($backupId);
        $report = $manager->backup($backupDir, $jobId, $cursor, $budget, (int)$steps['max_rows_per_request']);
        if (($report['done'] ?? false) !== true) {
            $state->write(['progress' => [
                'phase' => 'backup_db',
                'cursor' => $report['cursor'] ?? [],
                'done' => (int)($report['rows_done'] ?? 0),
                'total' => (int)($report['rows_done'] ?? 0),
            ]]);
            return ['stop' => true];
        }
        $state->writeFile('db_backup.json', $report);
        if (($report['ok'] ?? false) === true) {
            $logger->info('db_backup_created', 'Database backup created', [
                'driver' => $report['driver'] ?? null,
                'tables' => $report['tables'] ?? null,
                'rows' => $report['rows'] ?? null,
            ]);
        } else {
            $logger->error('db_backup_failed', 'Database backup failed', ['error' => $report['error'] ?? ($report['reason'] ?? 'unknown')]);
        }
        return $this->beginMigrations($state, $logger, $report);
    }

    /**
     * Database-safety guard + move into the migrate phase. Migrations must
     * never run without a DB snapshot they can be rolled back from; if the
     * snapshot is missing/failed and migrations are pending, abort BEFORE any
     * schema change so the database stays exactly as it was.
     */
    private function beginMigrations(JobState $state, UpdateLogger $logger, array $dbBackup): array
    {
        $dbBackupUsable = ($dbBackup['ok'] ?? false) === true
            || (string)($dbBackup['reason'] ?? '') === 'no pending migrations';
        if (!$dbBackupUsable && $this->pendingMigrations() !== []) {
            $dbReason = (string)($dbBackup['reason'] ?? $dbBackup['error'] ?? 'unknown');
            throw new \RuntimeException(
                'Database backup is not available (' . $dbReason . ') but migrations are pending. '
                . 'Apply aborted before any schema change to protect the database; '
                . 'fix the backup issue and retry the update.'
            );
        }
        $pending = $this->pendingMigrations();
        $state->write(['progress' => [
            'phase' => 'migrate',
            'cursor' => [],
            'done' => 0,
            'total' => count($pending),
            'executed' => [],
        ]]);
        return ['next' => true];
    }

    private function applyPhaseMigrate(JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger): array
    {
        $accumulated = array_merge((array)($progress['executed'] ?? []), []);
        $maxMigrations = (int)$steps['max_migrations_per_request'];
        $report = (new MigrationRunner($this->basePath))->run($maxMigrations, $budget);
        $accumulated = array_values(array_unique(array_merge($accumulated, (array)($report['executed'] ?? []))));

        if (($report['ok'] ?? false) !== true) {
            $report['executed'] = $accumulated;
            $state->writeFile('migrations.json', $report);
            $logger->error('migrations_failed', 'Database migrations failed', ['error' => $report['error'] ?? 'unknown']);
            throw new \RuntimeException(
                'Database migrations failed: ' . (string)($report['error'] ?? 'migrations did not fully apply') . '. '
                . 'The update was not finalized and maintenance mode stays enabled; '
                . 'roll back to restore the database and files, or retry the update.'
            );
        }

        $state->write(['progress' => [
            'phase' => 'migrate',
            'cursor' => [],
            'done' => count($accumulated),
            'total' => (int)($progress['total'] ?? 0),
            'executed' => $accumulated,
        ]]);
        if (($report['done'] ?? false) !== true) {
            return ['stop' => true];
        }

        $finalReport = $report;
        $finalReport['executed'] = $accumulated;
        $state->writeFile('migrations.json', $finalReport);
        $logger->info('migrations_applied', 'Database migrations applied', [
            'executed' => $accumulated,
            'pending_after' => $report['pending_after'] ?? [],
        ]);
        $state->write(['progress' => ['phase' => 'finalize', 'cursor' => [], 'done' => 0, 'total' => 1]]);
        return ['next' => true];
    }

    private function applyFinalResponse(JobState $state, string $jobId): JsonResponse
    {
        return JsonResponse::success([
            'job_id' => $jobId,
            'continue' => false,
            'backup' => $state->readFile('backup.json'),
            'db_backup' => $state->readFile('db_backup.json'),
            'applied' => $state->readFile('applied.json'),
            'health' => $state->readFile('health.json'),
            'migrations' => $state->readFile('migrations.json'),
            'installed_core' => (new LocalState($this->storageDir))->read(),
        ]);
    }

    private function applyPhaseFinalize(JobState $state, array $steps, UpdateLogger $logger): array
    {
        $jobId = (string)($state->readFile('state.json')['job_id'] ?? '');
        $manifest = $state->readFile('manifest.json') ?: [];
        (new LocalState($this->storageDir))->write([
            'state' => 'installed',
            'product' => $manifest['product'] ?? $this->config['product'],
            'core_version' => $manifest['core_version'] ?? null,
            'core_build' => $manifest['to_build'] ?? null,
            'source_sha' => $manifest['to_sha'] ?? null,
            'short_sha' => $manifest['short_sha'] ?? null,
            'last_job_id' => $jobId,
        ]);
        (new MaintenanceMode($this->basePath))->disable();
        (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->release($jobId);
        // Ensure recovery key files exist for rescue.php. If the hash file
        // exists but the plaintext sidecar is missing (old installation or
        // manual deletion), generate a new key pair so the admin can always
        // recover via rescue.php or SSH.
        $this->ensureRecoveryKey($logger);
        // Clear any error recorded by an earlier failed attempt: a job that
        // finished successfully must never keep showing a stale error text.
        $state->write(['state' => 'applied', 'can_resume' => false, 'can_rollback' => true, 'finished_at' => gmdate('c'), 'error' => null, 'progress' => ['phase' => 'finalized', 'cursor' => [], 'done' => 1, 'total' => 1]]);
        $logger->info('apply_complete', 'Update applied successfully');

        return [
            'finished' => true,
            'response' => $this->applyFinalResponse($state, $jobId),
        ];
    }

    private function resume(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'resume');
        return JsonResponse::success(['latest_job' => (new JobState($this->storageDir))->latest(), 'message' => 'Resume/status inspection is available for staged and applied jobs.']);
    }

    /**
     * Ensure recovery key files exist for rescue.php.
     * If the hash file is missing, generate a new key pair.
     * If the hash exists but the plaintext sidecar is missing, generate a new key.
     */
    private function ensureRecoveryKey(UpdateLogger $logger): void
    {
        $hashFile = $this->storageDir . '/recovery_key.hash';
        $txtFile = $this->storageDir . '/recovery_key.txt';
        $hashExists = is_file($hashFile);
        $txtExists = is_file($txtFile);

        // Both files exist — nothing to do
        if ($hashExists && $txtExists) {
            return;
        }

        // Hash exists but txt is missing — we can't recover the old key,
        // so rotate BOTH sides as one pair. Writing only the plaintext sidecar
        // would expose a key that rescue.php cannot verify.
        if ($hashExists && !$txtExists) {
            $key = bin2hex(random_bytes(16));
            $hash = password_hash($key, PASSWORD_DEFAULT);
            if (@file_put_contents($hashFile, $hash, LOCK_EX) === false
                || @file_put_contents($txtFile, $key, LOCK_EX) === false) {
                @unlink($txtFile);
                $logger->warning('recovery_key_failed', 'Failed to rotate recovery key pair');
                return;
            }
            @chmod($hashFile, 0640);
            @chown($hashFile, 'www-data');
            @chgrp($hashFile, 'www-data');
            @chmod($txtFile, 0640);
            @chown($txtFile, 'www-data');
            @chgrp($txtFile, 'www-data');
            $logger->info('recovery_key_rotated', 'Rotated recovery key pair for rescue.php');
            return;
        }

        // Neither file exists — generate a new key pair
        try {
            $key = bin2hex(random_bytes(16));
            if (@file_put_contents($hashFile, password_hash($key, PASSWORD_DEFAULT)) !== false) {
                @chmod($hashFile, 0640);
                @chown($hashFile, 'www-data');
                @chgrp($hashFile, 'www-data');
                @file_put_contents($txtFile, $key);
                @chmod($txtFile, 0640);
                @chown($txtFile, 'www-data');
                @chgrp($txtFile, 'www-data');
                $logger->info('recovery_key_created', 'Created recovery key pair for rescue.php');
            }
        } catch (\Throwable $e) {
            $logger->warning('recovery_key_failed', 'Failed to create recovery key: ' . $e->getMessage());
        }
    }

    private function rollback(array $input): JsonResponse
    {
        $jobId = $this->jobId($input);
        $state = new JobState($this->storageDir, $jobId);
        $logger = new UpdateLogger($this->storageDir, $jobId);
        $steps = $this->stepConfig();
        $backup = $state->readFile('backup.json') ?: [];
        $backupId = (string)($input['backup_id'] ?? ($backup['backup_id'] ?? ''));
        if ($backupId === '') {
            return JsonResponse::error('ROLLBACK_REQUIRES_BACKUP', 'No backup is available for rollback.', 409);
        }

        // Guard 3 (rollback): a failed rollback can leave a partially
        // restored database or file tree, so maintenance must stay ON until
        // the admin retries the rollback. Only disable when we enabled it
        // ourselves, nothing had been restored yet, and maintenance was not
        // already held before this run.
        $maintenanceWasOn = is_file($this->basePath . '/storage_api/maintenance.flag');

        try {
            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : null;
            // Re-post of an already-finished rollback: return its stored result
            // right away, without consuming the single-use rollback token or
            // re-acquiring the lock / re-enabling maintenance.
            if ($progress !== null
                && ($progress['phase'] ?? '') === 'finalized'
                && (($stored['state'] ?? '') === 'rolled_back')
            ) {
                $this->verifyTokenIfPresent($input, 'rollback_step');
                return $this->rollbackFinalResponse($state, $jobId, (new LocalState($this->storageDir))->read());
            }
            // Rollback reuses the APPLY job's id, whose state.json carries the
            // apply progress (possibly 'finalized'). A rollback is a
            // continuation only while inside the rollback flow, so a finished
            // or downloaded job always starts rollback() fresh.
            $isContinuation = $progress !== null
                && in_array((string)($stored['state'] ?? ''), ['rolling_back', 'rollback_failed'], true);

            if ($isContinuation) {
                $this->verifyTokenIfPresent($input, 'rollback_step');
                if (!$this->renewLockForJob($jobId, $steps)) {
                    throw new \RuntimeException('Rollback lock was lost; another update may have started.');
                }
                // A failed rollback attempt being retried: drop the stale
                // error marker while the job resumes from its progress.
                if (($stored['state'] ?? '') === 'rollback_failed' && ($stored['error'] ?? null) !== null) {
                    $state->write(['state' => 'rolling_back', 'error' => null]);
                }
            } else {
                $this->verifyTokenIfPresent($input, 'rollback');
                (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->acquire($jobId);
                (new MaintenanceMode($this->basePath))->enable($jobId);
                $state->write(['state' => 'rolling_back', 'can_resume' => false, 'can_rollback' => false]);
                // Restore the database BEFORE restoring files. The file backup
                // of a self-updating package contains the pre-update updater
                // files (an older DatabaseBackupManager without restore()), so
                // touching the DB after the file rollback would autoload that
                // older class from disk and fatal. Restoring the DB first runs
                // against the current post-update code; it is best-effort and
                // skips cleanly when no DB snapshot exists for the job
                // (files-only update, old job).
                $state->write(['progress' => ['phase' => 'restore_db', 'cursor' => [], 'done' => 0, 'total' => 0]]);
            }

            // Step machine: DB restore, file restore, health and finalize each
            // run in bounded chunks; {continue:true} is returned between
            // requests so no single request exceeds shared-hosting limits.
            $budget = WorkBudget::forSeconds((float)$steps['max_seconds_per_request']);
            for ($guard = 0; $guard < 1000; $guard++) {
                $stored = $state->readFile('state.json') ?: [];
                $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : null;
                if (!is_array($progress)) {
                    throw new \RuntimeException('Rollback progress is missing.');
                }
                $phase = (string)($progress['phase'] ?? '');
                $result = $this->rollbackPhase($phase, $state, $progress, $budget, $steps, $logger, $backupId);
                if (($result['stop'] ?? false) === true) {
                    break;
                }
                if (($result['finished'] ?? false) === true) {
                    return $result['response'];
                }
                if (($result['next'] ?? false) === true) {
                    // Safety net: a phase completion must always advance the
                    // phase, otherwise the step loop would spin forever.
                    $stored = $state->readFile('state.json') ?: [];
                    $newPhase = (string)(is_array($stored['progress'] ?? null) ? ($stored['progress']['phase'] ?? '') : '');
                    if ($newPhase === $phase) {
                        throw new \RuntimeException('Rollback phase did not advance: ' . $phase);
                    }
                }
                if ($budget->exhausted()) {
                    break;
                }
            }

            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            return JsonResponse::success(['job_id' => $jobId, 'continue' => true, 'progress' => $progress]);
        } catch (\Throwable $e) {
            // Guard 3 (rollback): a partially restored state must stay behind
            // maintenance so the admin can retry; never silently reopen the
            // CRM on a half-restored database or file tree.
            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            $phase = (string)($progress['phase'] ?? '');
            $systemMutated = $phase === '' || in_array($phase, ['restore_db', 'restore_files', 'health'], true);
            $maintenanceHeld = $maintenanceWasOn || $systemMutated;
            if (!$maintenanceHeld) {
                (new MaintenanceMode($this->basePath))->disable();
            }
            (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->release($jobId);
            $safeMessage = $this->safeDiagnosticMessage($e->getMessage());
            $state->write(['state' => 'rollback_failed', 'error' => $safeMessage, 'error_code' => 'ROLLBACK_FAILED', 'can_rollback' => true, 'maintenance_held' => $maintenanceHeld]);
            $logger->error('rollback_failed', 'Rollback failed', ['error' => $e->getMessage(), 'maintenance_held' => $maintenanceHeld]);
            return JsonResponse::error('ROLLBACK_FAILED', $safeMessage, 500);
        }
    }

    /**
     * @return array{stop?:bool,next?:bool,finished?:bool,response?:JsonResponse}
     */
    private function rollbackPhase(string $phase, JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger, string $backupId): array
    {
        return match ($phase) {
            'restore_db' => $this->rollbackPhaseRestoreDb($state, $progress, $budget, $steps, $logger, $backupId),
            'restore_files' => $this->rollbackPhaseRestoreFiles($state, $progress, $budget, $steps, $logger, $backupId),
            'health' => $this->rollbackPhaseHealth($state),
            'finalize' => $this->rollbackPhaseFinalize($state, $steps, $logger, $backupId),
            default => throw new \RuntimeException('Unknown rollback phase: ' . $phase),
        };
    }

    private function rollbackPhaseRestoreDb(JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger, string $backupId): array
    {
        $cursor = is_array($progress['cursor'] ?? null) ? $progress['cursor'] : null;
        $report = (new DatabaseBackupManager($this->basePath))
            ->restore($this->storageDir . '/backups/' . basename($backupId), $cursor, $budget, (int)$steps['max_statements_per_request']);
        if (($report['done'] ?? false) !== true) {
            $state->write(['progress' => [
                'phase' => 'restore_db',
                'cursor' => $report['cursor'] ?? [],
                'done' => 0,
                'total' => 0,
            ]]);
            return ['stop' => true];
        }
        $state->writeFile('db_restore.json', $report);
        if (($report['ok'] ?? false) === true) {
            $logger->info('db_restore_complete', 'Database restored from backup', ['backup_id' => $backupId]);
        } elseif (($report['skipped'] ?? false) === true) {
            $logger->info('db_restore_skipped', 'Database restore skipped', ['reason' => $report['reason'] ?? 'unknown']);
        } else {
            $logger->error('db_restore_failed', 'Database restore failed', ['error' => $report['error'] ?? 'unknown']);
        }
        return $this->beginRollbackFiles($state, $logger);
    }

    private function beginRollbackFiles(JobState $state, UpdateLogger $logger): array
    {
        $backup = $state->readFile('backup.json') ?: [];
        $items = is_array($backup['items'] ?? null) ? $backup['items'] : [];
        $state->write(['progress' => ['phase' => 'restore_files', 'cursor' => 0, 'done' => 0, 'total' => count($items)]]);
        $logger->info('rollback_files_started', 'Restoring files from backup');
        return ['next' => true];
    }

    private function rollbackPhaseRestoreFiles(JobState $state, array $progress, WorkBudget $budget, array $steps, UpdateLogger $logger, string $backupId): array
    {
        $jobId = (string)($state->readFile('state.json')['job_id'] ?? '');
        $cursor = (int)($progress['cursor'] ?? 0);
        $result = (new RollbackManager($this->basePath, $this->storageDir))
            ->rollback($backupId, $cursor, $budget, (int)$steps['max_files_per_request']);
        $dir = $this->storageDir . '/jobs/' . basename($jobId);
        // Same trim-to-cursor as applied.jsonl: a failed chunk must not leave
        // duplicate entries behind for a retried rollback.
        $this->trimJsonlToCursor($dir . '/rollback.jsonl', $cursor);
        foreach ($result['files'] as $item) {
            $this->appendJsonl($dir, 'rollback.jsonl', $item);
        }
        $state->write(['progress' => [
            'phase' => 'restore_files',
            'cursor' => $result['cursor'],
            'done' => $result['cursor'],
            'total' => $result['total'],
        ]]);
        if (!$result['done']) {
            return ['stop' => true];
        }
        $files = $this->readJsonl($dir . '/rollback.jsonl');
        $state->writeFile('rollback.json', ['backup_id' => $backupId, 'restored_count' => count($files), 'files' => $files]);
        $logger->info('rollback_files_done', 'Files restored', ['count' => count($files)]);
        $state->write(['progress' => ['phase' => 'health', 'cursor' => [], 'done' => 0, 'total' => 0]]);
        return ['next' => true];
    }

    private function rollbackPhaseHealth(JobState $state): array
    {
        $health = (new HealthChecker($this->basePath))->check();
        $state->writeFile('health.json', $health);
        if (($health['ok'] ?? false) !== true) {
            throw new \RuntimeException('Post-rollback health check failed.');
        }
        $state->write(['progress' => ['phase' => 'finalize', 'cursor' => [], 'done' => 0, 'total' => 1]]);
        return ['next' => true];
    }

    private function rollbackFinalResponse(JobState $state, string $jobId, array $installedCore): JsonResponse
    {
        return JsonResponse::success([
            'job_id' => $jobId,
            'continue' => false,
            'rollback' => $state->readFile('rollback.json'),
            'db_restore' => $state->readFile('db_restore.json'),
            'health' => $state->readFile('health.json'),
            'installed_core' => $installedCore,
        ]);
    }

    private function rollbackPhaseFinalize(JobState $state, array $steps, UpdateLogger $logger, string $backupId): array
    {
        $jobId = (string)($state->readFile('state.json')['job_id'] ?? '');
        $manifest = $state->readFile('manifest.json') ?: [];
        $plan = $state->readFile('plan.json') ?: [];
        $installedCore = $this->rollbackInstalledCoreState($jobId, $manifest, $plan);
        (new MaintenanceMode($this->basePath))->disable();
        (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->release($jobId);
        // Clear any error recorded by an earlier failed attempt.
        $state->write(['state' => 'rolled_back', 'can_resume' => false, 'can_rollback' => false, 'finished_at' => gmdate('c'), 'error' => null, 'progress' => ['phase' => 'finalized', 'cursor' => [], 'done' => 1, 'total' => 1]]);
        $logger->info('rollback_complete', 'Rollback completed', ['backup_id' => $backupId]);
        return [
            'finished' => true,
            'response' => $this->rollbackFinalResponse($state, $jobId, $installedCore),
        ];
    }

    private function rollbackInstalledCoreState(string $jobId, array $manifest, array $plan): array
    {
        $previousBuild = (string)($plan['current_build'] ?? ($manifest['from_build'] ?? ''));
        $previousSha = (string)($plan['current_sha'] ?? ($manifest['from_sha'] ?? ''));
        $local = new LocalState($this->storageDir);

        if ($previousBuild === '' || $previousBuild === '0') {
            $state = $local->read();
            $state['state'] = 'unknown_local_core';
            $state['core_build'] = null;
            $state['source_sha'] = null;
            $state['short_sha'] = null;
            $state['last_job_id'] = $jobId;
            $local->write($state);
            return $local->read();
        }

        $local->write([
            'state' => 'installed',
            'product' => $manifest['product'] ?? $this->config['product'],
            'core_version' => $manifest['core_version'] ?? null,
            'core_build' => $previousBuild,
            'source_sha' => $previousSha !== '' ? $previousSha : null,
            'short_sha' => $previousSha !== '' ? substr($previousSha, 0, 7) : null,
            'last_job_id' => $jobId,
        ]);

        return $local->read();
    }

    /**
     * @return list<string> pending migration names, empty when nothing to run
     */
    private function pendingMigrations(): array
    {
        try {
            $connection = \Updater\Db\Connection::open($this->basePath);
            $schema = new \Api\System\Library\Database\SchemaManager();
            $migrations = new \Api\System\Library\Database\Migration\MigrationManager($schema);
            $status = $migrations->status($connection['pdo'], $connection['driver']);
            return is_array($status['pending'] ?? null) ? array_values($status['pending']) : [];
        } catch (\Throwable $e) {
            // Unknown DB state - safest to take the backup anyway.
            return ['__unknown__'];
        }
    }

    /**
     * Force-remove the updater lock file. Exposed to the admin panel so an
     * administrator can clear a stuck lock after a crashed update without
     * waiting for the TTL. Returns whether the lock was actually present.
     */
    private function forceUnlock(): JsonResponse
    {
        $manager = new LockManager($this->storageDir);
        $removed = $manager->forceRelease();
        return JsonResponse::success([
            'lock_removed' => $removed,
            'message' => $removed
                ? 'The update lock has been removed.'
                : 'No lock was present — nothing to remove.',
        ]);
    }

    private function log(string $jobId): JsonResponse
    {
        if ($jobId === '') {
            return JsonResponse::error('JOB_ID_REQUIRED', 'job_id is required', 400);
        }
        $file = $this->storageDir . '/jobs/' . basename($jobId) . '/log.jsonl';
        return JsonResponse::success(['job_id' => $jobId, 'log' => is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : []]);
    }

    private function input(): array
    {
        $json = json_decode((string)file_get_contents('php://input'), true);
        return is_array($json) ? array_replace($_POST, $json) : $_POST;
    }

    private function verifyTokenIfPresent(array $input, string $action): void
    {
        $token = $this->bearerToken() ?: (string)($input['token'] ?? '');
        // All updater mutations, including read-only preflight, require the
        // one-time session token. The CRM page obtains it through the
        // authenticated in-process bridge/session endpoint. Allowing an
        // anonymous dry-run created jobs and performed outbound package
        // checks for any internet visitor, which is an avoidable DoS/disk
        // abuse vector on shared hosting.
        if ($token === '') {
            throw new \RuntimeException('Updater token is required.');
        }
        if (!(new TokenVerifier($this->storageDir))->verify($token, $action)) {
            throw new \RuntimeException('Updater token is invalid or expired.');
        }
    }

    /**
     * Rate-limit preflight/download requests per client IP.
     *
     * These actions are allowed without a one-time token when dry_run=true so
     * the admin-updates page can drive them directly from the browser, which
     * makes them an anonymous DoS / disk-fill vector on shared hosting. We
     * limit by IP regardless of whether a token is present: TokenVerifier only
     * marks tokens used for apply/rollback, so a stolen session token must not
     * become a free pass for unlimited downloads either. The limits are
     * generous (see api/config/update.php) so the normal page flow (~2 calls
     * per update) is never affected.
     */
    private function rateLimitAnonymous(array $input, string $action): ?JsonResponse
    {
        $limits = is_array($this->config['rate_limits'] ?? null) ? $this->config['rate_limits'] : [];
        if (($limits['enabled'] ?? true) !== true) {
            return null;
        }
        $clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        if ($clientIp === '' || $clientIp === '0.0.0.0') {
            $clientIp = 'cli';
        }
        $result = (new RequestRateLimiter($this->storageDir, $limits))->check($action, $clientIp);
        if (($result['blocked'] ?? false) === true) {
            $retryAfter = max(1, (int)($result['retry_after'] ?? 1));
            return JsonResponse::error(
                'RATE_LIMITED',
                'Too many updater ' . $action . ' requests. Please try again later.',
                429,
                ['Retry-After' => (string)$retryAfter]
            );
        }
        return null;
    }

    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        return preg_match('/Bearer\s+(.+)/i', (string)$header, $m) ? trim($m[1]) : null;
    }

    private function packageHead(string $url): array
    {
        return \Updater\Util\HttpClient::head($url, (int)($this->config['timeouts']['check'] ?? 10));
    }

    private function jobId(array $input): string
    {
        $raw = (string)($input['job_id'] ?? '');
        if ($raw !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $raw)) {
            return $raw;
        }
        return 'upd_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
    }

    /**
     * Protected paths in effect for validating THIS package.
     *
     * The updater reads api/config/update.php from disk, which is the config
     * of the PREVIOUS build. When a package itself ships an updated config
     * (the file is part of add/modify), the on-disk list is stale BY DESIGN:
     * after the update the package's own config governs. Validating against
     * the stale list would reject package files that the package's config
     * deliberately unprotects.
     *
     * Legacy example: installations predating "modules ship with core updates"
     * still list modules/** in protected_paths, so they rejected packages
     * containing module files at preflight and could never update. Module
     * files are now part of core updates, and any package that ships the new
     * config retires that protection for itself; everything else in
     * protected_paths (.env, storage, uploads, backups, logs, cache, *.local
     * configs) stays enforced unconditionally.
     *
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    private function effectiveProtectedPaths(?array $manifest): array
    {
        $protected = array_values(array_map('strval', (array)$this->config['protected_paths']));
        if (is_array($manifest) && $this->manifestCarriesConfig($manifest)) {
            // Patterns the current product no longer protects and that a
            // package shipping its own config removes from the on-disk list.
            // Kept as an explicit allowlist so a stale config can never
            // permanently block a signed update. Never include the
            // always-protected runtime paths (storage, .env, ...) here.
            $retired = ['modules/**'];
            $protected = array_values(array_diff($protected, $retired));
        }
        return $protected;
    }

    /**
     * Whether the package replaces api/config/update.php on the client.
     *
     * @param array<string,mixed> $manifest
     */
    private function manifestCarriesConfig(array $manifest): bool
    {
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        foreach (['add', 'modify'] as $group) {
            foreach (is_array($files[$group] ?? null) ? $files[$group] : [] as $path) {
                if ((string)$path === 'api/config/update.php') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Step budgets with defaults (see api/config/update.php 'steps').
     *
     * @return array{max_seconds_per_request:int,max_files_per_request:int,max_rows_per_request:int,max_migrations_per_request:int,max_statements_per_request:int,lock_ttl_seconds:int}
     */
    private function stepConfig(): array
    {
        $steps = is_array($this->config['steps'] ?? null) ? $this->config['steps'] : [];
        return array_merge([
            'max_seconds_per_request' => 20,
            'max_files_per_request' => 75,
            'max_rows_per_request' => 50000,
            'max_migrations_per_request' => 1,
            'max_statements_per_request' => 500,
            'lock_ttl_seconds' => 600,
        ], $steps);
    }

    /**
     * Refresh the multi-request job lock. Returns false when the lock is held
     * by a different, still-fresh job.
     */
    private function renewLockForJob(string $jobId, array $steps): bool
    {
        return (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->renew($jobId);
    }

    private function appendJsonl(string $dir, string $file, array $row): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $file, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Keep at most $cursor lines of a jsonl accumulator. A failed chunk may
     * have appended entries for files that were never committed; trimming to
     * the committed cursor before appending the retried chunk prevents
     * duplicate entries in the final assembled report.
     */
    private function trimJsonlToCursor(string $path, int $cursor): void
    {
        if ($cursor <= 0 || !is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false || count($lines) <= $cursor) {
            return;
        }
        file_put_contents($path, implode(PHP_EOL, array_slice($lines, 0, $cursor)) . PHP_EOL);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readJsonl(string $path): array
    {
        $rows = [];
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }
        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode((string)$line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        fclose($handle);
        return $rows;
    }

    /**
     * @return array<int,string> names of staged package files
     */
    private function safeDiagnosticMessage(string $message): string
    {
        $value = trim($message);
        if ($value === '') {
            return 'Updater operation failed. Check the operation log for details.';
        }
        foreach ([
            'Unable to reach update center:' => 'Update center request failed.',
            'Update center returned HTTP ' => 'Update center returned an HTTP error.',
            'Update center returned invalid JSON' => 'Update center returned invalid data.',
            'Unable to download package.' => 'Package download failed.',
            'Downloaded package size mismatch.' => 'Downloaded package size does not match the manifest.',
            'Downloaded package sha256 mismatch.' => 'Downloaded package checksum does not match the manifest.',
            'Downloaded package is missing.' => 'Downloaded package is missing.',
            'Unable to open update package zip.' => 'Downloaded package could not be opened as a ZIP archive.',
            'Unable to extract update package.' => 'Downloaded package could not be extracted.',
        ] as $prefix => $safe) {
            if (str_starts_with($value, $prefix)) {
                return $safe;
            }
        }
        return 'Updater operation failed. Check the operation log for details.';
    }

    private function stagedNames(string $jobId): array
    {
        $listFile = $this->storageDir . '/staging/' . basename($jobId) . '.list.json';
        if (!is_file($listFile)) {
            return [];
        }
        $cached = json_decode((string)file_get_contents($listFile), true);
        $names = is_array($cached['names'] ?? null) ? $cached['names'] : [];
        return array_values(array_filter(array_map('strval', $names)));
    }
}
