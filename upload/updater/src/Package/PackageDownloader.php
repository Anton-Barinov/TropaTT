<?php
declare(strict_types=1);

namespace Updater\Package;

use Updater\Util\HttpClient;

/**
 * Streams the update package to disk, resuming a partial transfer.
 *
 * A cheap shared host is exactly where a multi-megabyte download is most likely
 * to be cut: a proxy idle timeout, a flaky link, a PHP request killed at the
 * host's own limit. The previous behaviour deleted the partial file on any
 * failure, so a package that almost arrived was thrown away and the next
 * attempt started from byte zero — on a slow line that can mean the update
 * never finishes, and every retry burns the customer's bandwidth quota.
 *
 * Now the partial file is kept (with the URL it came from, so a different
 * package can never be appended to) and the next attempt asks the server for
 * the rest with a Range request. Correctness is unchanged: the completed file
 * must still match the manifest's exact size and sha256 before it is used, so a
 * server that ignores Range and answers 200 restarts cleanly, and a corrupted
 * partial can only ever fail the checksum.
 */
final class PackageDownloader
{
    /** Attempts inside one request before giving up and leaving the partial. */
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $storageDir,
        private readonly int $timeoutSeconds = 120
    ) {
    }

    public function download(string $jobId, array $package): string
    {
        $dir = $this->storageDir . '/packages/' . basename($jobId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $dir . '/package.tmp';
        $meta = $dir . '/package.tmp.json';
        $final = $dir . '/package.zip';
        $url = (string)$package['url'];
        $expectedSize = (int)($package['size_bytes'] ?? 0);
        $expectedSha = (string)($package['sha256'] ?? '');
        $timeout = (int)($package['timeout'] ?? $this->timeoutSeconds);

        // Resume only the same package that produced this partial.
        $resumeFrom = 0;
        if (is_file($tmp) && is_file($meta)) {
            $record = json_decode((string)@file_get_contents($meta), true);
            if (is_array($record) && ($record['url'] ?? null) === $url) {
                $resumeFrom = max(0, (int)filesize($tmp));
                if ($expectedSize > 0 && $resumeFrom >= $expectedSize) {
                    // Complete (or longer than expected): validate below.
                    $resumeFrom = (int)filesize($tmp);
                }
            } else {
                @unlink($tmp);
                $resumeFrom = 0;
            }
        } elseif (is_file($tmp)) {
            // No provenance: never append to an unknown partial.
            @unlink($tmp);
            $resumeFrom = 0;
        }
        @file_put_contents($meta, json_encode(['url' => $url, 'size' => $expectedSize], JSON_UNESCAPED_SLASHES));

        $lastError = '';
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            // Recompute the offset from the file itself before every attempt:
            // a previous attempt appends to the partial, so a stale offset
            // would ask for bytes that are already on disk and re-append them.
            $resumeFrom = is_file($tmp) ? max(0, (int)filesize($tmp)) : 0;
            $result = HttpClient::request($url, [
                'timeout' => $timeout,
                'stream_to' => $tmp,
                'stream_range_from' => $resumeFrom,
            ]);
            $status = (int)($result['status'] ?? 0);
            $partialOk = $resumeFrom > 0 && $status === 206;
            if (($result['ok'] ?? false) !== true && !$partialOk) {
                $lastError = (string)($result['error'] ?? 'HTTP ' . $status);
                if ($status === 416) {
                    // Range not satisfiable: the server thinks the file is
                    // already complete. Validate what we have.
                    break;
                }
                if ($status !== 0 && $status >= 400 && $status < 500 && $status !== 408 && $status !== 429) {
                    // A definitive client error will not change on retry.
                    break;
                }
                continue;
            }
            if (!is_file($tmp)) {
                $lastError = 'no data written';
                $resumeFrom = 0;
                continue;
            }
            $size = (int)filesize($tmp);
            if ($partialOk && $size < $expectedSize) {
                // The connection ended early; keep the bytes and resume.
                $lastError = 'transfer ended early';
                $resumeFrom = $size;
                continue;
            }
            break;
        }

        if (!is_file($tmp)) {
            @unlink($meta);
            throw new \RuntimeException('Unable to download package.' . ($lastError !== '' ? ' (' . $lastError . ')' : ''));
        }
        $actualSize = (int)filesize($tmp);
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            // Keep the partial for a resumed retry; only a provably wrong file
            // (larger than declared) is discarded.
            if ($actualSize > $expectedSize) {
                @unlink($tmp);
                @unlink($meta);
            }
            throw new \RuntimeException('Downloaded package size mismatch (' . $actualSize . ' of ' . $expectedSize . ' bytes).');
        }
        if ($expectedSha !== '' && (hash_file('sha256', $tmp) ?: '') !== $expectedSha) {
            // The size matched but the bytes did not: the partial is unusable,
            // and keeping it could only repeat the same checksum failure.
            @unlink($tmp);
            @unlink($meta);
            throw new \RuntimeException('Downloaded package sha256 mismatch.');
        }
        if (!@rename($tmp, $final)) {
            copy($tmp, $final);
            @unlink($tmp);
        }
        @unlink($meta);
        return $final;
    }
}
