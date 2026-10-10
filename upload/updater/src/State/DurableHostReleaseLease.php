<?php
declare(strict_types=1);

namespace Updater\State;

/**
 * Proposal: crash-reacquirable shared-hosting release lease.
 *
 * `host-release-v2.lock` is a permanent protocol-2 inode. The protocol-1 guard remains
 * on `host-release.lock`; a one-time intent transfers bootstrap authority while
 * both inodes are held. flock serializes metadata access and
 * is held for the entire bounded updater callback, making lease validation and
 * that request's mutations one fenced critical section. The lock inode is
 * never unlinked/replaced; the private JSON receipt may be atomically replaced
 * only while that permanent inode is held.
 */
final class DurableHostReleaseLease
{
    private const TTL_MIN = 30;
    private const TTL_MAX = 900;

    /** @var callable():int */
    private $clock;
    private string $directory;
    private string $path;
    private string $lockPath;

    /** @param callable():int|null $clock Test seam; production uses time(). */
    public function __construct(private readonly string $basePath, ?callable $clock = null,
                                private readonly ?string $updaterStorageDir = null)
    {
        $this->clock = $clock ?? static fn(): int => time();
        $base = realpath($basePath);
        if ($base === false) {
            throw new \RuntimeException('HOST_LEASE_ROOT_UNAVAILABLE');
        }
        $storage = $base . '/storage_api';
        $stat = @lstat($storage);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0040000) {
            throw new \RuntimeException('HOST_LEASE_STORAGE_UNAVAILABLE');
        }
        $directory = $storage . '/release-coordinator';
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))) {
            throw new \RuntimeException('HOST_LEASE_DIRECTORY_UNAVAILABLE');
        }
        clearstatcache(true, $directory);
        $dirStat = @lstat($directory);
        if (!is_array($dirStat) || ($dirStat['mode'] & 0170000) !== 0040000
            || ($dirStat['mode'] & 0077) !== 0 || is_link($directory)
            || $dirStat['uid'] !== $stat['uid']) {
            throw new \RuntimeException('HOST_LEASE_DIRECTORY_UNSAFE');
        }
        $this->directory = $directory;
        $this->path = $directory . '/host-release.lease.json';
        // Keep protocol 2 on a distinct permanent inode. A one-time bootstrap
        // intent bridges the old protocol-1 inode; v2 can be claimed while v1
        // remains held, so no unguarded release→claim interval exists.
        $this->lockPath = $directory . '/host-release-v2.lock';
    }

    /** @return array<string,mixed> */
    public function handle(array $request): array
    {
        $action = $request['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['claim', 'status', 'renew', 'release', 'reconcile', 'bind_manifest'], true)) {
            throw new \InvalidArgumentException('HOST_LEASE_ACTION_INVALID');
        }
        return $this->locked(function ($handle) use ($action, $request): array {
            $record = $this->readRecord($handle);
            $now = ($this->clock)();
            if ($action === 'status') {
                return ['protocol' => 2, 'lease' => $this->publicRecord($record, $now)];
            }
            if ($action === 'claim') {
                return $this->claim($handle, $record, $request, $now);
            }

            $record = $this->requireCurrent($record, $request, $now);
            if ($action === 'reconcile') {
                return $this->reconcileTakeover($handle, $record, $request, $now);
            }
            if ($action === 'bind_manifest') {
                return $this->bindManifest($handle, $record, $request, $now);
            }
            if ($action === 'renew') {
                $record['expires_at'] = $now + $this->ttl($request['ttl'] ?? 300);
                $record['released_at'] = null;
                $this->writeRecord($handle, $record);
                return $this->receipt($record, $now);
            }
            if (($record['takeover_pending'] ?? false) === true) {
                throw new \RuntimeException('HOST_LEASE_RECONCILIATION_REQUIRED');
            }
            $record['expires_at'] = $now;
            $record['released_at'] = $now;
            $this->writeRecord($handle, $record);
            return ['protocol' => 2, 'held' => false, 'run_id' => $record['run_id'],
                'generation' => $record['generation'], 'expires_at' => $now];
        });
    }

    /** Validate the exact owner and keep the permanent inode locked through work. */
    public function withLease(array $input, callable $work): mixed
    {
        return $this->locked(function ($handle) use ($input, $work): mixed {
            $record = $this->readRecord($handle);
            $record = $this->requireCurrent($record, $input, ($this->clock)());
            if (($record['takeover_pending'] ?? false) === true) {
                throw new \RuntimeException('HOST_LEASE_RECONCILIATION_REQUIRED');
            }
            $leaseAction = $input['_host_lease_action'] ?? null;
            $mode = $record['continuation_mode'] ?? 'new_run';
            if (($mode === 'same_job_resume' && !in_array($leaseAction, ['apply', 'resume', 'rollback', 'snapshot', 'installed_snapshot'], true))
                || ($mode === 'same_job_rollback' && !in_array($leaseAction, ['rollback', 'snapshot'], true))) {
                throw new \RuntimeException('HOST_LEASE_CONTINUATION_ACTION_REJECTED');
            }
            $jobId = $input['job_id'] ?? null;
            $targetSha = $this->targetSha($input['host_lease_target_sha'] ?? null);
            if ($jobId !== ($record['authorized_job_id'] ?? null)
                || !is_string($record['authorized_target_sha'] ?? null)
                || !hash_equals($record['authorized_target_sha'], $targetSha)) {
                throw new \RuntimeException('HOST_LEASE_JOB_BINDING_REJECTED');
            }
            $mutatingRunActions = ['download', 'apply', 'resume', 'rollback', 'snapshot', 'installed_snapshot', 'force-unlock'];
            if ($mode === 'new_run' && in_array($leaseAction, $mutatingRunActions, true)) {
                $manifestSha = $input['host_lease_manifest_sha256'] ?? null;
                if (!is_string($record['manifest_sha256'] ?? null) || !is_string($manifestSha)
                    || preg_match('/\A[a-f0-9]{64}\z/D', $manifestSha) !== 1
                    || !hash_equals($record['manifest_sha256'], $manifestSha)) {
                    throw new \RuntimeException('HOST_LEASE_MANIFEST_BINDING_REQUIRED');
                }
            }
            $safeInput = $input;
            unset($safeInput['host_lease_token'], $safeInput['host_lease_run_id'],
                $safeInput['host_lease_generation'], $safeInput['host_lease_target_sha'],
                $safeInput['host_lease_manifest_sha256']);
            return $work($safeInput, $record['generation']);
        });
    }

    /**
     * Fence a session-authenticated updater request against release coordinators.
     * The same permanent inode stays locked through the bounded updater action.
     * An expired lease is not equivalent to no lease: only an explicit, completed
     * release (or a never-created record) permits the manual path.
     */
    public function withNoLease(array $input, callable $work): mixed
    {
        return $this->locked(function ($handle) use ($input, $work): mixed {
            $record = $this->readRecord($handle);
            if ($record !== null) {
                $now = ($this->clock)();
                if ($record['expires_at'] > $now) {
                    throw new \RuntimeException('HOST_RELEASE_BUSY');
                }
                if (($record['takeover_pending'] ?? false) === true
                    || !is_int($record['released_at'] ?? null)
                    || $record['released_at'] < $record['expires_at']) {
                    throw new \RuntimeException('HOST_LEASE_RECONCILIATION_REQUIRED');
                }
            }
            return $work($input);
        });
    }

    /** @return mixed */
    private function locked(callable $callback): mixed
    {
        if (is_link($this->lockPath)) {
            throw new \RuntimeException('HOST_LEASE_LOCK_SYMLINK');
        }
        $oldUmask = umask(0077);
        try {
            $handle = @fopen($this->lockPath, 'c+b');
        } finally {
            umask($oldUmask);
        }
        if ($handle === false) {
            throw new \RuntimeException('HOST_LEASE_FILE_UNAVAILABLE');
        }
        try {
            $meta = fstat($handle);
            clearstatcache(true, $this->lockPath);
            $pathMeta = @lstat($this->lockPath);
            if (!is_array($meta) || !is_array($pathMeta)
                || ($meta['mode'] & 0170000) !== 0100000 || ($meta['mode'] & 0077) !== 0
                || $meta['nlink'] !== 1 || $meta['dev'] !== $pathMeta['dev']
                || $meta['ino'] !== $pathMeta['ino'] || ($pathMeta['mode'] & 0170000) !== 0100000
                || $meta['uid'] !== $pathMeta['uid'] || $meta['uid'] !== $this->directoryUid()) {
                throw new \RuntimeException('HOST_LEASE_FILE_UNSAFE');
            }
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('HOST_LEASE_BUSY');
            }
            return $callback($handle);
        } finally {
            if (is_resource($handle)) {
                @flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    private function readRecord($handle): ?array
    {
        if (!file_exists($this->path) && !is_link($this->path)) {
            return null;
        }
        clearstatcache(true, $this->path);
        $meta = @lstat($this->path);
        if (!is_array($meta) || is_link($this->path) || ($meta['mode'] & 0170000) !== 0100000
            || ($meta['mode'] & 0077) !== 0 || $meta['nlink'] !== 1 || $meta['size'] > 8192
            || $meta['uid'] !== $this->directoryUid()) {
            throw new \RuntimeException('HOST_LEASE_FILE_UNSAFE');
        }
        $raw = @file_get_contents($this->path);
        if (!is_string($raw) || strlen($raw) > 8192) {
            throw new \RuntimeException('HOST_LEASE_RECORD_INVALID');
        }
        if (trim($raw) === '') {
            return null;
        }
        $record = json_decode($raw, true);
        if (!is_array($record) || ($record['schema_version'] ?? null) !== 2
            || !is_int($record['generation'] ?? null) || $record['generation'] < 1
            || !is_int($record['expires_at'] ?? null) || $record['expires_at'] < 1
            || !is_string($record['run_id'] ?? null)
            || preg_match('/\A[a-f0-9]{32}\z/D', $record['run_id']) !== 1
            || !is_string($record['token_hash'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $record['token_hash']) !== 1) {
            throw new \RuntimeException('HOST_LEASE_RECORD_INVALID');
        }
        return $record;
    }

    private function writeRecord($handle, array $record): void
    {
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $temporary = $this->directory . '/.host-release-' . bin2hex(random_bytes(8)) . '.tmp';
        $oldUmask = umask(0077);
        try {
            $fd = @fopen($temporary, 'x+b');
        } finally {
            umask($oldUmask);
        }
        if ($fd === false) {
            throw new \RuntimeException('HOST_LEASE_RECORD_WRITE_FAILED');
        }
        try {
            if (fwrite($fd, $json) !== strlen($json) || fflush($fd) !== true
                || (function_exists('fsync') && !@fsync($fd))) {
                throw new \RuntimeException('HOST_LEASE_RECORD_SYNC_FAILED');
            }
            @chmod($temporary, 0600);
        } finally {
            fclose($fd);
        }
        if (!@rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new \RuntimeException('HOST_LEASE_RECORD_RENAME_FAILED');
        }
        $dir = @fopen($this->directory, 'r');
        if (is_resource($dir)) {
            if (function_exists('fsync')) {
                @fsync($dir);
            }
            fclose($dir);
        }
    }

    private function requireCurrent(?array $record, array $request, int $now): array
    {
        $runId = $this->runId($request['host_lease_run_id'] ?? $request['run_id'] ?? null);
        $token = $this->token($request['host_lease_token'] ?? $request['token'] ?? null);
        $generation = $request['host_lease_generation'] ?? $request['generation'] ?? null;
        if ($record === null || $record['expires_at'] <= $now
            || $record['run_id'] !== $runId || !is_int($generation)
            || $record['generation'] !== $generation
            || !hash_equals($record['token_hash'], hash('sha256', $token))) {
            throw new \RuntimeException('HOST_LEASE_FENCE_REJECTED');
        }
        return $record;
    }

    private function publicRecord(?array $record, int $now): ?array
    {
        if ($record === null) {
            return null;
        }
        return ['run_id' => $record['run_id'], 'generation' => $record['generation'],
            'expires_at' => $record['expires_at'], 'active' => $record['expires_at'] > $now];
    }

    private function receipt(array $record, int $now, ?array $reconciliation = null): array
    {
        $receipt = ['protocol' => 2, 'accepted' => true, 'held' => $record['expires_at'] > $now,
            'run_id' => $record['run_id'], 'generation' => $record['generation'],
            'expires_at' => $record['expires_at'],
            'takeover_pending' => (bool)($record['takeover_pending'] ?? false),
            'continuation_mode' => $record['continuation_mode'] ?? null,
            'authorized_job_id' => $record['authorized_job_id'] ?? null,
            'authorized_target_sha' => $record['authorized_target_sha'] ?? null,
            'bootstrap_eligible' => ($record['purpose'] ?? 'release') === 'bootstrap'
                && ($record['bootstrap_consumed'] ?? false) === true,
            'bootstrap_claim_id' => $record['bootstrap_claim_id'] ?? null,
            'manifest_sha256' => $record['manifest_sha256'] ?? null,
            'predecessor_readback_sha256' => $record['predecessor_readback_sha256'] ?? null];
        if ($reconciliation !== null) {
            $receipt['predecessor_readback'] = $reconciliation;
            $receipt['readback_complete'] = ($reconciliation['readback_complete'] ?? false) === true;
            $receipt['readback_sha256'] = hash('sha256', $this->canonical($reconciliation));
            $receipt['decision'] = $record['continuation_mode'] ?? null;
        } else {
            $receipt['predecessor_readback'] = $record['predecessor_readback'] ?? null;
        }
        return $receipt;
    }

    private function reconcileTakeover($handle, array $record, array $request, int $now): array
    {
        $decision = $request['decision'] ?? null;
        $expected = $request['expected_readback_sha256'] ?? null;
        $reconcileIntent = ['run_id' => $record['run_id'], 'generation' => $record['generation'],
            'claim_id' => $record['claim_id'], 'expected_readback_sha256' => $expected, 'decision' => $decision];
        $intentDigest = hash('sha256', $this->canonical($reconcileIntent));
        $pending = ($record['takeover_pending'] ?? false) === true;
        $rollbackContinuation = !$pending && ($record['continuation_mode'] ?? null) === 'same_job_rollback'
            && $decision === 'new_run';
        if (!$pending && !$rollbackContinuation) {
            if (is_string($record['last_reconcile_intent_sha256'] ?? null)
                && hash_equals($record['last_reconcile_intent_sha256'], $intentDigest)
                && is_array($record['last_reconcile_readback'] ?? null)) {
                return $this->receipt($record, $now, $record['last_reconcile_readback']);
            }
            throw new \RuntimeException('HOST_LEASE_TAKEOVER_NOT_PENDING');
        }
        if (!is_string($expected) || preg_match('/\A[a-f0-9]{64}\z/D', $expected) !== 1
            || ($pending && (!is_string($record['predecessor_readback_sha256'] ?? null)
                || !hash_equals($record['predecessor_readback_sha256'], $expected)))) {
            throw new \RuntimeException('HOST_LEASE_READBACK_RECEIPT_MISMATCH');
        }
        $fresh = $this->readExpiredJobFromPrevious($record);
        $freshDigest = hash('sha256', $this->canonical($fresh));
        if (($fresh['readback_complete'] ?? false) !== true || !hash_equals($expected, $freshDigest)) {
            throw new \RuntimeException('HOST_LEASE_READBACK_CHANGED');
        }
        $oldRun = $record['predecessor_readback']['previous_run_id'] ?? null;
        $oldJob = $record['predecessor_readback']['job_id'] ?? null;
        $oldSha = $record['predecessor_readback']['target_sha'] ?? null;
        if ($decision === 'same_job_resume' || $decision === 'same_job_rollback') {
            $validState = in_array($fresh['job_state'] ?? null,
                ['staging_ready', 'applying', 'backup_created', 'failed', 'rollback_failed'], true);
            $capability = $decision === 'same_job_resume' ? ($fresh['can_resume'] ?? false) : ($fresh['can_rollback'] ?? false);
            if (($fresh['job_exists'] ?? false) !== true || !$validState || $capability !== true
                || !is_string($oldSha) || !is_string($oldRun) || $oldJob !== 'upd_' . $oldRun
                || !hash_equals($oldSha, (string)$fresh['target_sha'])) {
                throw new \RuntimeException('HOST_LEASE_RECONCILIATION_DECISION_INVALID');
            }
            $record['continuation_mode'] = $decision;
            $record['authorized_job_id'] = $oldJob;
            $record['authorized_target_sha'] = $oldSha;
            $record['takeover_pending'] = false;
        } elseif ($decision === 'new_run') {
            $safeCompleted = ($fresh['job_exists'] ?? false) === true
                && in_array(($fresh['job_state'] ?? null), ['applied', 'rolled_back'], true)
                && ($fresh['maintenance_held'] ?? true) === false
                && ($fresh['maintenance_flag'] ?? true) === false
                && ($fresh['update_lock_active'] ?? true) === false;
            $absent = ($fresh['job_exists'] ?? false) === false;
            if (!$absent && !$safeCompleted) {
                throw new \RuntimeException('HOST_LEASE_NEW_RUN_REQUIRES_CLEAN_PREDECESSOR');
            }
            $record['continuation_mode'] = 'new_run';
            $record['authorized_job_id'] = 'upd_' . $record['run_id'];
            $record['authorized_target_sha'] = $record['target_sha'];
            $record['takeover_pending'] = false;
        } else {
            throw new \InvalidArgumentException('HOST_LEASE_RECONCILIATION_DECISION_INVALID');
        }
        $record['last_reconcile_intent_sha256'] = $intentDigest;
        $record['last_reconcile_readback'] = $fresh;
        $this->writeRecord($handle, $record);
        return $this->receipt($record, $now, $fresh);
    }

    private function claim($handle, ?array $record, array $request, int $now): array
    {
        $runId = $this->runId($request['run_id'] ?? null);
        $token = $this->token($request['token'] ?? null);
        $claimId = $this->claimId($request['claim_id'] ?? null);
        $targetSha = $this->targetSha($request['target_sha'] ?? null);
        $purpose = $request['purpose'] ?? 'release';
        if (!in_array($purpose, ['release', 'bootstrap'], true)) {
            throw new \InvalidArgumentException('HOST_LEASE_PURPOSE_INVALID');
        }
        // Ordinary v2 release claims briefly acquire the v1 inode as well. This
        // prevents a release claimant from racing the first bootstrap intent.
        $v1Guard = null;
        if ($purpose === 'release') {
            $v1Guard = new HostReleaseLock($this->basePath);
            if (!$v1Guard->acquire()) { throw new \RuntimeException('HOST_V1_BOOTSTRAP_GUARD_ACTIVE'); }
        }
        try {
            if ($purpose === 'bootstrap') {
                $this->assertBootstrapEligible($record, $runId, $claimId, $targetSha,
                    $request['manifest_sha256'] ?? null, $request['bootstrap_intent_token'] ?? null);
            } elseif (is_file($this->directory . '/bootstrap-intent.json')
                || is_link($this->directory . '/bootstrap-intent.json')) {
                throw new \RuntimeException('HOST_BOOTSTRAP_INTENT_PENDING');
            }
            $ttl = $this->ttl($request['ttl'] ?? 300);
            if ($record !== null && $record['expires_at'] > $now) {
                if ($record['run_id'] !== $runId
                    || !hash_equals($record['token_hash'], hash('sha256', $token))
                    || $record['claim_id'] !== $claimId
                    || !hash_equals((string)$record['target_sha'], $targetSha)
                    || ($record['purpose'] ?? 'release') !== $purpose) {
                    throw new \RuntimeException('HOST_LEASE_BUSY');
                }
                if ($purpose === 'bootstrap') { $this->consumeBootstrapIntent(); }
                $record['expires_at'] = $now + $ttl;
                $this->writeRecord($handle, $record);
                return $this->receipt($record, $now);
            }
            $reconciliation = $record === null ? null : $this->readExpiredJob($record);
            $generation = $record === null ? 1 : $record['generation'] + 1;
            $next = ['schema_version' => 2, 'generation' => $generation,
                'run_id' => $runId, 'token_hash' => hash('sha256', $token),
                'claim_id' => $claimId, 'target_sha' => $targetSha, 'purpose' => $purpose,
                'bootstrap_consumed' => ($record['bootstrap_consumed'] ?? false) === true || $purpose === 'bootstrap',
                'bootstrap_run_id' => $purpose === 'bootstrap' ? $runId : ($record['bootstrap_run_id'] ?? null),
                'bootstrap_claim_id' => $purpose === 'bootstrap' ? $claimId : ($record['bootstrap_claim_id'] ?? null),
                'bootstrap_target_sha' => $purpose === 'bootstrap' ? $targetSha : ($record['bootstrap_target_sha'] ?? null),
                'bootstrap_intent_token_hash' => $purpose === 'bootstrap' ? hash('sha256', (string)$request['bootstrap_intent_token']) : ($record['bootstrap_intent_token_hash'] ?? null),
                'expires_at' => $now + $ttl, 'takeover_pending' => $record !== null,
                'released_at' => null,
                'predecessor_readback' => $reconciliation,
                'predecessor_readback_sha256' => $reconciliation === null ? null : hash('sha256', $this->canonical($reconciliation)),
                'continuation_mode' => $record === null ? 'new_run' : 'pending',
                'authorized_job_id' => $record === null ? 'upd_' . $runId : null,
                'authorized_target_sha' => $record === null ? $targetSha : null];
            $this->writeRecord($handle, $next);
            if ($purpose === 'bootstrap') { $this->consumeBootstrapIntent(); }
            return $this->receipt($next, $now, $reconciliation);
        } finally {
            if ($v1Guard !== null) { $v1Guard->release(); }
        }
    }

    private function bindManifest($handle, array $record, array $request, int $now): array
    {
        if (($record['takeover_pending'] ?? false) === true || $this->updaterStorageDir === null) {
            throw new \RuntimeException('HOST_LEASE_RECONCILIATION_REQUIRED');
        }
        $jobId = $request['job_id'] ?? null;
        $targetSha = $this->targetSha($request['target_sha'] ?? null);
        $expectedDigest = $request['manifest_sha256'] ?? null;
        if ($jobId !== ($record['authorized_job_id'] ?? null)
            || !hash_equals((string)($record['authorized_target_sha'] ?? ''), $targetSha)
            || !is_string($expectedDigest) || preg_match('/\A[a-f0-9]{64}\z/D', $expectedDigest) !== 1) {
            throw new \RuntimeException('HOST_LEASE_JOB_BINDING_REJECTED');
        }
        $jobDir = rtrim($this->updaterStorageDir, '/') . '/jobs/' . $jobId;
        $this->assertPrivateDirectory(rtrim($this->updaterStorageDir, '/') . '/jobs');
        $this->assertPrivateDirectory($jobDir);
        $state = $this->readPrivateJson($jobDir . '/state.json', 262144);
        $plan = $this->readPrivateJson($jobDir . '/plan.json', 1048576);
        $manifest = $this->readPrivateJson($jobDir . '/manifest.json', 4194304);
        $preflight = $this->readPrivateJson($jobDir . '/preflight.json', 1048576);
        $normalPreflight = (($state['state'] ?? null) === 'preflight_passed');
        $bootstrapHealthHandoff = false;
        $backupReceipt = null;
        if (($record['purpose'] ?? 'release') === 'bootstrap'
            && ($state['state'] ?? null) === 'applying'
            && (($state['progress']['phase'] ?? null) === 'health')) {
            $lockOwner = (new LockManager($this->updaterStorageDir))->ownerJobId();
            $maintenancePath = dirname($this->updaterStorageDir, 2) . '/storage_api/maintenance.flag';
            $maintenanceStat = @lstat($maintenancePath);
            $maintenance = is_array($maintenanceStat) && !is_link($maintenancePath)
                && ($maintenanceStat['mode'] & 0170000) === 0100000
                && $maintenanceStat['nlink'] === 1 && $maintenanceStat['size'] <= 4096
                ? json_decode((string)@file_get_contents($maintenancePath), true) : null;
            $backupReceipt = (new \Updater\UpdaterKernel(dirname($this->updaterStorageDir, 2)))
                ->verifiedBootstrapBackupCheckpoint($jobId);
            $bootstrapHealthHandoff = $lockOwner === $jobId
                && is_array($maintenance) && ($maintenance['job_id'] ?? null) === $jobId
                && ($maintenance['reason'] ?? null) === 'core_update_apply'
                && ($backupReceipt['maintenance_held'] ?? false) === true
                && ($backupReceipt['job_id'] ?? null) === $jobId;
            if (!$bootstrapHealthHandoff) {
                throw new \RuntimeException('HOST_LEASE_BOOTSTRAP_HANDOFF_NOT_CONFIRMED');
            }
        }
        if (($state['job_id'] ?? null) !== $jobId || (!$normalPreflight && !$bootstrapHealthHandoff)
            || ($plan['target_sha'] ?? null) !== $targetSha || ($manifest['to_sha'] ?? null) !== $targetSha
            || ($preflight['ok'] ?? false) !== true || ($preflight['target_sha'] ?? null) !== $targetSha) {
            throw new \RuntimeException('HOST_LEASE_PREFLIGHT_NOT_CONFIRMED');
        }
        $digest = hash('sha256', $this->canonical($manifest));
        if (!hash_equals($digest, $expectedDigest)) {
            throw new \RuntimeException('HOST_LEASE_MANIFEST_DIGEST_MISMATCH');
        }
        if (is_string($record['manifest_sha256'] ?? null) && !hash_equals($record['manifest_sha256'], $digest)) {
            throw new \RuntimeException('HOST_LEASE_MANIFEST_ALREADY_BOUND');
        }
        $record['manifest_sha256'] = $digest;
        if ($bootstrapHealthHandoff) {
            $record['bootstrap_handoff_verified'] = true;
            $record['bootstrap_handoff_job_id'] = $jobId;
            $record['bootstrap_handoff_target_sha'] = $targetSha;
        }
        $this->writeRecord($handle, $record);
        $receipt = $this->receipt($record, $now);
        $receipt['manifest_sha256'] = $digest;
        $receipt['preflight_confirmed'] = true;
        $receipt['bootstrap_handoff_verified'] = $bootstrapHealthHandoff;
        if ($bootstrapHealthHandoff) {
            $receipt['bootstrap_handoff_phase'] = 'health';
            $receipt['backup_checkpoint'] = $backupReceipt;
        }
        return $receipt;
    }

    private function readExpiredJobFromPrevious(array $record): array
    {
        $previous = $record['predecessor_readback'] ?? null;
        if (!is_array($previous) || !is_string($previous['previous_run_id'] ?? null)
            || !is_int($previous['previous_generation'] ?? null)) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_UNAVAILABLE');
        }
        $synthetic = ['run_id' => $previous['previous_run_id'], 'generation' => $previous['previous_generation']];
        return $this->readExpiredJob($synthetic);
    }

    private function assertBootstrapEligible(?array $record, string $runId, string $claimId, string $targetSha,
                                             mixed $manifestSha, mixed $intentToken): void
    {
        $acceptance = $this->directory . '/coordinator-accepted.json';
        if (file_exists($acceptance) || is_link($acceptance)) {
            throw new \RuntimeException('HOST_BOOTSTRAP_ALREADY_ACCEPTED');
        }
        if (($record['bootstrap_consumed'] ?? false) === true
            && (($record['bootstrap_run_id'] ?? null) !== $runId
                || ($record['bootstrap_claim_id'] ?? null) !== $claimId
                || ($record['bootstrap_target_sha'] ?? null) !== $targetSha)) {
            throw new \RuntimeException('HOST_BOOTSTRAP_LATCH_CONSUMED');
        }
        if (($record['bootstrap_consumed'] ?? false) === true) {
            if (!is_string($intentToken) || !hash_equals((string)($record['bootstrap_intent_token_hash'] ?? ''), hash('sha256', $intentToken))
                || !is_string($manifestSha) || !is_string($record['manifest_sha256'] ?? null)
                    && isset($record['manifest_sha256'])
                || (is_string($record['manifest_sha256'] ?? null) && !hash_equals($record['manifest_sha256'], $manifestSha))) {
                throw new \RuntimeException('HOST_BOOTSTRAP_REPLAY_MISMATCH');
            }
            return;
        }
        if (!is_string($manifestSha) || preg_match('/\A[a-f0-9]{64}\z/D', $manifestSha) !== 1
            || !is_string($intentToken) || preg_match('/\A[a-f0-9]{64}\z/D', $intentToken) !== 1) {
            throw new \RuntimeException('HOST_BOOTSTRAP_INTENT_REQUIRED');
        }
        $intent = $this->readBootstrapIntent();
        if (($intent['purpose'] ?? null) !== 'bootstrap-demo'
            || ($intent['run_id'] ?? null) !== $runId || ($intent['claim_id'] ?? null) !== $claimId
            || ($intent['target_sha'] ?? null) !== $targetSha
            || !hash_equals((string)($intent['manifest_sha256'] ?? ''), $manifestSha)
            || !hash_equals((string)($intent['token_hash'] ?? ''), hash('sha256', $intentToken))
            || ($intent['expires_at'] ?? 0) <= ($this->clock)()) {
            throw new \RuntimeException('HOST_BOOTSTRAP_INTENT_BINDING_MISMATCH');
        }
    }

    private function readBootstrapIntent(): array
    {
        $path = $this->directory . '/bootstrap-intent.json';
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $dir = @lstat($this->directory);
        if ($stat === false || $dir === false || is_link($path)
            || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0077) !== 0
            || $stat['nlink'] !== 1 || $stat['uid'] !== $dir['uid'] || $stat['size'] < 2 || $stat['size'] > 4096) {
            throw new \RuntimeException('HOST_BOOTSTRAP_INTENT_UNSAFE');
        }
        $value = json_decode((string)@file_get_contents($path), true);
        if (!is_array($value) || array_is_list($value) || ($value['schema_version'] ?? null) !== 1
            || !is_int($value['expires_at'] ?? null)
            || !is_string($value['token_hash'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $value['token_hash']) !== 1) {
            throw new \RuntimeException('HOST_BOOTSTRAP_INTENT_INVALID');
        }
        return $value;
    }

    private function consumeBootstrapIntent(): void
    {
        $path = $this->directory . '/bootstrap-intent.json';
        if (!file_exists($path) && !is_link($path)) { return; }
        if (is_link($path) || !@unlink($path)) {
            throw new \RuntimeException('HOST_BOOTSTRAP_INTENT_CONSUME_FAILED');
        }
    }

    private function claimId(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('HOST_LEASE_CLAIM_ID_INVALID');
        }
        return $value;
    }

    private function targetSha(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{40}\z/iD', $value) !== 1) {
            throw new \InvalidArgumentException('HOST_LEASE_TARGET_SHA_INVALID');
        }
        return strtolower($value);
    }

    private function canonical(array $value): string
    {
        unset($value['read_at']);
        $normalize = static function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) { return $item; }
            if (!array_is_list($item)) { ksort($item); }
            foreach ($item as $key => $child) { $item[$key] = $normalize($child); }
            return $item;
        };
        return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Read old updater state while holding the host lease inode before successor claim. */
    private function readExpiredJob(array $record): array
    {
        if ($this->updaterStorageDir === null) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_UNAVAILABLE');
        }
        $storage = rtrim($this->updaterStorageDir, '/');
        $jobsDir = $storage . '/jobs';
        if (is_link($storage) || !is_dir($storage)) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_INVALID');
        }
        if (is_link($jobsDir)) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_INVALID');
        }
        $jobId = 'upd_' . $record['run_id'];
        $jobDir = $jobsDir . '/' . $jobId;
        if (!file_exists($jobsDir) && !is_link($jobsDir)) {
            return ['readback_complete' => true, 'previous_run_id' => $record['run_id'],
                'previous_generation' => $record['generation'], 'job_id' => $jobId, 'job_exists' => false,
                'job_state' => null, 'can_resume' => false, 'can_rollback' => false,
                'maintenance_held' => false, 'maintenance_flag' => false, 'update_lock_active' => false,
                'target_sha' => null];
        }
        $this->assertPrivateDirectory($jobsDir);
        if (!file_exists($jobDir) && !is_link($jobDir)) {
            return ['readback_complete' => true, 'previous_run_id' => $record['run_id'],
                'previous_generation' => $record['generation'], 'job_id' => $jobId, 'job_exists' => false,
                'job_state' => null, 'can_resume' => false, 'can_rollback' => false,
                'maintenance_held' => false, 'maintenance_flag' => false, 'update_lock_active' => false,
                'target_sha' => null];
        }
        $this->assertPrivateDirectory($jobDir);
        $state = $this->readPrivateJson($jobDir . '/state.json', 262144);
        $manifest = $this->readPrivateJson($jobDir . '/manifest.json', 4194304);
        $plan = $this->readPrivateJson($jobDir . '/plan.json', 1048576);
        if ($state === null || $manifest === null || $plan === null
            || ($state['job_id'] ?? null) !== $jobId
            || !is_string($state['state'] ?? null)
            || !is_bool($state['can_resume'] ?? null)
            || !is_bool($state['can_rollback'] ?? null)
            || !is_bool($state['maintenance_held'] ?? null)
            || !is_string($manifest['to_sha'] ?? null)
            || !is_string($plan['target_sha'] ?? null)) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_INVALID');
        }
        $manifestSha = strtolower($this->targetSha($manifest['to_sha']));
        $planSha = strtolower($this->targetSha($plan['target_sha']));
        if (!hash_equals($manifestSha, $planSha)) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_PLAN_MISMATCH');
        }
        $basePath = dirname(dirname($storage));
        $maintenance = $basePath . '/storage_api/maintenance.flag';
        $lock = $storage . '/locks/update.lock';
        $maintenanceFlag = $this->maintenanceOwnedBy($maintenance, $jobId);
        $lockActive = $this->updaterLockActive($lock, $jobId);
        if ($state['maintenance_held'] !== $maintenanceFlag) {
            throw new \RuntimeException('HOST_LEASE_MAINTENANCE_READBACK_MISMATCH');
        }
        [$dirtyCount, $dirtySha] = $this->readDirtyPaths($jobDir, $state);
        return ['readback_complete' => true, 'previous_run_id' => $record['run_id'],
            'previous_generation' => $record['generation'], 'job_id' => $jobId, 'job_exists' => true,
            'job_state' => $state['state'], 'can_resume' => $state['can_resume'],
            'can_rollback' => $state['can_rollback'], 'maintenance_held' => $state['maintenance_held'],
            'maintenance_flag' => $maintenanceFlag, 'update_lock_active' => $lockActive,
            'dirty_paths_count' => $dirtyCount, 'dirty_paths_sha256' => $dirtySha,
            'target_sha' => $manifestSha];
    }

    private function maintenanceOwnedBy(string $path, string $jobId): bool
    {
        if (!file_exists($path) && !is_link($path)) { return false; }
        clearstatcache(true, $path); $stat = @lstat($path);
        if (!is_array($stat) || is_link($path) || ($stat['mode'] & 0170000) !== 0100000
            || $stat['nlink'] !== 1 || $stat['size'] > 4096
            || $stat['uid'] !== (int)@fileowner(rtrim((string)$this->updaterStorageDir, '/'))) {
            throw new \RuntimeException('HOST_LEASE_MAINTENANCE_READBACK_INVALID');
        }
        $body = json_decode((string)@file_get_contents($path), true);
        if (!is_array($body) || ($body['job_id'] ?? null) !== $jobId
            || ($body['reason'] ?? null) !== 'core_update_apply') {
            throw new \RuntimeException('HOST_LEASE_MAINTENANCE_OWNER_MISMATCH');
        }
        return true;
    }

    private function updaterLockActive(string $path, string $jobId): bool
    {
        if (!file_exists($path) && !is_link($path)) { return false; }
        clearstatcache(true, $path); $stat = @lstat($path);
        if (!is_array($stat) || is_link($path) || ($stat['mode'] & 0170000) !== 0100000
            || $stat['nlink'] !== 1 || $stat['size'] < 2 || $stat['size'] > 8192
            || $stat['uid'] !== (int)@fileowner(rtrim((string)$this->updaterStorageDir, '/'))) {
            throw new \RuntimeException('HOST_LEASE_UPDATE_LOCK_READBACK_INVALID');
        }
        $lock = json_decode((string)@file_get_contents($path), true);
        if (!is_array($lock) || ($lock['job_id'] ?? null) !== $jobId) {
            throw new \RuntimeException('HOST_LEASE_UPDATE_LOCK_OWNER_MISMATCH');
        }
        $heartbeat = strtotime((string)($lock['heartbeat_at'] ?? $lock['created_at'] ?? ''));
        if ($heartbeat === false) { throw new \RuntimeException('HOST_LEASE_UPDATE_LOCK_READBACK_INVALID'); }
        $age = max(0, time() - $heartbeat);
        $pid = $lock['pid'] ?? null;
        $dead = is_int($pid) && $pid > 0 && function_exists('posix_kill') && !@posix_kill($pid, 0);
        return !($dead ? $age > 300 : $age > 3600);
    }

    private function readDirtyPaths(string $jobDir, array $state): array
    {
        $json = $jobDir . '/applied.json';
        $jsonl = $jobDir . '/applied.jsonl';
        $items = [];
        if (file_exists($json) || is_link($json)) {
            $data = $this->readPrivateJson($json, 8 * 1024 * 1024);
            if (!is_array($data) || !is_array($data['files'] ?? null)) { throw new \RuntimeException('HOST_LEASE_DIRTY_PATH_READBACK_INVALID'); }
            $items = $data['files'];
        } elseif (file_exists($jsonl) || is_link($jsonl)) {
            clearstatcache(true, $jsonl); $meta = @lstat($jsonl);
            if (!is_array($meta) || is_link($jsonl) || ($meta['mode'] & 0170000) !== 0100000
                || ($meta['mode'] & 0077) !== 0 || $meta['nlink'] !== 1 || $meta['size'] > 8 * 1024 * 1024
                || $meta['uid'] !== (int)@fileowner(rtrim((string)$this->updaterStorageDir, '/'))) {
                throw new \RuntimeException('HOST_LEASE_DIRTY_PATH_READBACK_INVALID');
            }
            foreach (preg_split('/\\R/', trim((string)@file_get_contents($jsonl))) ?: [] as $line) {
                if ($line === '') { continue; }
                $item = json_decode($line, true);
                if (!is_array($item)) { throw new \RuntimeException('HOST_LEASE_DIRTY_PATH_READBACK_INVALID'); }
                $items[] = $item;
            }
        } elseif (($state['state'] ?? null) === 'applying' && (int)($state['progress']['cursor'] ?? 0) > 0) {
            throw new \RuntimeException('HOST_LEASE_DIRTY_PATH_READBACK_MISSING');
        }
        $paths = [];
        foreach ($items as $item) {
            $path = is_array($item) ? ($item['path'] ?? null) : null;
            $normalized = is_string($path) ? str_replace('\\\\', '/', $path) : '';
            if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, "\\0")
                || preg_match('/(?:^|\\/)\\.\\.(?:\\/|$)/', $normalized) === 1) {
                throw new \RuntimeException('HOST_LEASE_DIRTY_PATH_READBACK_INVALID');
            }
            $paths[] = $normalized;
        }
        if (count(array_unique($paths)) !== count($paths)) { throw new \RuntimeException('HOST_LEASE_DIRTY_PATH_READBACK_INVALID'); }
        sort($paths, SORT_STRING);
        return [count($paths), hash('sha256', json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    }

    private function assertPrivateDirectory(string $path): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $storage = @lstat(rtrim((string)$this->updaterStorageDir, '/'));
        if (!is_array($stat) || !is_array($storage) || is_link($path)
            || ($stat['mode'] & 0170000) !== 0040000 || ($stat['mode'] & 0077) !== 0
            || $stat['uid'] !== $storage['uid']) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_INVALID');
        }
    }

    private function readPrivateJson(string $path, int $maxBytes): ?array
    {
        if (!file_exists($path) && !is_link($path)) {
            return null;
        }
        clearstatcache(true, $path);
        $stat = @lstat($path);
        $storage = @lstat(rtrim((string)$this->updaterStorageDir, '/'));
        if (!is_array($stat) || !is_array($storage) || is_link($path)
            || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0077) !== 0
            || $stat['uid'] !== $storage['uid'] || $stat['nlink'] !== 1
            || $stat['size'] < 2 || $stat['size'] > $maxBytes) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_INVALID');
        }
        $raw = @file_get_contents($path);
        $value = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($value)) {
            throw new \RuntimeException('HOST_LEASE_SUCCESSOR_READBACK_INVALID');
        }
        return $value;
    }

    private function runId(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('HOST_LEASE_RUN_ID_INVALID');
        }
        return $value;
    }

    private function token(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('HOST_LEASE_TOKEN_INVALID');
        }
        return $value;
    }

    private function ttl(mixed $value): int
    {
        if (!is_int($value) || $value < self::TTL_MIN || $value > self::TTL_MAX) {
            throw new \InvalidArgumentException('HOST_LEASE_TTL_INVALID');
        }
        return $value;
    }

    private function directoryUid(): int
    {
        $stat = @lstat($this->directory);
        if (!is_array($stat)) {
            throw new \RuntimeException('HOST_LEASE_DIRECTORY_UNAVAILABLE');
        }
        return (int)$stat['uid'];
    }
}
