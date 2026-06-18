<?php
declare(strict_types=1);

namespace Updater\Package;

final class PackageDownloader
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function download(string $jobId, array $package): string
    {
        $dir = $this->storageDir . '/packages/' . basename($jobId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $dir . '/package.tmp';
        $final = $dir . '/package.zip';
        $context = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
        $data = @file_get_contents((string)$package['url'], false, $context);
        if ($data === false) {
            throw new \RuntimeException('Unable to download package.');
        }
        file_put_contents($tmp, $data);
        if (filesize($tmp) !== (int)$package['size_bytes']) {
            @unlink($tmp);
            throw new \RuntimeException('Downloaded package size mismatch.');
        }
        if ((hash_file('sha256', $tmp) ?: '') !== (string)$package['sha256']) {
            @unlink($tmp);
            throw new \RuntimeException('Downloaded package sha256 mismatch.');
        }
        rename($tmp, $final);
        return $final;
    }
}
