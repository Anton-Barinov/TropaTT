<?php
declare(strict_types=1);

namespace Updater;

use Updater\Client\UpdateCenterClient;
use Updater\Apply\FileApplier;
use Updater\Apply\HealthChecker;
use Updater\Apply\MaintenanceMode;
use Updater\Apply\MigrationRunner;
use Updater\Backup\FileBackupManager;
use Updater\Http\JsonResponse;
use Updater\Log\UpdateLogger;
use Updater\Package\PackageDownloader;
use Updater\Package\PackageExtractor;
use Updater\Rollback\RollbackManager;
use Updater\Security\ManifestVerifier;
use Updater\Security\TokenVerifier;
use Updater\State\JobState;
use Updater\State\LocalState;
use Updater\State\LockManager;

final class UpdaterKernel
{
    private array $config;
    private string $storageDir;

    public function __construct(private readonly string $basePath)
    {
        $this->config = require $basePath . '/api/config/update.php';
        $this->storageDir = (string)$this->config['storage_dir'];
        foreach (['sessions', 'jobs', 'packages', 'staging', 'backups', 'locks', 'logs'] as $dir) {
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

        try {
            $response = match ($action) {
                'status' => $this->status(),
                'preflight' => $this->preflight($input),
                'download' => $this->download($input),
                'apply' => $this->apply($input),
                'resume' => $this->resume($input),
                'rollback' => $this->rollback($input),
                'log' => $this->log((string)($_GET['job_id'] ?? $input['job_id'] ?? '')),
                default => JsonResponse::error('UNKNOWN_ACTION', 'Unknown updater action', 404),
            };
        } catch (\Throwable $e) {
            $response = JsonResponse::error('UPDATER_ERROR', $e->getMessage(), 500);
        }

        $response->send();
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
        $jobId = $this->jobId($input);
        $logger = new UpdateLogger($this->storageDir, $jobId);
        $state = new JobState($this->storageDir, $jobId);
        $state->write(['state' => 'created', 'can_resume' => true, 'can_rollback' => false]);

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
        $verifier = new ManifestVerifier((string)$this->config['public_key_path'], (array)$this->config['protected_paths']);
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
            'modules_note' => 'modules/** are excluded from core updates and will not be changed.',
        ];
        $state->writeFile('plan.json', $plan);
        $state->writeFile('manifest.json', $manifest);
        $state->writeFile('preflight.json', $report);
        $state->write(['state' => $ok ? 'preflight_passed' : 'failed', 'can_resume' => $ok, 'can_rollback' => false]);
        return JsonResponse::success(['job_id' => $jobId, 'preflight' => $report]);
    }

    private function download(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'download');
        $jobId = $this->jobId($input);
        $state = new JobState($this->storageDir, $jobId);
        $plan = $state->readFile('plan.json');
        if (!$plan) {
            $preflight = $this->preflight(array_merge($input, ['job_id' => $jobId, 'dry_run' => true]));
            if ($preflight->status >= 400) {
                return $preflight;
            }
            $plan = $state->readFile('plan.json');
        }
        $package = $plan['recommended_package'] ?? null;
        if (!is_array($package)) {
            return JsonResponse::error('NO_PACKAGE', 'No package available for this job', 409);
        }
        $path = (new PackageDownloader($this->storageDir))->download($jobId, $package);
        $files = (new PackageExtractor($this->storageDir, (array)$this->config['protected_paths']))->extract($jobId, $path);
        $state->write([
            'state' => 'staging_ready',
            'can_resume' => true,
            'can_rollback' => false,
            'package_path' => $path,
            'staged_file_count' => count($files),
            'staged_files_preview' => array_slice($files, 0, 50),
        ]);

        return JsonResponse::success([
            'job_id' => $jobId,
            'package' => [
                'path' => $path,
                'exists' => is_file($path),
                'size_bytes' => is_file($path) ? filesize($path) : null,
            ],
            'staging' => [
                'file_count' => count($files),
                'preview' => array_slice($files, 0, 20),
                'preview_truncated' => count($files) > 20,
            ],
        ]);
    }

    private function apply(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'apply');
        if (($input['confirm_apply'] ?? false) !== true) {
            return JsonResponse::error('CONFIRM_APPLY_REQUIRED', 'Real apply requires confirm_apply=true after successful dry-run preflight', 409);
        }

        $jobId = $this->jobId($input);
        $state = new JobState($this->storageDir, $jobId);
        $preflight = $state->readFile('preflight.json');
        $manifest = $state->readFile('manifest.json');
        if (!is_array($preflight) || ($preflight['ok'] ?? false) !== true || !is_array($manifest)) {
            return JsonResponse::error('PREFLIGHT_REQUIRED', 'Successful preflight is required before apply.', 409);
        }

        $lock = new LockManager($this->storageDir);
        $maintenance = new MaintenanceMode($this->basePath);
        $logger = new UpdateLogger($this->storageDir, $jobId);

        try {
            $lock->acquire($jobId);
            $state->write(['state' => 'applying', 'can_resume' => false, 'can_rollback' => false]);
            $maintenance->enable($jobId);
            $logger->info('maintenance_enabled', 'Maintenance mode enabled');

            $applier = new FileApplier($this->basePath, $this->storageDir, (array)$this->config['protected_paths']);
            $files = $applier->filesFromManifest($manifest);
            $filesForBackup = array_values(array_unique(array_merge($files['add'], $files['modify'], $files['delete'])));
            $backup = (new FileBackupManager($this->basePath, $this->storageDir))->backup($jobId, $filesForBackup);
            $state->writeFile('backup.json', $backup);
            $state->write(['state' => 'backup_created', 'backup_id' => $backup['backup_id'] ?? null, 'can_rollback' => true]);
            $logger->info('backup_created', 'File backup created', ['backup_id' => $backup['backup_id'] ?? null]);

            $applied = $applier->apply($jobId, $manifest);
            $state->writeFile('applied.json', $applied);
            $logger->info('files_applied', 'Files applied', ['count' => $applied['count'] ?? 0]);

            $health = (new HealthChecker($this->basePath))->check();
            $state->writeFile('health.json', $health);
            if (($health['ok'] ?? false) !== true) {
                throw new \RuntimeException('Post-apply health check failed.');
            }

            $migrations = $this->runMigrations($state, $logger);

            (new LocalState($this->storageDir))->write([
                'state' => 'installed',
                'product' => $manifest['product'] ?? $this->config['product'],
                'core_version' => $manifest['core_version'] ?? null,
                'core_build' => $manifest['to_build'] ?? null,
                'source_sha' => $manifest['to_sha'] ?? null,
                'short_sha' => $manifest['short_sha'] ?? null,
                'last_job_id' => $jobId,
            ]);

            $maintenance->disable();
            $lock->release();
            $state->write(['state' => 'applied', 'can_resume' => false, 'can_rollback' => true, 'finished_at' => gmdate('c')]);
            $logger->info('apply_complete', 'Update applied successfully');

            return JsonResponse::success([
                'job_id' => $jobId,
                'backup' => $backup,
                'applied' => $applied,
                'health' => $health,
                'migrations' => $migrations,
                'installed_core' => (new LocalState($this->storageDir))->read(),
            ]);
        } catch (\Throwable $e) {
            $maintenance->disable();
            $lock->release();
            $state->write(['state' => 'failed', 'error' => $e->getMessage(), 'can_rollback' => true]);
            $logger->error('apply_failed', 'Update apply failed', ['error' => $e->getMessage()]);
            return JsonResponse::error('APPLY_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Best-effort database migration run after files have been applied.
     * Uses the freshly deployed code so newly added migrations are picked
     * up. Failures are recorded but do not fail the whole apply: the admin
     * can retry migrations or roll back from the update page.
     *
     * NOTE: when the package itself updates this very UpdaterKernel file, the
     * in-memory copy of this class is still the pre-update bytecode, so
     * MigrationRunner here refers to the class from the already-loaded
     * namespace imports. Keep MigrationRunner's signature stable across
     * updater self-updates so this method keeps working with fresh code.
     */
    private function runMigrations(JobState $state, UpdateLogger $logger): array
    {
        try {
            $report = (new MigrationRunner($this->basePath))->run();
            $state->writeFile('migrations.json', $report);
            $logger->info('migrations_applied', 'Database migrations applied', [
                'executed' => $report['executed'] ?? [],
                'pending_after' => $report['pending_after'] ?? [],
            ]);
            return $report;
        } catch (\Throwable $e) {
            $report = ['ok' => false, 'error' => $e->getMessage()];
            $state->writeFile('migrations.json', $report);
            $logger->error('migrations_failed', 'Database migrations failed', ['error' => $e->getMessage()]);
            return $report;
        }
    }

    private function resume(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'resume');
        return JsonResponse::success(['latest_job' => (new JobState($this->storageDir))->latest(), 'message' => 'Resume/status inspection is available for staged and applied jobs.']);
    }

    private function rollback(array $input): JsonResponse
    {
        $this->verifyTokenIfPresent($input, 'rollback');
        $jobId = $this->jobId($input);
        $state = new JobState($this->storageDir, $jobId);
        $backup = $state->readFile('backup.json');
        $manifest = $state->readFile('manifest.json') ?: [];
        $plan = $state->readFile('plan.json') ?: [];
        $backupId = (string)($input['backup_id'] ?? ($backup['backup_id'] ?? ''));
        if ($backupId === '') {
            return JsonResponse::error('ROLLBACK_REQUIRES_BACKUP', 'No backup is available for rollback.', 409);
        }

        $lock = new LockManager($this->storageDir);
        $maintenance = new MaintenanceMode($this->basePath);
        $logger = new UpdateLogger($this->storageDir, $jobId);

        try {
            $lock->acquire($jobId);
            $maintenance->enable($jobId);
            $state->write(['state' => 'rolling_back', 'can_resume' => false, 'can_rollback' => false]);
            $result = (new RollbackManager($this->basePath, $this->storageDir))->rollback($backupId);
            $health = (new HealthChecker($this->basePath))->check();
            if (($health['ok'] ?? false) !== true) {
                throw new \RuntimeException('Post-rollback health check failed.');
            }
            $installedCore = $this->rollbackInstalledCoreState($jobId, $manifest, $plan);
            $maintenance->disable();
            $lock->release();
            $state->write(['state' => 'rolled_back', 'rollback' => $result, 'can_resume' => false, 'can_rollback' => false, 'finished_at' => gmdate('c')]);
            $logger->info('rollback_complete', 'Rollback completed', ['backup_id' => $backupId]);
            return JsonResponse::success(['job_id' => $jobId, 'rollback' => $result, 'health' => $health, 'installed_core' => $installedCore]);
        } catch (\Throwable $e) {
            $maintenance->disable();
            $lock->release();
            $state->write(['state' => 'rollback_failed', 'error' => $e->getMessage(), 'can_rollback' => true]);
            $logger->error('rollback_failed', 'Rollback failed', ['error' => $e->getMessage()]);
            return JsonResponse::error('ROLLBACK_FAILED', $e->getMessage(), 500);
        }
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
        if ($token === '' && in_array($action, ['preflight', 'download'], true) && (bool)($input['dry_run'] ?? true)) {
            return;
        }
        if (!(new TokenVerifier($this->storageDir))->verify($token, $action)) {
            throw new \RuntimeException('Updater token is invalid or expired.');
        }
    }

    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        return preg_match('/Bearer\s+(.+)/i', (string)$header, $m) ? trim($m[1]) : null;
    }

    private function packageHead(string $url): array
    {
        $headers = @get_headers($url, true);
        if (!is_array($headers)) {
            return ['status' => 0, 'content_length' => null, 'content_type' => null];
        }
        $status = 0;
        foreach ($headers as $key => $value) {
            if (is_int($key) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$value, $m)) {
                $status = (int)$m[1];
            }
        }
        $length = $headers['Content-Length'] ?? $headers['content-length'] ?? null;
        if (is_array($length)) {
            $length = end($length);
        }
        $type = $headers['Content-Type'] ?? $headers['content-type'] ?? null;
        if (is_array($type)) {
            $type = end($type);
        }
        return [
            'status' => $status,
            'content_length' => is_numeric($length) ? (int)$length : null,
            'content_type' => is_string($type) ? $type : null,
        ];
    }

    private function jobId(array $input): string
    {
        $raw = (string)($input['job_id'] ?? '');
        if ($raw !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $raw)) {
            return $raw;
        }
        return 'upd_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
    }
}
