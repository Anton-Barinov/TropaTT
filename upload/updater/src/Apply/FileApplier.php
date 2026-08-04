<?php
declare(strict_types=1);

namespace Updater\Apply;

use Updater\Package\PathGuard;
use Updater\Util\WorkBudget;

final class FileApplier
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir,
        private readonly array $protectedPaths
    ) {
    }

    /**
     * Apply the package files, optionally resuming from $cursor with a bounded
     * amount of work per call.
     *
     * The plan is a flat ordered list: deletes first, then adds, then
     * modifies (file order within a category does not matter). With a budget
     * set, each call processes at most $maxFiles entries starting at $cursor
     * and returns 'done' => false until the whole plan is applied, so a huge
     * update runs as many small requests instead of one request that a shared
     * host would cut mid-way.
     *
     * @return array{done:bool,cursor:int,total:int,count:int,files:array<int,array<string,mixed>>}
     */
    public function apply(string $jobId, array $manifest, int $cursor = 0, ?WorkBudget $budget = null, int $maxFiles = 150): array
    {
        $stagingDir = $this->storageDir . '/staging/' . basename($jobId);
        if (!is_dir($stagingDir)) {
            throw new \RuntimeException('Staging directory is missing.');
        }

        $guard = new PathGuard($this->protectedPaths);
        $plan = $this->buildPlan($manifest, $guard);
        $total = count($plan);
        $startCursor = min(max(0, $cursor), $total);

        $applied = [];
        $hashes = is_array($manifest['file_hashes'] ?? null) ? $manifest['file_hashes'] : [];
        $position = $startCursor;
        while ($position < $total && count($applied) < $maxFiles) {
            if ($budget !== null && $budget->exhausted()) {
                break;
            }
            $entry = $plan[$position];
            $relative = $entry['path'];
            $action = $entry['action'];
            if ($action === 'delete') {
                $target = $this->basePath . '/' . $relative;
                if (is_file($target) && !unlink($target)) {
                    throw new \RuntimeException('Unable to delete file: ' . $relative);
                }
                $applied[] = ['path' => $relative, 'action' => 'delete'];
            } else {
                $source = $stagingDir . '/' . $relative;
                if (!is_file($source)) {
                    throw new \RuntimeException('Staged file is missing: ' . $relative);
                }
                $target = $this->basePath . '/' . $relative;
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }
                if (!copy($source, $target)) {
                    throw new \RuntimeException('Unable to apply file: ' . $relative);
                }
                $expected = is_array($hashes[$relative] ?? null) ? (string)($hashes[$relative]['sha256'] ?? '') : '';
                $actual = hash_file('sha256', $target) ?: '';
                if ($expected !== '' && !hash_equals($expected, $actual)) {
                    throw new \RuntimeException('Applied file hash mismatch: ' . $relative);
                }
                $applied[] = ['path' => $relative, 'action' => is_file($target) ? 'write' : 'add', 'sha256' => $actual];
            }
            $position++;
        }

        return [
            'done' => $position >= $total,
            'cursor' => $position,
            'total' => $total,
            'count' => count($applied),
            'files' => $applied,
        ];
    }

    /**
     * Build the flat, ordered apply plan (deletes, then adds, then modifies),
     * validating every path once up-front so a forbidden path aborts BEFORE
     * any file is touched (the plan is validated on every step, but a partial
     * apply can never have started when validation fails on the first call).
     *
     * @return array<int,array{path:string,action:string}>
     */
    private function buildPlan(array $manifest, PathGuard $guard): array
    {
        $files = $this->filesFromManifest($manifest);
        $plan = [];
        foreach ($files['delete'] as $relative) {
            $plan[] = ['path' => $this->assertAllowed($guard, $relative), 'action' => 'delete'];
        }
        foreach (array_merge($files['add'], $files['modify']) as $relative) {
            $plan[] = ['path' => $this->assertAllowed($guard, $relative), 'action' => 'write'];
        }
        return $plan;
    }

    /**
     * @return array{add:array<int,string>,modify:array<int,string>,delete:array<int,string>}
     */
    public function filesFromManifest(array $manifest): array
    {
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        return [
            'add' => array_values(array_filter(array_map('strval', is_array($files['add'] ?? null) ? $files['add'] : []))),
            'modify' => array_values(array_filter(array_map('strval', is_array($files['modify'] ?? null) ? $files['modify'] : []))),
            'delete' => array_values(array_filter(array_map('strval', is_array($files['delete'] ?? null) ? $files['delete'] : []))),
        ];
    }

    private function assertAllowed(PathGuard $guard, string $relative): string
    {
        $normalized = $guard->normalize($relative);
        if ($normalized === null || !$guard->isAllowed($normalized)) {
            throw new \RuntimeException('Forbidden update path: ' . $relative);
        }
        return $normalized;
    }
}
