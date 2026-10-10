<?php
declare(strict_types=1);

namespace Updater\State;

/**
 * Bounds the disk the updater keeps between runs.
 *
 * A shared-hosting account has a small disk quota, and the updater used to keep
 * everything it ever produced: every job directory, every staged extraction
 * (1429 files, 24 MiB on the demo), every package archive and every file/DB
 * backup. Nothing removed them, so a long-lived installation slowly filled its
 * quota — and a full disk breaks the NEXT update in the worst possible way
 * (a partially applied copy).
 *
 * Policy:
 *  - the staged extraction is deleted as soon as the apply succeeds; it is the
 *    largest artefact and the one that is never needed again;
 *  - a terminal job keeps its directory and backup while it is one of the most
 *    recent ones, or while it is the rollback point LocalState points at;
 *  - only terminal jobs (applied / rolled_back / failed) are pruned, and only
 *    their own job directory, staging, packages and backups subdirectories are
 *    touched — never the live installation, never a lock, lease or session.
 */
final class JobRetention
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $keepJobs = 3,
        private readonly int $maxAgeSeconds = 604800
    ) {
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
     * Prune old terminal jobs. Returns the job ids that were removed.
     *
     * @param string $protectJobId the currently installed job (rollback point)
     * @return array<int,string>
     */
    public function prune(?string $protectJobId = null): array
    {
        $states = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        $terminal = [];
        foreach ($states as $file) {
            $state = json_decode((string)@file_get_contents($file), true);
            if (!is_array($state)) {
                continue;
            }
            $status = (string)($state['state'] ?? '');
            if (!in_array($status, ['applied', 'rolled_back', 'failed'], true)) {
                continue; // in-flight work is never touched
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
            if ($index < max(0, $this->keepJobs)) {
                continue;
            }
            if ($this->maxAgeSeconds > 0 && ($now - $job['mtime']) < $this->maxAgeSeconds && $index < max(1, $this->keepJobs) * 2) {
                // Still inside the grace period: a recent failed job may be
                // about to be rolled back by the admin.
                continue;
            }
            if ($protectJobId !== null && $job['job_id'] === $protectJobId) {
                continue;
            }
            $this->removeTree($this->storageDir . '/jobs/' . basename($job['job_id']));
            $this->removeTree($this->storageDir . '/backups/' . 'backup_' . $job['job_id'] . '_' . '*', true);
            $this->removeTree($this->storageDir . '/staging/' . basename($job['job_id']));
            $this->removeTree($this->storageDir . '/packages/' . basename($job['job_id']));
            $removed[] = $job['job_id'];
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
