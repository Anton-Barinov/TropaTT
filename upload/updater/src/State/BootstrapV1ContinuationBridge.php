<?php
declare(strict_types=1);

namespace Updater\State;

/**
 * One-job bridge while the first bootstrap installs protocol-2 fencing.
 * The caller must already hold the installation DeploymentMutex. The bridge
 * accepts only the run-bound signed updater job while the fixed protocol-1
 * supervisor flock, updater job lock and maintenance flag all identify that
 * same job. It grants no new preflight/download/rollback/force-unlock path.
 */
final class BootstrapV1ContinuationBridge
{
    public function __construct(private readonly string $basePath, private readonly string $storageDir)
    {
    }

    public function withContinuation(array $input, string $action, callable $work): mixed
    {
        if (!in_array($action, ['apply', 'resume'], true)
            || ($input['host_lease_protocol'] ?? null) !== 1
            || ($input['bootstrap_purpose'] ?? null) !== 'bootstrap-demo') {
            throw new \RuntimeException('BOOTSTRAP_V1_BRIDGE_ACTION_REJECTED');
        }
        $runId = $input['host_lease_run_id'] ?? null;
        $jobId = $input['job_id'] ?? null;
        $targetSha = $input['host_lease_target_sha'] ?? null;
        $manifestSha = $input['host_lease_manifest_sha256'] ?? null;
        if (!is_string($runId) || preg_match('/\A[a-f0-9]{32}\z/D', $runId) !== 1
            || $jobId !== 'upd_' . $runId
            || !is_string($targetSha) || preg_match('/\A[a-f0-9]{40}\z/iD', $targetSha) !== 1
            || !is_string($manifestSha) || preg_match('/\A[a-f0-9]{64}\z/D', $manifestSha) !== 1) {
            throw new \RuntimeException('BOOTSTRAP_V1_BRIDGE_BINDING_REJECTED');
        }
        if (!HostReleaseLock::isHeld($this->basePath)) {
            throw new \RuntimeException('BOOTSTRAP_V1_GUARD_NOT_HELD');
        }
        $v2RecordPath = rtrim($this->basePath, '/') . '/storage_api/release-coordinator/host-release.lease.json';
        if (file_exists($v2RecordPath) || is_link($v2RecordPath)) {
            // Once protocol 2 has been claimed, a stale/missing token is a
            // reconciliation problem. Falling back to v1 would downgrade the
            // fence and reopen a competing-writer path.
            throw new \RuntimeException('BOOTSTRAP_V1_BRIDGE_CLOSED_AFTER_V2_CLAIM');
        }
        $jobDir = rtrim($this->storageDir, '/') . '/jobs/' . $jobId;
        $this->assertPrivateDirectory(rtrim($this->storageDir, '/') . '/jobs');
        $this->assertPrivateDirectory($jobDir);
        $state = $this->readPrivateJson($jobDir . '/state.json', 262144);
        $plan = $this->readPrivateJson($jobDir . '/plan.json', 1048576);
        $manifest = $this->readPrivateJson($jobDir . '/manifest.json', 4194304);
        $preflight = $this->readPrivateJson($jobDir . '/preflight.json', 1048576);
        $digest = hash('sha256', $this->canonical($manifest));
        $progress = $state['progress'] ?? null;
        if (($state['job_id'] ?? null) !== $jobId
            || !in_array(($state['state'] ?? null), ['applying', 'backup_created', 'failed'], true)
            || !is_array($progress)
            || !in_array(($progress['phase'] ?? null), ['apply_files', 'health'], true)
            || ($plan['target_sha'] ?? null) !== strtolower($targetSha)
            || ($manifest['to_sha'] ?? null) !== strtolower($targetSha)
            || ($preflight['ok'] ?? false) !== true
            || ($preflight['target_sha'] ?? null) !== strtolower($targetSha)
            || !hash_equals($digest, $manifestSha)) {
            throw new \RuntimeException('BOOTSTRAP_V1_JOB_READBACK_REJECTED');
        }
        if ($action === 'apply' && ($progress['phase'] ?? null) !== 'apply_files') {
            throw new \RuntimeException('BOOTSTRAP_V1_HANDOFF_REQUIRED_BEFORE_HEALTH');
        }
        $lock = new LockManager($this->storageDir);
        if ($lock->ownerJobId() !== $jobId) {
            throw new \RuntimeException('BOOTSTRAP_V1_UPDATER_LOCK_OWNER_MISMATCH');
        }
        $maintenancePath = rtrim($this->basePath, '/') . '/storage_api/maintenance.flag';
        $maintenanceStat = @lstat($maintenancePath);
        if ($maintenanceStat === false || is_link($maintenancePath)
            || ($maintenanceStat['mode'] & 0170000) !== 0100000 || $maintenanceStat['nlink'] !== 1
            || $maintenanceStat['uid'] !== fileowner($this->storageDir) || $maintenanceStat['size'] > 4096) {
            throw new \RuntimeException('BOOTSTRAP_V1_MAINTENANCE_FILE_UNSAFE');
        }
        $maintenance = json_decode((string)@file_get_contents($maintenancePath), true);
        if (!is_array($maintenance)) { throw new \RuntimeException('BOOTSTRAP_V1_MAINTENANCE_JSON_INVALID'); }
        if (($maintenance['job_id'] ?? null) !== $jobId
            || ($maintenance['reason'] ?? null) !== 'core_update_apply') {
            throw new \RuntimeException('BOOTSTRAP_V1_MAINTENANCE_OWNER_MISMATCH');
        }
        // This only validates and dispatches the already-created job. Job id,
        // plan, manifest, maintenance and updater lock remain bound to one run.
        unset($input['host_lease_protocol'], $input['bootstrap_purpose']);
        return $work($input, 1);
    }

    private function assertPrivateDirectory(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || ($stat['mode'] & 0170000) !== 0040000
            || ($stat['mode'] & 0077) !== 0 || $stat['uid'] !== fileowner($this->storageDir)) {
            throw new \RuntimeException('BOOTSTRAP_V1_READBACK_DIRECTORY_UNSAFE');
        }
    }

    private function readPrivateJson(string $path, int $maxBytes): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 0077) !== 0 || $stat['nlink'] !== 1
            || $stat['uid'] !== fileowner($this->storageDir) || $stat['size'] < 2 || $stat['size'] > $maxBytes) {
            throw new \RuntimeException('BOOTSTRAP_V1_READBACK_FILE_UNSAFE');
        }
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || array_is_list($data)) {
            throw new \RuntimeException('BOOTSTRAP_V1_READBACK_JSON_INVALID');
        }
        return $data;
    }

    private function canonical(array $value): string
    {
        $normalize = static function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) { return $item; }
            if (!array_is_list($item)) { ksort($item); }
            foreach ($item as $key => $child) { $item[$key] = $normalize($child); }
            return $item;
        };
        return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
