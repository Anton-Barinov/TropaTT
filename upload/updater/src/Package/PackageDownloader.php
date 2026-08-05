<?php
declare(strict_types=1);

namespace Updater\Package;

use Updater\Util\HttpClient;

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
        $result = HttpClient::request((string)$package['url'], [
            'timeout' => (int)($package['timeout'] ?? 120),
            'stream_to' => $tmp,
        ]);
        if (($result['ok'] ?? false) !== true || !is_file($tmp)) {
            @unlink($tmp);
            $error = (string)($result['error'] ?? '');
            throw new \RuntimeException('Unable to download package.' . ($error !== '' ? ' (' . $error . ')' : ''));
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
