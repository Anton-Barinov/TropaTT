<?php
declare(strict_types=1);

namespace Updater\State;

/**
 * Bounds the disk the updater keeps between runs.
 *
 * A shared-hosting account has a small disk quota, and the updater used to keep
 * everything it ever produced: every job directory, every staged extraction
 * (thousands of files), every package archive and every file/DB backup.
 * Nothing removed old backups, so a long-lived installation slowly filled its
 * quota — and a full disk breaks the NEXT update in the worst possible way.
 *
 * Policy:
 *  - the staged extraction and package zip are deleted as soon as the apply succeeds;
 *  - only the active rollback point (LocalState's last_job_id) keeps its heavy file/DB
 *    backup, and only while within the rollback grace period (default 14 days);
 *  - older terminal jobs keep their directory/state for audit logs, but their heavy
 *    backups, staging, and package archives are purged;
 *  - abandoned in-flight jobs (>24h without an active lock) are transitioned to failed
 *    and their temporary artefacts removed;
 *  - orphan backups and packages without a valid active job are swept.
 */
final class JobRetention
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $keepJobs = 3,
        private readonly int $maxAgeSeconds = 604800,
        private readonly int $rollbackGraceSeconds = 1209600,
        private readonly int $staleJobTtlSeconds = 86400
    ) {
    }

    /**
     * Free the heavy artefacts of a rollback point that has aged out.
     *
     * The protected job is what a rollback would restore, so its file/DB backup
     * is kept for `rollback_grace_days` (default 14) after it finished. A
     * rollback is only meaningful while the applied build is still the current
     * one and something is still visibly wrong; after two weeks the backup is
     * the largest single artefact on the account (100+ MiB) and is worth the quota.
     * The job directory and its state stay, so the updates page still shows what was installed.
     *
     * Returns the paths freed.
     */
    public function pruneRollbackPoint(string $jobId): array
    {
        if ($jobId === '' || $this->rollbackGraceSeconds <= 0) {
            return [];
        }
        $dir = $this->storageDir . '/jobs/' . basename($jobId);
        $stateFile = $dir . '/state.json';
        if (!is_file($stateFile)) {
            return [];
        }
        $state = json_decode((string)@file_get_contents($stateFile), true);
        if (!is_array($state) || (string)($state['state'] ?? '') !== 'applied') {
            return [];
        }
        $finished = 0;
        if (!empty($state['finished_at'])) {
            $finished = is_numeric($state['finished_at']) ? (int)$state['finished_at'] : (int)strtotime((string)$state['finished_at']);
        }
        if ($finished <= 0) {
            $finished = (int)@filemtime($stateFile);
        }
        if ($finished <= 0 || (time() - $finished) < $this->rollbackGraceSeconds) {
            return [];
        }
        $freed = [];
        foreach ([
            $this->storageDir . '/staging/' . basename($jobId),
            $this->storageDir . '/packages/' . basename($jobId),
        ] as $path) {
            if ($this->removeTree($path)) {
                $freed[] = $path;
            }
        }
        foreach (glob($this->storageDir . '/backups/backup_' . basename($jobId) . '_*') ?: [] as $path) {
            if ($this->removeTree($path)) {
                $freed[] = $path;
            }
        }
        if ($freed !== []) {
            $state['rollback_artefacts_pruned_at'] = gmdate('c');
            @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        return $freed;
    }

    /** Delete the staged extraction of a finished job (safe after finalize). */
    public function dropStaging(string $jobId): bool
    {
        return $this->removeTree($this->storageDir . '/staging/' . basename($jobId));
    }

    /** Delete the downloaded package of a finished job. */
    public function dropPackage(string $jobId): bool
    {
        return $this->removeTree($this->storageDir . '/packages/' . basename($jobId));
    }

    /**
     * Prune old terminal jobs and release unnecessary backups. Returns the job ids that were removed.
     *
     * @param string $protectJobId the currently installed job (active rollback point)
     * @return array<int,string>
     */
    public function prune(?string $protectJobId = null): array
    {
        $this->reconcileStaleInFlightJobs();

        $states = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        $terminal = [];
        foreach ($states as $file) {
            $state = json_decode((string)@file_get_contents($file), true);
            if (!is_array($state)) {
                continue;
            }
            $status = (string)($state['state'] ?? '');
            if (!in_array($status, ['applied', 'rolled_back', 'failed'], true)) {
                continue; // active in-flight work is never touched
            }
            $jobId = (string)($state['job_id'] ?? basename(dirname($file)));
            if ($jobId === '') {
                continue;
            }
            $terminal[] = ['job_id' => $jobId, 'mtime' => (int)@filemtime($file)];
        }
        // Newest first so "keep the most recent" is well defined.
        usort($terminal, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $now = time();
        $removed = [];
        foreach ($terminal as $index => $job) {
            $isProtected = ($protectJobId !== null && $job['job_id'] === $protectJobId);

            if ($index < max(1, $this->keepJobs) || $isProtected) {
                // Job metadata is kept inside keepJobs (or if protected).
                // But for terminal jobs that are NOT the active rollback point,
                // purge their heavy file+DB backups, staging, and packages!
                if (!$isProtected) {
                    $this->removeTree($this->storageDir . '/backups/' . 'backup_' . $job['job_id'] . '_' . '*', true);
                    $this->removeTree($this->storageDir . '/staging/' . basename($job['job_id']));
                    $this->removeTree($this->storageDir . '/packages/' . basename($job['job_id']));
                }
                continue;
            }

            // Beyond keepJobs: check if still inside grace period (index < keepJobs * 2 and age < maxAgeSeconds)
            if ($this->maxAgeSeconds > 0 && ($now - $job['mtime']) < $this->maxAgeSeconds && $index < max(1, $this->keepJobs) * 2) {
                // Still inside grace period: keep job metadata, but purge heavy backups/staging/packages
                $this->removeTree($this->storageDir . '/backups/' . 'backup_' . $job['job_id'] . '_' . '*', true);
                $this->removeTree($this->storageDir . '/staging/' . basename($job['job_id']));
                $this->removeTree($this->storageDir . '/packages/' . basename($job['job_id']));
                continue;
            }

            // Aged out beyond keepJobs: prune completely
            $this->removeTree($this->storageDir . '/jobs/' . basename($job['job_id']));
            $this->removeTree($this->storageDir . '/backups/' . 'backup_' . $job['job_id'] . '_' . '*', true);
            $this->removeTree($this->storageDir . '/staging/' . basename($job['job_id']));
            $this->removeTree($this->storageDir . '/packages/' . basename($job['job_id']));
            $removed[] = $job['job_id'];
        }

        // For the active rollback point, prune if aged beyond rollback grace days
        if ($protectJobId !== null && $protectJobId !== '') {
            $this->pruneRollbackPoint($protectJobId);
        }

        // Leftovers whose job is gone entirely or orphan backup/staging/packages
        $this->dropOrphanArtefacts(array_column($terminal, 'job_id'), $protectJobId);

        return $removed;
    }

    /**
     * Mark abandoned in-flight jobs older than staleJobTtlSeconds as failed,
     * and free their temporary files so they don't block disk cleanup indefinitely.
     */
    private function reconcileStaleInFlightJobs(): void
    {
        $now = time();
        $lockFile = $this->storageDir . '/locks/update.lock';
        $isSystemLocked = is_file($lockFile);

        foreach (glob($this->storageDir . '/jobs/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $stateFile = $dir . '/state.json';
            if (!is_file($stateFile)) {
                if (($now - (int)@filemtime($dir)) > $this->staleJobTtlSeconds) {
                    $this->removeTree($dir);
                }
                continue;
            }

            $state = json_decode((string)@file_get_contents($stateFile), true);
            if (!is_array($state)) {
                continue;
            }

            $status = (string)($state['state'] ?? '');
            if (in_array($status, ['applied', 'rolled_back', 'failed'], true)) {
                continue; // Already terminal
            }

            $mtime = (int)@filemtime($stateFile);
            if (($now - $mtime) > $this->staleJobTtlSeconds) {
                if ($isSystemLocked) {
                    $lockMtime = (int)@filemtime($lockFile);
                    if (($now - $lockMtime) < 300) {
                        continue;
                    }
                }

                $jobId = (string)($state['job_id'] ?? basename($dir));
                $state['state'] = 'failed';
                $state['error'] = 'Update job abandoned or timed out.';
                $state['finished_at'] = gmdate('c');
                @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                $this->removeTree($this->storageDir . '/staging/' . basename($jobId));
                $this->removeTree($this->storageDir . '/packages/' . basename($jobId));
                $this->removeTree($this->storageDir . '/backups/backup_' . basename($jobId) . '_*', true);
            }
        }
    }

    /**
     * Remove staging/packages/backups directories that belong to no known job.
     *
     * @param array<int,string> $knownJobIds terminal job ids that still exist
     * @return array<int,string> the artefact paths removed
     */
    private function dropOrphanArtefacts(array $knownJobIds, ?string $protectJobId = null): array
    {
        $known = [];
        foreach ($knownJobIds as $jobId) {
            if (is_dir($this->storageDir . '/jobs/' . basename((string)$jobId))) {
                $known[basename((string)$jobId)] = true;
            }
        }
        // In-flight (non-stale) jobs keep their staging/packages
        foreach (glob($this->storageDir . '/jobs/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $state = json_decode((string)@file_get_contents($dir . '/state.json'), true);
            $status = is_array($state) ? (string)($state['state'] ?? '') : '';
            if (!in_array($status, ['applied', 'rolled_back', 'failed'], true)) {
                $known[basename($dir)] = true;
            }
        }

        $removed = [];
        foreach (['staging', 'packages'] as $kind) {
            foreach (glob($this->storageDir . '/' . $kind . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (isset($known[basename($dir)])) {
                    continue;
                }
                if ($this->removeTree($dir)) {
                    $removed[] = $dir;
                }
            }
        }

        // Sweep orphan backups: only protectJobId or active in-flight jobs may keep backups
        $validBackupJobIds = [];
        if ($protectJobId !== null && $protectJobId !== '') {
            $validBackupJobIds[basename($protectJobId)] = true;
        }
        foreach ($known as $jobId => $true) {
            $stateFile = $this->storageDir . '/jobs/' . $jobId . '/state.json';
            $state = is_file($stateFile) ? json_decode((string)@file_get_contents($stateFile), true) : null;
            $status = is_array($state) ? (string)($state['state'] ?? '') : '';
            if (!in_array($status, ['applied', 'rolled_back', 'failed'], true)) {
                $validBackupJobIds[$jobId] = true;
            }
        }

        foreach (glob($this->storageDir . '/backups/backup_*', GLOB_ONLYDIR) ?: [] as $backupDir) {
            $base = basename($backupDir);
            $matched = false;
            foreach (array_keys($validBackupJobIds) as $validJobId) {
                if (str_starts_with($base, 'backup_' . $validJobId)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                if ($this->removeTree($backupDir)) {
                    $removed[] = $backupDir;
                }
            }
        }

        // Clean up orphan or stale .nonce_* files in backups/
        $now = time();
        foreach (glob($this->storageDir . '/backups/.nonce_*') ?: [] as $nonceFile) {
            $nonceJobId = substr(basename($nonceFile), strlen('.nonce_'));
            if (!isset($validBackupJobIds[$nonceJobId]) || ($now - (int)@filemtime($nonceFile)) > 86400) {
                @unlink($nonceFile);
                $removed[] = $nonceFile;
            }
        }

        return $removed;
    }

    /** Remove a staged directory or a glob of backup directories. */
    private function removeTree(string $path, bool $glob = false): bool
    {
        if ($glob) {
            $found = false;
            foreach (glob($path) ?: [] as $match) {
                $found = $this->removeTree($match) || $found;
            }
            return $found;
        }
        if (is_link($path)) {
            return false; // never follow a link out of the storage tree
        }
        if (!file_exists($path)) {
            return false;
        }
        if (is_file($path)) {
            return @unlink($path);
        }
        $removed = false;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $removed = $this->removeTree($path . '/' . $entry) || $removed;
        }
        return @rmdir($path) || $removed;
    }
}
