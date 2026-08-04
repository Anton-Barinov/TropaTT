<?php
declare(strict_types=1);

namespace Updater\Package;

use Updater\Util\WorkBudget;

final class PackageExtractor
{
    public function __construct(private readonly string $storageDir, private readonly array $protectedPatterns)
    {
    }

    /**
     * Extract the package into the staging directory, optionally resuming from
     * $cursor with a bounded amount of work per call.
     *
     * All entry names are validated against the protected-path guard BEFORE
     * any extraction (first call), so a forbidden path aborts before a single
     * file lands in staging. Extraction itself is chunked: each call unzips at
     * most $maxFiles entries, and the validated entry list is persisted so
     * later calls resume without re-scanning the whole archive.
     *
     * @return array{done:bool,cursor:int,total:int,files:array<int,string>}
     */
    public function extract(string $jobId, string $zipPath, int $cursor = 0, ?WorkBudget $budget = null, int $maxFiles = 150): array
    {
        $target = $this->storageDir . '/staging/' . basename($jobId);
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }
        $listFile = $this->storageDir . '/staging/' . basename($jobId) . '.list.json';

        $guard = new PathGuard($this->protectedPatterns);
        $names = $this->loadOrBuildEntryList($zipPath, $listFile, $guard);
        $total = count($names);
        $startCursor = min(max(0, $cursor), $total);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open update package zip.');
        }

        $chunk = array_slice($names, $startCursor, $maxFiles);
        if ($budget === null) {
            // No budget: extract the whole chunk in one call (fast path).
            if ($chunk !== [] && $zip->extractTo($target, $chunk) !== true) {
                $zip->close();
                throw new \RuntimeException('Unable to extract update package.');
            }
            $zip->close();
            $newCursor = $startCursor + count($chunk);
        } else {
            // Budgeted: extract entry by entry and stop as soon as the request
            // time budget is spent, so even very large entries cannot blow a
            // shared-hosting request timeout.
            $newCursor = $startCursor;
            foreach ($chunk as $name) {
                if ($budget->exhausted()) {
                    break;
                }
                if ($zip->extractTo($target, [$name]) !== true) {
                    $zip->close();
                    throw new \RuntimeException('Unable to extract update package.');
                }
                $newCursor++;
            }
            $zip->close();
        }

        return [
            'done' => $newCursor >= $total,
            'cursor' => $newCursor,
            'total' => $total,
            'files' => array_slice($names, $startCursor, $newCursor - $startCursor),
        ];
    }

    /**
     * Read the persisted entry list, or build + persist it (validating every
     * name) on the first call.
     *
     * @return array<int,string>
     */
    private function loadOrBuildEntryList(string $zipPath, string $listFile, PathGuard $guard): array
    {
        if (is_file($listFile)) {
            $cached = json_decode((string)file_get_contents($listFile), true);
            if (is_array($cached) && isset($cached['names']) && is_array($cached['names'])) {
                return array_values(array_filter(array_map('strval', $cached['names'])));
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open update package zip.');
        }
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (!$guard->isAllowed($name)) {
                $zip->close();
                throw new \RuntimeException('Package contains forbidden path: ' . $name);
            }
            $stat = $zip->statIndex($i);
            if (($stat['size'] ?? 0) === 0 && str_ends_with($name, '/')) {
                continue;
            }
            $names[] = $name;
        }
        $zip->close();

        file_put_contents($listFile, json_encode(['names' => $names], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $names;
    }
}
