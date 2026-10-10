<?php
declare(strict_types=1);

namespace Updater;

use Updater\Client\UpdateCenterClient;
use Updater\Apply\FileApplier;
use Updater\Apply\HealthChecker;
use Updater\Apply\InstalledSnapshotReader;
use Updater\Apply\MaintenanceMode;
use Updater\Apply\MigrationRunner;
use Updater\Apply\PreflightChecker;
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
use Updater\State\DeploymentMutex;
use Updater\State\BootstrapV1ContinuationBridge;
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
        $deploymentMutex = null;
        try {
            if (in_array($action, ['snapshot', 'installed_snapshot'], true)) {
                $this->verifyTokenIfPresent($input, $action);
            }
            if (in_array($action, ['apply', 'resume', 'rollback', 'force-unlock', 'snapshot', 'installed_snapshot', 'recover'], true)) {
                $deploymentMutex = new DeploymentMutex($this->basePath);
                if (!$deploymentMutex->acquire()) {
                    return JsonResponse::error('DEPLOYMENT_BUSY', 'A deployment or verification is running. Retry later.', 409);
                }
            }
            $dispatch = fn(array $safeInput): JsonResponse => match ($action) {
                'status' => $this->status(),
                'snapshot' => $this->snapshot($safeInput),
                'installed_snapshot' => $this->installedSnapshot($safeInput),
                'preflight' => $this->preflight($safeInput),
                'download' => $this->download($safeInput),
                'apply' => $this->apply($safeInput),
                'resume' => $this->resume($safeInput),
                'rollback' => $this->rollback($safeInput),
                'force-unlock' => $this->forceUnlock(),
                'recover' => $this->recover($safeInput),
                'log' => $this->log((string)($safeInput['job_id'] ?? '')),
                default => JsonResponse::error('UNKNOWN_ACTION', 'Unknown updater action', 404),
            };
            // The host lease's permanent lock inode remains flocked from
            // validation through this bounded dispatch. This closes the
            // validation/mutation TOCTOU window even if a stale coordinator
            // resumes after another run has acquired a higher generation.
            $fencedActions = ['preflight', 'download', 'apply', 'resume', 'rollback',
                'snapshot', 'installed_snapshot', 'force-unlock', 'recover'];
            if (in_array($action, $fencedActions, true)) {
                if ($action === 'installed_snapshot' && !isset($input['job_id']) && is_string($input['snapshot_id'] ?? null)) {
                    $snapshotId = strtolower((string)$input['snapshot_id']);
                    if (preg_match('/\A[a-f0-9]{32}\z/D', $snapshotId) === 1) {
                        $input['job_id'] = 'upd_' . $snapshotId;
                    }
                }

                $hasHostLease = isset($input['host_lease_token'])
                    || isset($input['host_lease_protocol'])
                    || isset($input['host_lease_run_id'])
                    || isset($input['host_lease_target_sha'])
                    || isset($input['host_lease_generation'])
                    || isset($input['host_lease_manifest_sha256']);

                if ($hasHostLease) {
                    // During the one-time bootstrap, the package may install this
                    // kernel before protocol-2 lease files are callable. Permit
                    // only the exact already-running updater job to finish its
                    // bounded apply_files phase under the still-held protocol-1
                    // host guard. The caller must pause at health, claim/bind v2,
                    // and only then continue the job.
                    if (in_array($action, ['apply', 'resume'], true)
                        && ($input['host_lease_protocol'] ?? null) === 1
                        && ($input['bootstrap_purpose'] ?? null) === 'bootstrap-demo') {
                        $jobId = (string)($input['job_id'] ?? '');
                        $state = new JobState($this->storageDir, $jobId);
                        $stored = $state->readFile('state.json') ?: [];
                        $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
                        if (in_array(($progress['phase'] ?? null), ['apply_files', 'health'], true)) {
                            // This verifies the real file and database backup
                            // artifacts and the maintenance owner before any
                            // pre-v2 continuation reaches FileApplier.
                            $this->backupCheckpointReceipt($state);
                        }
                        return (new BootstrapV1ContinuationBridge($this->basePath, $this->storageDir))
                            ->withContinuation($input, $action, $dispatch);
                    }
                    if ($action === 'preflight' && (!is_string($input['target_sha'] ?? null)
                        || !is_string($input['host_lease_target_sha'] ?? null)
                        || !hash_equals(strtolower($input['host_lease_target_sha']), strtolower($input['target_sha'])))) {
                        throw new \RuntimeException('HOST_LEASE_JOB_BINDING_REJECTED');
                    }
                    if ($action === 'installed_snapshot') {
                        $snapshotId = strtolower((string)($input['snapshot_id'] ?? ''));
                        if (preg_match('/\A[a-f0-9]{32}\z/D', $snapshotId) !== 1) {
                            throw new \RuntimeException('HOST_LEASE_JOB_BINDING_REJECTED');
                        }
                        // The candidate job is the host-lease binding. The reader
                        // independently chooses the currently installed completed
                        // job from LocalState; callers cannot select another job.
                        $input['job_id'] = 'upd_' . $snapshotId;
                    }
                    $input['_host_lease_action'] = $action;
                    return (new \Updater\State\DurableHostReleaseLease($this->basePath, null, $this->storageDir))
                        ->withLease($input, $dispatch);
                }

                // Keep the same durable inode locked through the action. Missing or
                // unsafe lease state fails closed; status-then-dispatch is racy.
                return (new \Updater\State\DurableHostReleaseLease($this->basePath, null, $this->storageDir))
                    ->withNoLease($input, $dispatch);
            }
            return $dispatch($input);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'HOST_RELEASE_BUSY')
                || str_contains($e->getMessage(), 'HOST_LEASE_BUSY')) {
                return JsonResponse::error('HOST_RELEASE_BUSY', 'An automated release or update is currently in progress.', 409);
            }
            if (str_contains($e->getMessage(), 'HOST_LEASE_RECONCILIATION_REQUIRED')) {
                return JsonResponse::error('HOST_LEASE_RECONCILIATION_REQUIRED', 'The previous release must be reconciled before updating.', 409);
            }
            if (str_contains($e->getMessage(), 'HOST_LEASE_FENCE_REJECTED')
                || str_contains($e->getMessage(), 'HOST_LEASE_JOB_BINDING_REJECTED')) {
                return JsonResponse::error('HOST_LEASE_FENCE_REJECTED', 'Current release ownership is required.', 409);
            }
            return JsonResponse::error('UPDATER_ERROR', $this->safeDiagnosticMessage($e->getMessage()), 500);
        } finally {
            $deploymentMutex?->release();
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

    /** Return one bounded page of exact installed-file evidence for a completed job. */
    private function snapshot(array $input): JsonResponse
    {
        $jobId = (string)($input['job_id'] ?? '');
        if (preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9._-]{0,79}\z/D', $jobId) !== 1) {
            return JsonResponse::error('SNAPSHOT_JOB_ID_INVALID', 'A valid job_id is required.', 400);
        }
        $cursor = filter_var($input['cursor'] ?? 0, FILTER_VALIDATE_INT);
        $limit = filter_var($input['limit'] ?? 100, FILTER_VALIDATE_INT);
        if ($cursor === false || $limit === false || $cursor < 0 || $limit < 1 || $limit > 100) {
            return JsonResponse::error('SNAPSHOT_ARGUMENT_INVALID', 'Invalid snapshot page bounds.', 400);
        }
        $updateLock = new LockManager($this->storageDir);
        if (($updateLock->isLocked() && !$updateLock->isStale())
            || is_file($this->basePath . '/storage_api/maintenance.flag')) {
            return JsonResponse::error('UPDATE_IN_PROGRESS', 'Installed snapshot is unavailable during an update.', 409);
        }
        $snapshotId = strtolower((string)($input['snapshot_id'] ?? ''));
        if (preg_match('/\A[a-f0-9]{32}\z/D', $snapshotId) !== 1) {
            return JsonResponse::error('SNAPSHOT_ID_INVALID', 'A run-bound snapshot_id is required.', 400);
        }
        if (!hash_equals('upd_' . $snapshotId, $jobId)) {
            return JsonResponse::error('SNAPSHOT_JOB_RUN_MISMATCH', 'The updater job does not belong to this release run.', 409);
        }
        $manifestPath = $this->storageDir . '/jobs/' . $jobId . '/manifest.json';
        clearstatcache(true, $manifestPath);
        $manifestStat = @lstat($manifestPath);
        if (!is_array($manifestStat) || is_link($manifestPath)
            || ($manifestStat['mode'] & 0170000) !== 0100000 || $manifestStat['size'] > 4194304) {
            return JsonResponse::error('SNAPSHOT_MANIFEST_INVALID', 'The installed job manifest is unavailable.', 409);
        }
        $manifest = json_decode((string)@file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return JsonResponse::error('SNAPSHOT_MANIFEST_INVALID', 'The installed job manifest is invalid.', 409);
        }
        $reader = new InstalledSnapshotReader(
            $this->basePath,
            $this->storageDir,
            $this->effectiveProtectedPaths($manifest)
        );
        $snapshot = $reader->read($jobId, $cursor, $limit);
        $snapshot['snapshot_id'] = $snapshotId;
        return JsonResponse::success(['snapshot' => $snapshot]);
    }

    /** Read the current installation before candidate files are changed. */
    private function installedSnapshot(array $input): JsonResponse
    {
        $candidateJob = (string)($input['job_id'] ?? '');
        $snapshotId = strtolower((string)($input['snapshot_id'] ?? ''));
        $cursor = filter_var($input['cursor'] ?? 0, FILTER_VALIDATE_INT);
        $limit = filter_var($input['limit'] ?? 100, FILTER_VALIDATE_INT);
        if (preg_match('/\Aupd_([a-f0-9]{32})\z/D', $candidateJob, $match) !== 1
            || $match[1] !== $snapshotId || $cursor === false || $limit === false
            || $cursor < 0 || $limit < 1 || $limit > 100) {
            return JsonResponse::error('INSTALLED_SNAPSHOT_ARGUMENT_INVALID', 'Invalid run-bound baseline snapshot request.', 400);
        }
        $updateLock = new LockManager($this->storageDir);
        if (($updateLock->isLocked() && !$updateLock->isStale())
            || is_file($this->basePath . '/storage_api/maintenance.flag')) {
            return JsonResponse::error('UPDATE_IN_PROGRESS', 'Installed snapshot is unavailable during an update.', 409);
        }
        $local = (new LocalState($this->storageDir))->read();
        $installedJob = $local['last_job_id'] ?? null;
        if (!is_string($installedJob)
            || preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9._-]{0,79}\z/D', $installedJob) !== 1) {
            return JsonResponse::error('INSTALLED_SNAPSHOT_BASELINE_UNAVAILABLE', 'No verified completed installation job is recorded.', 409);
        }
        try {
            $installedManifest = (new JobState($this->storageDir, $installedJob))->readFile('manifest.json');
            if (!is_array($installedManifest)) {
                return JsonResponse::error('INSTALLED_SNAPSHOT_BASELINE_INVALID', 'The installed package manifest is unavailable.', 409);
            }
            $reader = new InstalledSnapshotReader(
                $this->basePath, $this->storageDir,
                $this->effectiveProtectedPaths($installedManifest)
            );
            $snapshot = $reader->read($installedJob, $cursor, $limit);
        } catch (\Throwable $error) {
            return JsonResponse::error('INSTALLED_SNAPSHOT_READBACK_FAILED', 'The current installation could not be verified.', 409);
        }
        $snapshot['snapshot_id'] = $snapshotId;
        $snapshot['candidate_job_id'] = $candidateJob;
        $snapshot['installed_job_id'] = $installedJob;
        return JsonResponse::success(['snapshot' => $snapshot]);
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
        $targetSha = trim((string)($input['target_sha'] ?? ''));
        $installedCore = $local->read();
        $plan = $targetSha !== ''
            ? $client->updatePlanForSha($current, $targetSha,
                is_string($installedCore['source_sha'] ?? null) ? $installedCore['source_sha'] : null)
            : $client->updatePlan($current);
        $pinnedManifest = is_array($plan['manifest'] ?? null) ? $plan['manifest'] : null;
        unset($plan['manifest']);
        $state->write(['state' => 'plan_loaded', 'plan' => $plan]);
        $logger->info('plan_loaded', 'Update plan loaded', [
            'target_build' => $plan['target_build'] ?? null,
            'target_sha' => $plan['target_sha'] ?? null,
        ]);

        // Hard stream guard: a production installation must never receive an
        // update resolved to a stream other than its configured product (e.g.
        // the develop stream). This is the client-side counterpart of the
        // update center's DomainRouter: it keeps a misrouted request (missing
        // installation_domain on an old updater, a stale stored product, or a
        // misconfigured center) from ever applying unreviewed develop code on
        // a production domain.
        $streamError = $this->streamMismatchReason($plan, $client);
        if ($streamError !== null) {
            $state->write([
                'state' => 'failed',
                'can_resume' => false,
                'can_rollback' => false,
                'error' => $streamError,
                'error_code' => 'STREAM_MISMATCH',
                'failed_checks' => ['stream_mismatch'],
            ]);
            $logger->error('stream_mismatch', 'Update stream rejected', ['error' => $streamError]);
            return JsonResponse::error('STREAM_MISMATCH', $streamError, 409);
        }

        $package = $plan['recommended_package'] ?? null;
        if (!is_array($package)) {
            $report = ['ok' => true, 'update_available' => false, 'current_build' => $current,
                'target_build' => (string)($plan['target_build'] ?? $current),
                'target_sha' => (string)($plan['target_sha'] ?? ''),
                'checks' => ['no_update' => true]];
            $state->writeFile('plan.json', $plan);
            $state->writeFile('preflight.json', $report);
            return JsonResponse::success(['job_id' => $jobId, 'preflight' => $report]);
        }

        $manifest = $pinnedManifest ?? $client->getJson((string)($package['manifest_url'] ?? ''));
        if ($manifest === []) {
            throw new \UnexpectedValueException('PINNED_TARGET_MANIFEST_MISSING');
        }
        $expectedProduct = UpdateCenterClient::expectedProductForDomain(
            (string)$this->config['product'],
            $client->installationDomain()
        );
        $verifier = new ManifestVerifier((string)$this->config['public_key_path'], $this->effectiveProtectedPaths($manifest));
        $manifestReport = $verifier->verify($manifest, $package, $expectedProduct);
        $packageHead = $this->packageHead((string)$package['url']);

        $checks = [
            'update_center' => true,
            'manifest_schema_version' => $manifestReport['schema_version'],
            'manifest_product' => $manifestReport['product'],
            'manifest_signature' => $manifestReport['manifest_signature'],
            'manifest_package_sha' => $manifestReport['package_sha'],
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
        // Platform requirements the package declares (php/mysql/updater/
        // min_core_build) — evaluated BEFORE any mutation so a host that
        // cannot run the new build is rejected while the old one still works.
        $checks += PreflightChecker::check($manifest, $this->basePath, $current);
        $ok = !in_array(false, $checks, true);

        $report = [
            'ok' => $ok,
            'dry_run' => (bool)($input['dry_run'] ?? true),
            'current_build' => $current,
            'target_build' => $plan['target_build'] ?? null,
            'target_sha' => $plan['target_sha'] ?? ($manifest['to_sha'] ?? null),
            'package' => $package,
            'checks' => $checks,
            'package_head' => $packageHead,
            'manifest_report' => $manifestReport,
            'manifest_sha256' => hash('sha256', $this->canonicalJson($manifest)),
            'requirements' => is_array($manifest['requirements'] ?? null) ? $manifest['requirements'] : null,
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
                (new MaintenanceMode($this->basePath))->enable($jobId);
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
                // Re-validate the stream that produced this job before any
                // mutation (the same guard preflight already ran): a job file
                // written by an older updater or restored from a backup must
                // not be able to apply a develop-stream package on a
                // production installation.
                $storedPlan = $state->readFile('plan.json');
                if (is_array($storedPlan)) {
                    $streamError = $this->streamMismatchReason($storedPlan, new UpdateCenterClient($this->config));
                    if ($streamError !== null) {
                        return JsonResponse::error('STREAM_MISMATCH', $streamError, 409);
                    }
                }
                $this->verifyTokenIfPresent($input, 'apply');
                (new LockManager($this->storageDir, (int)$steps['lock_ttl_seconds']))->acquire($jobId);
                (new MaintenanceMode($this->basePath))->enable($jobId);
                // The whole apply is a resumable step machine: the file, DB
                // backup, apply and migration phases all persist their cursor
                // (apply_files even trims its journal back to the committed
                // cursor), so a browser that closes, a dropped connection or a
                // reload during the apply must be able to continue the SAME
                // job. Marking it can_resume=false made an interrupted apply
                // look finished-and-dead: maintenance stayed held and the page
                // offered a fresh preflight instead of a continuation, which
                // then collided with the lock the interrupted job still held.
                $state->write(['state' => 'applying', 'can_resume' => true, 'can_rollback' => false]);
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
                    if (($input['pause_after_backup'] ?? false) === true && $newPhase === 'apply_files') {
                        // Return a durable checkpoint after both backup decisions
                        // are recorded but before the first installed file mutates.
                        break;
                    }
                    if (($input['pause_after_files'] ?? false) === true && $newPhase === 'health') {
                        // The v1→v2 handoff is a durable state boundary after
                        // every package file (including the new guard/kernel)
                        // has landed, but before health/migrations/finalize.
                        break;
                    }
                }
                if ($budget->exhausted()) {
                    break;
                }
            }

            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            $response = ['job_id' => $jobId, 'continue' => true, 'progress' => $progress];
            if (($input['pause_after_backup'] ?? false) === true && ($progress['phase'] ?? null) === 'apply_files') {
                $response['backup_checkpoint'] = $this->backupCheckpointReceipt($state);
                $response['backup_gate_paused'] = true;
            }
            if (($input['pause_after_files'] ?? false) === true && ($progress['phase'] ?? null) === 'health') {
                $response['backup_checkpoint'] = $this->backupCheckpointReceipt($state);
                $response['bootstrap_handoff_required'] = true;
                $response['handoff_phase'] = 'health';
                $response['host_guard_protocol_required'] = 2;
            }
            return JsonResponse::success($response);
        } catch (\Throwable $e) {
            // Guard 3: maintenance stays ON when it was already held before
            // this run (previous failed attempt) or when the failure happened
            // after the file tree / DB was mutated. Only a clean pre-mutation
            // failure with maintenance we enabled ourselves may turn it off.
            $stored = $state->readFile('state.json') ?: [];
            $progress = is_array($stored['progress'] ?? null) ? $stored['progress'] : [];
            $phase = (string)($progress['phase'] ?? '');
            $systemMutated = $phase === '' || in_array($phase, ['apply_files', 'health', 'migrate', 'finalize'], true);
            $maintenanceHeld = $maintenanceWasOn || $systemMutated;
            if (!$maintenanceHeld) {
                (new MaintenanceMode($this->basePath))->disable($jobId);
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
            'backup_db' => $this->applyPhaseBackupDb($state, $progress, $budget, $steps, $logger),
            'apply_files' => $this->applyPhaseApplyFiles($state, $progress, $budget, $steps, $logger),
            'health' => $this->applyPhaseHealth($state),
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
            return $this->beginDatabaseBackup($state, $logger);
        }
        $plan = $state->readFile('apply-plan.json') ?: [];
        $files = is_array($plan['files_for_backup'] ?? null) ? $plan['files_for_backup'] : [];
        $cursor = (int)($progress['cursor'] ?? 0);
        if ($cursor >= count($files)) {
            // Nothing left to back up (empty file list, or a completed backup
            // whose transition crashed). Emit a consistent empty backup
            // manifest if needed, then move to the DB backup phase.
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
            return $this->beginDatabaseBackup($state, $logger);
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
        return $this->beginDatabaseBackup($state, $logger);
    }

    private function canonicalJson(array $value): string
    {
        $normalize = static function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) { return $item; }
            if (!array_is_list($item)) { ksort($item); }
            foreach ($item as $key => $child) { $item[$key] = $normalize($child); }
            return $item;
        };
        return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function verifyFileBackupArtifacts(array $backup): bool
    {
        $backupId = (string)($backup['backup_id'] ?? '');
        if (preg_match('/\Abackup_[A-Za-z0-9_-]{1,100}\z/D', $backupId) !== 1) { return false; }
        $dir = $this->storageDir . '/backups/' . $backupId;
        if (($backup['items'] ?? []) === []) { return true; }
        $manifestPath = $dir . '/manifest.json';
        $manifest = $this->readVerifiedBackupJson($manifestPath, 8 * 1024 * 1024);
        if (!is_array($manifest) || ($manifest['backup_id'] ?? null) !== $backupId
            || ($manifest['job_id'] ?? null) !== ($backup['job_id'] ?? null)
            || !is_array($manifest['items'] ?? null) || count($manifest['items']) !== count($backup['items'])) { return false; }
        foreach ($backup['items'] as $item) {
            if (!is_array($item) || !is_string($item['path'] ?? null)) { return false; }
            $relative = str_replace('\\', '/', $item['path']);
            if ($relative === '' || str_starts_with($relative, '/')
                || preg_match('/(?:^|\/)\.\.(?:\/|$)/', $relative) === 1) { return false; }
            if (($item['existed'] ?? false) !== true) { continue; }
            $path = $dir . '/files/' . $relative;
            if (!$this->safeArtifactFile($path)) { return false; }
            $sha = hash_file('sha256', $path);
            if (!is_string($sha) || !is_string($item['sha256'] ?? null)
                || !hash_equals(strtolower($item['sha256']), $sha)
                || (int)@filesize($path) !== (int)($item['size_bytes'] ?? -1)) { return false; }
        }
        return true;
    }

    private function verifyDatabaseBackupArtifacts(string $backupId, array $report, string $jobId): bool
    {
        $dir = $this->storageDir . '/backups/' . basename($backupId) . '/db';
        $manifestPath = $dir . '/manifest.json';
        $manifest = $this->readVerifiedBackupJson($manifestPath, 4 * 1024 * 1024);
        if (!is_array($manifest) || ($manifest['ok'] ?? false) !== true
            || ($manifest['job_id'] ?? null) !== $jobId || ($report['job_id'] ?? null) !== $jobId) { return false; }
        if (($manifest['driver'] ?? null) === 'sqlite') {
            $relative = $manifest['schema_file'] ?? null;
            $sha = $manifest['file_sha256'] ?? null;
            return is_string($relative) && $relative === 'db/crm.sqlite'
                && is_string($sha) && $this->verifyArtifactHash($this->storageDir . '/backups/' . basename($backupId) . '/' . $relative, $sha);
        }
        if (($manifest['driver'] ?? null) !== 'mysql') { return false; }
        $schema = $manifest['schema_file'] ?? null;
        $triggers = $manifest['triggers_file'] ?? null;
        if (!is_string($schema) || $schema !== 'db/schema.sql' || !is_string($manifest['schema_sha256'] ?? null)
            || !$this->verifyArtifactHash($this->storageDir . '/backups/' . basename($backupId) . '/' . $schema, $manifest['schema_sha256'])) { return false; }
        if (!is_string($triggers) || $triggers !== 'db/triggers.sql' || !is_string($manifest['triggers_sha256'] ?? null)
            || ($manifest['triggers_sha256'] !== '' && !$this->verifyArtifactHash($this->storageDir . '/backups/' . basename($backupId) . '/' . $triggers, $manifest['triggers_sha256']))) { return false; }
        $tables = $manifest['table_files'] ?? null;
        if (!is_array($tables)) { return false; }
        foreach ($tables as $entry) {
            if (!is_array($entry) || !is_string($entry['file'] ?? null) || !is_string($entry['sha256'] ?? null)
                || preg_match('/\Adb\/tables\/[A-Za-z0-9_$-]+\.sql\z/D', $entry['file']) !== 1
                || !$this->verifyArtifactHash($this->storageDir . '/backups/' . basename($backupId) . '/' . $entry['file'], $entry['sha256'])) { return false; }
        }
        return true;
    }

    private function verifyArtifactHash(string $path, string $expected): bool
    {
        if (!$this->safeArtifactFile($path)) { return false; }
        $actual = hash_file('sha256', $path);
        return is_string($actual) && preg_match('/\A[a-f0-9]{64}\z/iD', $expected) === 1
            && hash_equals(strtolower($expected), $actual);
    }

    private function safeArtifactFile(string $path): bool
    {
        $root = rtrim($this->storageDir, '/') . '/backups';
        $relative = substr($path, strlen($root) + 1);
        if ($relative === false || $relative === '' || str_starts_with($relative, '/')
            || preg_match('/(?:^|\/)\.\.(?:\/|$)/', $relative) === 1) { return false; }
        $cursor = $root;
        foreach (explode('/', $relative) as $index => $part) {
            $cursor .= '/' . $part;
            clearstatcache(true, $cursor);
            $stat = @lstat($cursor);
            if (!is_array($stat) || is_link($cursor) || $stat['uid'] !== (int)@fileowner($root)) { return false; }
            if ($index < count(explode('/', $relative)) - 1 && ($stat['mode'] & 0170000) !== 0040000) { return false; }
            if ($index === count(explode('/', $relative)) - 1 && (($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1 || $stat['size'] > 512 * 1024 * 1024)) { return false; }
        }
        return true;
    }

    private function readVerifiedBackupJson(string $path, int $maxBytes): ?array
    {
        if (!$this->safeArtifactFile($path) || @filesize($path) > $maxBytes) { return null; }
        $value = json_decode((string)@file_get_contents($path), true);
        return is_array($value) ? $value : null;
    }

    private function backupCheckpointReceipt(JobState $state): array
    {
        $fileBackup = $state->readFile('backup.json');
        $dbBackup = $state->readFile('db_backup.json');
        $job = $state->readFile('state.json');
        if (!is_array($fileBackup) || !is_array($dbBackup)
            || !is_string($fileBackup['backup_id'] ?? null)
            || !is_array($fileBackup['items'] ?? null)
            || !is_string($job['job_id'] ?? null)
            || ($fileBackup['job_id'] ?? null) !== $job['job_id']
            || ($dbBackup['job_id'] ?? null) !== $job['job_id']
            || ($dbBackup['ok'] ?? false) !== true
            || !$this->verifyFileBackupArtifacts($fileBackup)
            || !$this->verifyDatabaseBackupArtifacts($fileBackup['backup_id'], $dbBackup, $job['job_id'])) {
            throw new \RuntimeException('BACKUP_CHECKPOINT_EVIDENCE_INVALID');
        }
        $fileJson = json_encode($fileBackup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $dbJson = json_encode($dbBackup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $maintenanceFlag = is_file($this->basePath . '/storage_api/maintenance.flag')
            && !is_link($this->basePath . '/storage_api/maintenance.flag');
        if (($job['maintenance_held'] ?? false) !== true || !$maintenanceFlag) {
            throw new \RuntimeException('BACKUP_CHECKPOINT_MAINTENANCE_NOT_HELD');
        }
        return ['job_id' => $job['job_id'], 'state' => $job['state'] ?? null,
            'maintenance_held' => true, 'maintenance_flag' => true,
            'file_backup' => ['backup_id' => $fileBackup['backup_id'], 'sha256' => hash('sha256', $fileJson), 'items' => count($fileBackup['items'])],
            'database_backup' => ['ok' => true, 'sha256' => hash('sha256', $dbJson)]];
    }

    /** Server-side verifier used by the bootstrap v1→v2 lease handoff. */
    public function verifiedBootstrapBackupCheckpoint(string $jobId): array
    {
        if (preg_match('/\Aupd_[a-f0-9]{32}\z/D', $jobId) !== 1) {
            throw new \RuntimeException('BOOTSTRAP_BACKUP_JOB_INVALID');
        }
        return $this->backupCheckpointReceipt(new JobState($this->storageDir, $jobId));
    }

    private function beginDatabaseBackup(JobState $state, UpdateLogger $logger): array
    {
        $backup = $state->readFile('backup.json');
        if (!is_array($backup) || !is_string($backup['backup_id'] ?? null) || !is_array($backup['items'] ?? null)) {
            throw new \RuntimeException('Verified file backup is required before database backup.');
        }
        $state->write(['progress' => ['phase' => 'backup_db', 'cursor' => null, 'done' => 0, 'total' => 0]]);
        $logger->info('database_backup_started', 'Creating database backup before applying files');
        return ['next' => true];
    }

    private function beginApplyFiles(JobState $state, UpdateLogger $logger): array
    {
        $backup = $state->readFile('backup.json');
        $dbBackup = $state->readFile('db_backup.json');
        $job = $state->readFile('state.json') ?: [];
        $dbBackupUsable = is_array($dbBackup) && ($dbBackup['ok'] ?? false) === true;
        if (!is_array($backup) || !is_string($backup['backup_id'] ?? null)
            || !is_array($backup['items'] ?? null) || !$dbBackupUsable
            || !$this->verifyFileBackupArtifacts($backup)
            || !$this->verifyDatabaseBackupArtifacts((string)$backup['backup_id'], $dbBackup, (string)($job['job_id'] ?? ''))) {
            throw new \RuntimeException('Verified file and database backup decisions are required before applying files.');
        }
        $plan = $state->readFile('apply-plan.json') ?: [];
        $total = (int)($plan['apply_total'] ?? 0);
        $state->write(['progress' => ['phase' => 'apply_files', 'cursor' => 0, 'done' => 0, 'total' => $total]]);
        $logger->info('files_apply_started', 'Applying package files after backups');
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
        $manifest = $state->readFile('manifest.json');
        $health = (new HealthChecker($this->basePath))->check(is_array($manifest) && $manifest !== [] ? $manifest : null);
        $state->writeFile('health.json', $health);
        if (($health['ok'] ?? false) !== true) {
            throw new \RuntimeException('Post-apply health check failed.');
        }
        $backup = $state->readFile('backup.json');
        $dbBackup = $state->readFile('db_backup.json');
        $dbBackupUsable = is_array($dbBackup) && ($dbBackup['ok'] ?? false) === true;
        if (!is_array($backup) || !is_array($backup['items'] ?? null) || !$dbBackupUsable) {
            throw new \RuntimeException('Verified backup evidence is required before migration.');
        }
        $state->write(['progress' => ['phase' => 'migrate', 'cursor' => [], 'done' => 0, 'total' => count($this->pendingMigrations()), 'executed' => []]]);
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

        // Always create a DB snapshot before applying files. The updater
        // cannot infer whether the downloaded package introduces migrations
        // from the currently installed migration registry.
        if (($this->config['db_backup']['enabled'] ?? true) !== true) {
            throw new \RuntimeException('Database backup is disabled; refusing to mutate installed files.');
        }

        $backupDir = $this->storageDir . '/backups/' . basename($backupId);
        $report = $manager->backup($backupDir, $jobId, $cursor, $budget, (int)$steps['max_rows_per_request']);
        if (($report['done'] ?? false) !== true) {
            // The DB dump resumes per table, so only the completed-row count is
            // known while it runs. Do NOT mirror it into `total`: the admin page
            // reads a present `total` as a known step count and would print
            // "step 150000 of 150000" at every step (150000 being the running
            // total, not a limit). `total` stays absent until the dump finishes.
            $progress = [
                'phase' => 'backup_db',
                'cursor' => $report['cursor'] ?? [],
                'done' => (int)($report['rows_done'] ?? 0),
            ];
            // When the manager can cheaply report the exact total, show real
            // progress instead: tables already dumped out of all tables.
            $tablesTotal = (int)($report['tables_total'] ?? 0);
            $tablesDone = (int)($report['tables_done'] ?? 0);
            if ($tablesTotal > 0) {
                $progress['tables_done'] = $tablesDone;
                $progress['tables_total'] = $tablesTotal;
            }
            $state->write(['progress' => $progress]);
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
        return $this->finishDatabaseBackup($state, $logger, $report);
    }

    private function finishDatabaseBackup(JobState $state, UpdateLogger $logger, array $dbBackup): array
    {
        $state->writeFile('db_backup.json', $dbBackup);
        $ok = ($dbBackup['ok'] ?? false) === true;
        if (!$ok) {
            throw new \RuntimeException('Database backup failed before file application: ' . (string)($dbBackup['reason'] ?? $dbBackup['error'] ?? 'unknown'));
        }
        $logger->info('db_backup_created', 'Database backup created before applying files');
        return $this->beginApplyFiles($state, $logger);
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
        (new MaintenanceMode($this->basePath))->disable($jobId);
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
        $jobId = (string)($input['job_id'] ?? '');
        if (preg_match('/\Aupd_[a-f0-9]{32}\z/D', $jobId) !== 1) {
            return JsonResponse::error('RESUME_JOB_ID_INVALID', 'A run-bound job_id is required.', 400);
        }
        $state = new JobState($this->storageDir, $jobId);
        $latest = $state->readFile('state.json');
        if (!is_array($latest) || ($latest['job_id'] ?? null) !== $jobId) {
            return JsonResponse::error('RESUME_JOB_NOT_FOUND', 'The requested updater job is unavailable.', 404);
        }
        $result = ['latest_job' => $latest, 'message' => 'Resume/status inspection is available for staged and applied jobs.'];
        $progress = $latest['progress'] ?? null;
        if (is_array($progress) && in_array(($progress['phase'] ?? null), ['apply_files', 'health'], true)) {
            $result['backup_checkpoint'] = $this->backupCheckpointReceipt($state);
        }
        return JsonResponse::success($result);
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
            //
            // The stored progress must also BE a rollback phase: a job that
            // failed while applying files (state 'failed', phase 'apply_files')
            // or a rollback attempt that died before writing its own progress
            // (state 'rollback_failed', phase still 'apply_files') used to be
            // treated as a continuation and aborted with "Unknown rollback
            // phase: apply_files", leaving the installation in maintenance mode
            // with no way back through the UI. Such a job now restarts the
            // rollback from the database step.
            $rollbackPhases = ['restore_db', 'restore_files', 'health', 'finalize'];
            $currentPhase = $progress !== null ? (string)($progress['phase'] ?? '') : '';
            $isContinuation = $progress !== null
                && $currentPhase !== ''
                && in_array($currentPhase, $rollbackPhases, true)
                && in_array((string)($stored['state'] ?? ''), ['rolling_back', 'rollback_failed'], true);

            if ($isContinuation) {
                $this->verifyTokenIfPresent($input, 'rollback_step');
                if (!$this->renewLockForJob($jobId, $steps)) {
                    throw new \RuntimeException('Rollback lock was lost; another update may have started.');
                }
                (new MaintenanceMode($this->basePath))->enable($jobId);
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
            $response = ['job_id' => $jobId, 'continue' => true, 'progress' => $progress];
            if (($input['pause_after_backup'] ?? false) === true && ($progress['phase'] ?? null) === 'apply_files') {
                $response['backup_checkpoint'] = $this->backupCheckpointReceipt($state);
                $response['backup_gate_paused'] = true;
            }
            return JsonResponse::success($response);
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
                (new MaintenanceMode($this->basePath))->disable($jobId);
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
            default => throw new \RuntimeException('Unknown rollback phase: ' . $phase . ' (expected one of: '
                . implode(', ', ['restore_db', 'restore_files', 'health', 'finalize']) . ')'),
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
        $manifest = $state->readFile('manifest.json');
        $health = (new HealthChecker($this->basePath))->check(is_array($manifest) && $manifest !== [] ? $manifest : null);
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
        (new MaintenanceMode($this->basePath))->disable($jobId);
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

    /**
     * Self-healing path for an update the installation cannot continue.
     *
     * A job interrupted by a closed tab, a dropped connection or a killed PHP
     * process normally resumes on the next request, but a job left by an older
     * kernel (can_resume=false), one whose progress was lost, or one whose
     * stored state no longer matches its progress cannot be continued. Until
     * now that stranded the installation behind maintenance mode and only a
     * server administrator could clear it.
     *
     * The caller vouches that the job is not making progress (the admin page
     * retries a continuation first and only then asks for recovery). This
     * action stops the job, releases the update lock, clears maintenance mode
     * when the flag belongs to that job, and records the failure so the normal
     * "prepare a fresh update" flow works again. It never touches files or the
     * database: rollback stays an explicit, separate decision.
     */
    private function recover(array $input): JsonResponse
    {
        $jobId = (string)($input['job_id'] ?? '');
        if (preg_match('/\A[A-Za-z0-9._-]{1,128}\z/D', $jobId) !== 1) {
            return JsonResponse::error('RECOVERY_JOB_ID_INVALID', 'A valid job_id is required for recovery.', 400);
        }
        $state = new JobState($this->storageDir, $jobId);
        $stored = $state->readFile('state.json');
        if (!is_array($stored) || ($stored['job_id'] ?? null) !== $jobId) {
            return JsonResponse::error('RECOVERY_JOB_NOT_FOUND', 'The requested updater job is unavailable.', 404);
        }
        // Refuse to disturb a job that already reached a terminal state: there
        // is nothing to recover and the caller would be retrying a stale tab.
        // A job this very action already closed (RECOVERED_INTERRUPTED) is
        // reported the same way, so a repeated call is idempotent.
        $stateName = (string)($stored['state'] ?? '');
        $alreadyRecovered = $stateName === 'failed'
            && (string)($stored['error_code'] ?? '') === 'RECOVERED_INTERRUPTED';
        if ($alreadyRecovered || in_array($stateName, ['applied', 'rolled_back', 'failed'], true)) {
            return JsonResponse::success([
                'recovered' => false,
                'reason' => 'job_already_terminal',
                'state' => $stateName,
            ]);
        }

        (new LockManager($this->storageDir, (int)($this->stepConfig()['lock_ttl_seconds'] ?? 600)))->release($jobId);

        $maintenanceCleared = false;
        $maintenance = new MaintenanceMode($this->basePath);
        if (is_file($this->basePath . '/storage_api/maintenance.flag')) {
            try {
                // disable() verifies the flag belongs to this job before
                // unlinking it; a flag owned by another operation stays.
                $maintenance->disable($jobId);
                $maintenanceCleared = true;
            } catch (\Throwable) {
                // Another operation owns maintenance mode; leave it alone.
            }
        }

        $previousState = (string)($stored['state'] ?? '');
        $state->write([
            'state' => 'failed',
            'can_resume' => false,
            'can_rollback' => (bool)($stored['can_rollback'] ?? false),
            'error' => 'Recovered from an interrupted update.',
            'error_code' => 'RECOVERED_INTERRUPTED',
            'maintenance_held' => false,
            'recovered_at' => gmdate('c'),
        ]);
        (new UpdateLogger($this->storageDir, $jobId))
            ->error('recovered', 'Interrupted update closed by the self-healing path', [
                'previous_state' => $previousState,
                'maintenance_cleared' => $maintenanceCleared,
            ]);

        return JsonResponse::success([
            'recovered' => true,
            'job_id' => $jobId,
            'previous_state' => $previousState,
            'maintenance_cleared' => $maintenanceCleared,
            'can_rollback' => (bool)($stored['can_rollback'] ?? false),
            'message' => 'The interrupted update was closed; a fresh update can be prepared.',
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
        $token = (string)($input['token'] ?? '') ?: $this->bearerToken();
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
     * Reason why the update stream resolved by the update center must be
     * rejected for this installation, or null when the stream is acceptable.
     *
     * @param array<string,mixed> $plan
     */
    private function streamMismatchReason(array $plan, UpdateCenterClient $client): ?string
    {
        $configured = (string)($this->config['product'] ?? '');
        $stream = (string)($plan['stream'] ?? $plan['product'] ?? '');
        if ($stream === '') {
            return null;
        }
        $domain = $client->installationDomain();
        if (UpdateCenterClient::isStreamAllowedForDomain($configured, $stream, $domain)) {
            return null;
        }
        $where = $domain !== '' ? $domain : 'unknown';
        return 'Update center resolved stream "' . $stream . '", but this installation is configured for product "' . $configured . '" on domain "' . $where . '". Refusing to apply an update from another stream so a production installation can never receive develop-stream code.';
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
