<?php
declare(strict_types=1);

namespace Updater\Apply;

use Updater\Package\PathGuard;
use Updater\State\JobState;

/** Bounded, read-only verification of a completed updater job's managed files. */
final class InstalledSnapshotReader
{
    private readonly ?\Closure $migrationStatusReader;

    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir,
        private readonly array $protectedPaths,
        ?callable $migrationStatusReader = null
    ) {
        $this->migrationStatusReader = $migrationStatusReader === null ? null : \Closure::fromCallable($migrationStatusReader);
    }

    /** @return array<string,mixed> */
    public function read(string $jobId, int $cursor = 0, int $limit = 100): array
    {
        if (preg_match('/\A[A-Za-z0-9_-][A-Za-z0-9._-]{0,79}\z/D', $jobId) !== 1
            || $cursor < 0 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('SNAPSHOT_ARGUMENT_INVALID');
        }

        $stateDir = $this->storageDir . '/jobs/' . $jobId;
        $manifestPath = $stateDir . '/manifest.json';
        $this->assertBoundedRegularFile($stateDir . '/state.json', 262144);
        $this->assertBoundedRegularFile($manifestPath, 4194304);
        $state = new JobState($this->storageDir, $jobId);
        $job = $state->readFile('state.json') ?? [];
        $manifest = $state->readFile('manifest.json') ?? [];
        $installed = (new \Updater\State\LocalState($this->storageDir))->read();
        $targetSha = strtolower((string)($manifest['to_sha'] ?? ''));
        $build = (string)($manifest['to_build'] ?? '');
        $manifestInfo = @lstat($manifestPath);
        $manifestBytes = @file_get_contents($manifestPath);
        if (!is_array($manifestInfo) || is_link($manifestPath) || !is_string($manifestBytes)) {
            throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
        }
        $manifestDigest = hash('sha256', $manifestBytes);
        if (($job['state'] ?? null) !== 'applied'
            || ($installed['state'] ?? null) !== 'installed'
            || ($installed['last_job_id'] ?? null) !== $jobId
            || !preg_match('/\A[a-f0-9]{40}\z/D', $targetSha)
            || !hash_equals($targetSha, strtolower((string)($installed['source_sha'] ?? '')))
            || preg_match('/\A[A-Za-z0-9._+-]{1,64}\z/D', $build) !== 1
            || !is_string($installed['core_build'] ?? null)
            || !hash_equals($build, (string)$installed['core_build'])) {
            throw new \RuntimeException('SNAPSHOT_INSTALLED_JOB_UNCONFIRMED');
        }
        $updateLock = new \Updater\State\LockManager($this->storageDir);
        if (($updateLock->isLocked() && !$updateLock->isStale())
            || is_file($this->basePath . '/storage_api/maintenance.flag')) {
            throw new \RuntimeException('SNAPSHOT_UPDATE_IN_PROGRESS');
        }

        $expected = $this->expectedFiles($manifest);
        if (count($expected) > 100000) {
            throw new \RuntimeException('SNAPSHOT_MANIFEST_TOO_MANY_FILES');
        }
        if ($cursor > count($expected)) {
            throw new \InvalidArgumentException('SNAPSHOT_CURSOR_INVALID');
        }
        $page = array_slice($expected, $cursor, $limit);
        $root = realpath($this->basePath);
        if ($root === false) {
            throw new \RuntimeException('SNAPSHOT_ROOT_UNAVAILABLE');
        }

        $guard = new PathGuard($this->protectedPaths);
        $files = [];
        $bytesRead = 0;
        foreach ($page as $item) {
            $path = (string)$item['path'];
            if ($guard->normalize($path) !== $path || !$guard->isAllowed($path)) {
                throw new \RuntimeException('SNAPSHOT_PATH_REJECTED');
            }
            $target = $root;
            foreach (explode('/', $path) as $component) {
                $target .= DIRECTORY_SEPARATOR . $component;
                clearstatcache(true, $target);
                if (is_link($target)) {
                    throw new \RuntimeException('SNAPSHOT_SYMLINK_REJECTED');
                }
            }

            if ($item['kind'] === 'deleted') {
                $exists = file_exists($target) || is_link($target);
                $files[] = ['path' => $path, 'kind' => 'deleted', 'exists' => $exists,
                    'matches' => !$exists];
                continue;
            }

            if (!file_exists($target) && !is_link($target)) {
                $files[] = ['path' => $path, 'kind' => 'file', 'expected_sha256' => $item['sha256'],
                    'sha256' => null, 'mode' => null, 'owner_id' => null, 'group_id' => null,
                    'matches' => false];
                continue;
            }
            [$actual, $stat, $size] = $this->hashFileSafely($root, $target, 67108864 - $bytesRead);
            $bytesRead += $size;
            $files[] = ['path' => $path, 'kind' => 'file', 'expected_sha256' => $item['sha256'],
                'sha256' => $actual, 'mode' => $stat['mode'] & 0777,
                'owner_id' => $stat['uid'], 'group_id' => $stat['gid'],
                'matches' => hash_equals($item['sha256'], $actual)];
        }

        $this->assertBoundedRegularFile($stateDir . '/migrations.json', 4194304);
        $this->assertBoundedRegularFile($stateDir . '/health.json', 262144);
        $migrationReport = $state->readFile('migrations.json') ?? [];
        $health = $state->readFile('health.json') ?? [];
        $databaseStatus = $this->migrationStatusReader !== null
            ? ($this->migrationStatusReader)()
            : $this->readDatabaseMigrationStatus();
        $nextCursor = $cursor + count($page);
        return [
            'job_id' => $jobId,
            'source_sha' => $targetSha,
            'build' => $build,
            'job_manifest_sha256' => $manifestDigest,
            'expected_file_map_sha256' => $this->canonicalFileMapDigest($expected),
            'cursor' => $cursor,
            'next_cursor' => $nextCursor,
            'total' => count($expected),
            'complete' => $nextCursor >= count($expected),
            'files' => $files,
            'health_ok' => ($health['ok'] ?? null) === true,
            'migration_report_sha256' => hash('sha256', json_encode(
                $migrationReport, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )),
            'migrations' => $this->migrationSummary($migrationReport, $databaseStatus),
        ];
    }

    private function assertBoundedRegularFile(string $path, int $maxBytes): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (!is_array($stat) || is_link($path) || ($stat['mode'] & 0170000) !== 0100000
            || $stat['size'] < 0 || $stat['size'] > $maxBytes) {
            throw new \RuntimeException('SNAPSHOT_STATE_FILE_INVALID');
        }
    }

    /** @return array{0:string,1:array<string,int>,2:int} */
    private function hashFileSafely(string $root, string $target, int $remainingPageBytes): array
    {
        $handle = @fopen($target, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('SNAPSHOT_FILE_READ_FAILED');
        }
        try {
            $before = fstat($handle);
            $real = realpath($target);
            $pathStat = @lstat($target);
            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!is_array($before) || !is_array($pathStat) || $real === false
                || !str_starts_with($real, $rootPrefix)
                || ($before['mode'] & 0170000) !== 0100000
                || ($pathStat['mode'] & 0170000) !== 0100000
                || $before['dev'] !== $pathStat['dev'] || $before['ino'] !== $pathStat['ino']
                || $before['size'] < 0 || $before['size'] > min(33554432, $remainingPageBytes)) {
                throw new \RuntimeException('SNAPSHOT_FILE_PATH_UNSAFE_OR_TOO_LARGE');
            }
            $context = hash_init('sha256');
            $bytesRead = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk === false) {
                    throw new \RuntimeException('SNAPSHOT_FILE_READ_FAILED');
                }
                if ($chunk === '') {
                    if (!feof($handle)) { throw new \RuntimeException('SNAPSHOT_FILE_READ_FAILED'); }
                    break;
                }
                $bytesRead += strlen($chunk);
                if ($bytesRead > min(33554432, $remainingPageBytes)) {
                    throw new \RuntimeException('SNAPSHOT_PAGE_BYTE_BUDGET_EXCEEDED');
                }
                hash_update($context, $chunk);
            }
            $after = fstat($handle);
            $pathAfter = @lstat($target);
            if (!is_array($after) || !is_array($pathAfter)
                || $after['dev'] !== $before['dev'] || $after['ino'] !== $before['ino']
                || $after['size'] !== $before['size'] || $after['mtime'] !== $before['mtime']
                || $pathAfter['dev'] !== $before['dev'] || $pathAfter['ino'] !== $before['ino']
                || ($pathAfter['mode'] & 0170000) !== 0100000) {
                throw new \RuntimeException('SNAPSHOT_FILE_CHANGED_DURING_READ');
            }
            if ($bytesRead !== (int)$before['size']) {
                throw new \RuntimeException('SNAPSHOT_FILE_CHANGED_DURING_READ');
            }
            return [hash_final($context), $before, $bytesRead];
        } finally {
            fclose($handle);
        }
    }

    private function readDatabaseMigrationStatus(): array
    {
        $connection = \Updater\Db\Connection::open($this->basePath);
        $manager = new \Api\System\Library\Database\Migration\MigrationManager(
            new \Api\System\Library\Database\SchemaManager()
        );
        return $manager->readStatus($connection['pdo'], $connection['driver']);
    }

    /** @return array<int,array{path:string,kind:string,sha256?:string}> */
    private function expectedFiles(array $manifest): array
    {
        $hashes = $manifest['file_hashes'] ?? null;
        $changes = $manifest['files'] ?? null;
        if (!is_array($hashes) || !is_array($changes)) {
            throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
        }
        $expected = [];
        foreach (['add', 'modify'] as $group) {
            $paths = $changes[$group] ?? [];
            if (!is_array($paths) || !array_is_list($paths)) {
                throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
            }
            foreach ($paths as $path) {
                if (!is_string($path) || isset($expected[$path])) {
                    throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
                }
                $value = $hashes[$path] ?? null;
                if (!is_array($value)) {
                    throw new \RuntimeException('SNAPSHOT_MANIFEST_HASH_MISSING');
                }
                $sha = strtolower((string)($value['sha256'] ?? ''));
                if (preg_match('/\A[a-f0-9]{64}\z/D', $sha) !== 1) {
                    throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
                }
                $expected[$path] = ['path' => $path, 'kind' => 'file', 'sha256' => $sha];
            }
        }
        foreach ($hashes as $path => $_value) {
            if (!is_string($path) || !isset($expected[$path])) {
                throw new \RuntimeException('SNAPSHOT_MANIFEST_FILE_MAP_MISMATCH');
            }
        }
        $deletes = $changes['delete'] ?? [];
        if (!is_array($deletes) || !array_is_list($deletes)) {
            throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
        }
        foreach ($deletes as $path) {
            if (!is_string($path) || isset($expected[$path]) || array_key_exists($path, $hashes)) {
                throw new \RuntimeException('SNAPSHOT_MANIFEST_INVALID');
            }
            $expected[$path] = ['path' => $path, 'kind' => 'deleted'];
        }
        ksort($expected, SORT_STRING);
        return array_values($expected);
    }

    private function canonicalFileMapDigest(array $expected): string
    {
        $map = [];
        foreach ($expected as $item) {
            $map[$item['path']] = $item['kind'] === 'file' ? $item['sha256'] : 'deleted';
        }
        ksort($map, SORT_STRING);
        return hash('sha256', json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function migrationSummary(array $report, array $databaseStatus): array
    {
        $reportedApplied = $report['applied_total'] ?? null;
        $reportedPending = $report['pending_after'] ?? null;
        $executed = $report['executed'] ?? null;
        $dbApplied = $databaseStatus['applied'] ?? null;
        $dbPending = $databaseStatus['pending'] ?? null;
        $knownKeys = $databaseStatus['all'] ?? null;
        $keys = [];
        foreach (is_array($dbApplied) ? $dbApplied : [] as $key) {
            if (!is_string($key) || preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/D', $key) !== 1
                || isset($keys[$key])) {
                return ['valid' => false, 'applied' => count($keys), 'failed' => 1,
                    'pending' => 0, 'keys' => array_keys($keys)];
            }
            $keys[$key] = true;
        }
        $reportKeys = static function ($values): ?array {
            if (!is_array($values)) { return null; }
            foreach ($values as $value) {
                if (!is_string($value) || preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/D', $value) !== 1) { return null; }
            }
            $unique = array_values(array_unique($values));
            if (count($unique) !== count($values)) { return null; }
            $values = $unique;
            sort($values, SORT_STRING);
            return $values;
        };
        $appliedList = array_keys($keys);
        sort($appliedList, SORT_STRING);
        $reportedAppliedList = $reportKeys($reportedApplied);
        $dbAppliedList = $reportKeys($dbApplied);
        $reportedPendingList = $reportKeys($reportedPending);
        $dbPendingList = $reportKeys($dbPending);
        $executedList = $reportKeys($executed);
        $knownList = $reportKeys($knownKeys);
        $valid = ($report['ok'] ?? null) === true && ($report['done'] ?? null) === true
            && ($databaseStatus['table_exists'] ?? null) === true
            && $reportedAppliedList !== null && $dbAppliedList !== null
            && $executedList !== null && $knownList !== null
            && $reportedPendingList === [] && $dbPendingList === []
            && $reportedAppliedList === $dbAppliedList
            && array_diff($dbAppliedList, $knownList) === []
            && array_diff($reportedAppliedList, $knownList) === []
            && array_diff($executedList, $reportedAppliedList) === []
            && $appliedList === $dbAppliedList;
        return ['valid' => $valid, 'applied' => count($keys), 'failed' => $valid ? 0 : 1,
            'pending' => is_array($dbPending) ? count($dbPending) : 0, 'keys' => $appliedList];
    }
}
