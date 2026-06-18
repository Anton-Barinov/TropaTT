<?php
declare(strict_types=1);

namespace Updater\Package;

final class PackageExtractor
{
    public function __construct(private readonly string $storageDir, private readonly array $protectedPatterns)
    {
    }

    public function extract(string $jobId, string $zipPath): array
    {
        $target = $this->storageDir . '/staging/' . basename($jobId);
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Unable to open update package zip.');
        }
        $guard = new PathGuard($this->protectedPatterns);
        $files = [];
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
            $files[] = $name;
        }
        if (!$zip->extractTo($target)) {
            $zip->close();
            throw new \RuntimeException('Unable to extract update package.');
        }
        $zip->close();
        return $files;
    }
}
