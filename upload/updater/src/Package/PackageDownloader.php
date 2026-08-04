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
        // Stream the download to disk (fopen + stream_copy_to_stream) instead
        // of file_get_contents into memory: a 100MB package must not blow the
        // shared-hosting memory_limit with an UNCATCHABLE fatal error.
        $context = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
        $remote = @fopen((string)$package['url'], 'rb', false, $context);
        if ($remote === false) {
            throw new \RuntimeException('Unable to download package.');
        }
        $local = @fopen($tmp, 'wb');
        if ($local === false) {
            fclose($remote);
            throw new \RuntimeException('Unable to open package destination.');
        }
        $copied = @stream_copy_to_stream($remote, $local);
        fclose($remote);
        fclose($local);
        if ($copied === false) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to download package.');
        }
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
