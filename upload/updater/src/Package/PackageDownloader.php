<?php
declare(strict_types=1);

namespace Updater\Package;

use Updater\Util\HttpClient;

final class PackageDownloader
{
    private const DEFAULT_CHUNK_BYTES = 1048576;

    public function __construct(
        private readonly string $storageDir,
        private readonly int $timeoutSeconds = 30,
        private readonly int $chunkBytes = self::DEFAULT_CHUNK_BYTES
    ) {
    }

    public function downloadStep(string $jobId, array $package): array
    {
        $jobId = basename($jobId);
        if ($jobId === '' || $jobId === '.' || $jobId === '..') {
            throw new \RuntimeException('Invalid update job id.');
        }
        $url = (string)($package['url'] ?? '');
        $expectedSize = (int)($package['size_bytes'] ?? 0);
        $expectedSha = strtolower((string)($package['sha256'] ?? ''));
        if ($url === '' || $expectedSize < 1 || preg_match('/^[a-f0-9]{64}$/', $expectedSha) !== 1) {
            throw new \RuntimeException('Update package metadata is incomplete.');
        }

        $chunkBytes = min(4 * 1024 * 1024, max(65536, $this->chunkBytes));
        $dir = $this->storageDir . '/packages/' . $jobId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create package storage directory.');
        }
        $tmp = $dir . '/package.tmp';
        $meta = $dir . '/package.tmp.json';
        $final = $dir . '/package.zip';
        $chunk = $dir . '/package.chunk.tmp';
        $lock = @fopen($dir . '/download.lock', 'c');
        if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            throw new \RuntimeException('Package download is already running for this update.');
        }

        try {
            $record = is_file($meta) ? json_decode((string)@file_get_contents($meta), true) : null;
            if (is_file($tmp) && (!is_array($record)
                || ($record['url'] ?? null) !== $url
                || (int)($record['size'] ?? 0) !== $expectedSize
                || strtolower((string)($record['sha256'] ?? '')) !== $expectedSha)) {
                @unlink($tmp);
            }
            if (is_file($final) && $this->matches($final, $expectedSize, $expectedSha)) {
                return ['done' => true, 'bytes' => $expectedSize, 'total' => $expectedSize, 'path' => $final];
            }
            if (is_file($final)) @unlink($final);
            $metaJson = json_encode(['url' => $url, 'size' => $expectedSize, 'sha256' => $expectedSha], JSON_UNESCAPED_SLASHES);
            if (!is_string($metaJson) || @file_put_contents($meta, $metaJson, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to persist package download state.');
            }

            clearstatcache(true, $tmp);
            $offset = is_file($tmp) ? (int)filesize($tmp) : 0;
            if ($offset < 0 || $offset > $expectedSize) {
                @unlink($tmp);
                $offset = 0;
            }
            if ($offset === $expectedSize) {
                return $this->finalize($tmp, $meta, $final, $expectedSize, $expectedSha);
            }

            $end = min($expectedSize - 1, $offset + $chunkBytes - 1);
            @unlink($chunk);
            $response = HttpClient::request($url, [
                'timeout' => max(5, min(60, (int)($package['timeout'] ?? $this->timeoutSeconds))),
                'stream_to' => $chunk,
                'stream_max_bytes' => $end - $offset + 1,
                'headers' => ['Range' => 'bytes=' . $offset . '-' . $end],
            ]);
            $status = (int)($response['status'] ?? 0);
            $responseHeaders = is_array($response['headers'] ?? null) ? $response['headers'] : [];
            $bytes = is_file($chunk) ? (int)filesize($chunk) : 0;
            $contentRange = $this->headerValue($responseHeaders, 'content-range');
            $contentLength = $this->headerValue($responseHeaders, 'content-length');
            $expectedChunkBytes = $end - $offset + 1;
            $hasContentLength = is_string($contentLength) && ctype_digit($contentLength);
            $transferWasCut = $hasContentLength && (int)$contentLength > $bytes;
            $shortRangeWithoutLength = !$hasContentLength && $bytes > 0 && $bytes < $expectedChunkBytes;
            $validPartial = $status === 206
                && is_string($contentRange)
                && preg_match('/^bytes\s+(\d+)-(\d+)\/(\d+)$/i', trim($contentRange), $range) === 1
                && (int)$range[1] === $offset
                && (int)$range[2] === $end
                && (int)$range[3] === $expectedSize;
            $smallFullResponse = $status === 200 && $offset === 0 && $expectedSize <= $chunkBytes && $bytes === $expectedSize;

            if (!$validPartial && !$smallFullResponse) {
                @unlink($chunk);
                if ($status === 200) {
                    throw new \RuntimeException('The update server does not support bounded Range downloads.');
                }
                throw new \RuntimeException('The update server returned an invalid or incomplete byte range (HTTP ' . $status . ').');
            }
            if ($bytes < 1 || $bytes > $expectedChunkBytes) {
                @unlink($chunk);
                throw new \RuntimeException('The downloaded package chunk has an invalid size.');
            }
            if ($validPartial && $bytes < $expectedChunkBytes && !$transferWasCut && !$shortRangeWithoutLength) {
                @unlink($chunk);
                throw new \RuntimeException('The update server returned a short range without indicating an interrupted transfer.');
            }

            $target = @fopen($tmp, $offset > 0 ? 'ab' : 'wb');
            $source = @fopen($chunk, 'rb');
            if ($target === false || $source === false) {
                if (is_resource($target)) fclose($target);
                if (is_resource($source)) fclose($source);
                @unlink($chunk);
                throw new \RuntimeException('Unable to persist the downloaded package chunk.');
            }
            $copied = @stream_copy_to_stream($source, $target);
            fclose($source);
            $flushOk = @fflush($target);
            fclose($target);
            @unlink($chunk);
            clearstatcache(true, $tmp);
            if ($copied === false || $copied !== $bytes || !$flushOk || (int)filesize($tmp) !== $offset + $bytes) {
                throw new \RuntimeException('Unable to persist the complete package chunk.');
            }

            $done = (int)filesize($tmp);
            if ($done < $expectedSize) {
                return ['done' => false, 'bytes' => $done, 'total' => $expectedSize, 'path' => null];
            }
            if ($done !== $expectedSize) {
                @unlink($tmp);
                throw new \RuntimeException('Downloaded package size exceeds the manifest value.');
            }
            return $this->finalize($tmp, $meta, $final, $expectedSize, $expectedSha);
        } finally {
            @unlink($chunk);
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function finalize(string $tmp, string $meta, string $final, int $expectedSize, string $expectedSha): array
    {
        if (!$this->matches($tmp, $expectedSize, $expectedSha)) {
            @unlink($tmp);
            @unlink($meta);
            throw new \RuntimeException('Downloaded package failed size or sha256 verification.');
        }
        if (!@rename($tmp, $final)) {
            if (!@copy($tmp, $final)) {
                @unlink($final);
                throw new \RuntimeException('Unable to move the verified package into place.');
            }
            if (!$this->matches($final, $expectedSize, $expectedSha)) {
                @unlink($final);
                throw new \RuntimeException('Copied package failed size or sha256 verification.');
            }
            if (!@unlink($tmp)) {
                @unlink($final);
                throw new \RuntimeException('Unable to remove the temporary package after copying.');
            }
        }
        @unlink($meta);
        return ['done' => true, 'bytes' => $expectedSize, 'total' => $expectedSize, 'path' => $final];
    }

    private function matches(string $path, int $size, string $sha256): bool
    {
        if (!is_file($path) || (int)filesize($path) !== $size) return false;
        return hash_equals($sha256, strtolower((string)(hash_file('sha256', $path) ?: '')));
    }

    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            $pos = strpos((string)$header, ':');
            if ($pos !== false && strtolower(trim(substr((string)$header, 0, $pos))) === strtolower($name)) {
                return trim(substr((string)$header, $pos + 1));
            }
        }
        return null;
    }
}
