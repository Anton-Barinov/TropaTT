<?php
declare(strict_types=1);

namespace Updater\Util;

/**
 * Tiny HTTP client used by the updater (update-center API calls, package
 * download, HEAD checks).
 *
 * cURL is preferred: it is enabled on virtually every shared-hosting PHP build,
 * while allow_url_fopen (needed by file_get_contents()/fopen() URL wrappers) is
 * frequently disabled by hosting security policies. The updater falls back to
 * stream wrappers so it still works when cURL is missing but allow_url_fopen
 * is on.
 */
final class HttpClient
{
    /**
     * Perform an HTTP request.
     *
     * @param array{method?:string,body?:string,headers?:array<string,string>,timeout?:int,stream_to?:string,follow?:bool,no_body?:bool,max_body_bytes?:int} $options
     * @return array{ok:bool,status:int,headers:array<int,string>,body:string|false,error:string,bytes:int|null}
     */
    public static function request(string $url, array $options = []): array
    {
        $method = strtoupper((string)($options['method'] ?? 'GET'));
        $timeout = max(1, (int)($options['timeout'] ?? 30));
        $streamTo = (string)($options['stream_to'] ?? '');
        $noBody = (bool)($options['no_body'] ?? false);
        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $maxBodyBytes = max(0, (int)($options['max_body_bytes'] ?? 0));
        // Resume an interrupted streamed download: ask for the rest of the file.
        // The caller keeps the partial file and passes its current size, so a
        // dropped connection costs one request, not the whole transfer.
        $rangeFrom = max(0, (int)($options['stream_range_from'] ?? 0));
        if ($rangeFrom > 0 && $streamTo !== '' && $method === 'GET') {
            $hasRange = false;
            foreach (array_keys($headers) as $name) {
                if (strtolower((string)$name) === 'range') {
                    $hasRange = true;
                    break;
                }
            }
            if (!$hasRange) {
                $headers['Range'] = 'bytes=' . $rangeFrom . '-';
            }
        }
        if ($maxBodyBytes > 0 && ($streamTo !== '' || $noBody)) {
            return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => false, 'error' => 'bounded response mode is incompatible with streaming', 'bytes' => null];
        }

        if (function_exists('curl_init')) {
            return self::requestViaCurl($url, $method, $headers, $options, $timeout, $streamTo, $noBody, $maxBodyBytes, $rangeFrom);
        }
        return self::requestViaStreams($url, $method, $headers, $options, $timeout, $streamTo, $noBody, $maxBodyBytes, $rangeFrom);
    }

    /**
     * HEAD-style check used by preflight (package URL reachability + size).
     *
     * @return array{status:int,content_length:int|null,content_type:string|null}
     */
    public static function head(string $url, int $timeout = 30): array
    {
        $result = self::request($url, ['method' => 'HEAD', 'timeout' => $timeout, 'no_body' => true]);
        $headers = is_array($result['headers'] ?? null) ? $result['headers'] : [];
        $length = self::headerValue($headers, 'content-length');
        $type = self::headerValue($headers, 'content-type');
        return [
            'status' => (int)($result['status'] ?? 0),
            'content_length' => is_numeric($length) ? (int)$length : null,
            'content_type' => is_string($type) && $type !== '' ? $type : null,
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function requestViaCurl(string $url, string $method, array $headers, array $options, int $timeout, string $streamTo, bool $noBody, int $maxBodyBytes, int $rangeFrom = 0): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => false, 'error' => 'curl_init failed', 'bytes' => null];
        }

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $curlOpts = [
            // Never combine RETURNTRANSFER with a destination file: cURL then
            // returns the body to PHP and does NOT write it to CURLOPT_FILE,
            // so a streamed download silently produced an empty/partial file.
            // When a destination or a bounded body is in play the body is
            // written by CURLOPT_FILE / CURLOPT_WRITEFUNCTION instead.
            CURLOPT_RETURNTRANSFER => $maxBodyBytes === 0 && $streamTo === '' && !$noBody,
            // Never capture headers into the same buffer as the body: binary
            // bodies (package ZIPs) can contain the header terminator sequence
            // (\r\n\r\n), which would corrupt the split and truncate the body.
            // Status/content-length/content-type are read from curl_getinfo()
            // instead, and the body is written straight to its destination.
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];

        $boundedBody = '';
        $bodyTooLarge = false;
        if ($maxBodyBytes > 0) {
            $curlOpts[CURLOPT_WRITEFUNCTION] = static function ($handle, string $chunk) use (&$boundedBody, &$bodyTooLarge, $maxBodyBytes): int {
                if (strlen($boundedBody) + strlen($chunk) > $maxBodyBytes) {
                    $bodyTooLarge = true;
                    return 0;
                }
                $boundedBody .= $chunk;
                return strlen($chunk);
            };
        }
        $fileHandle = null;
        if ($streamTo !== '') {
            // Append when resuming a partial download, otherwise truncate.
            $fileHandle = @fopen($streamTo, $rangeFrom > 0 ? 'ab' : 'wb');
            if ($fileHandle === false) {
                // PHP 8.0+ frees handles automatically; curl_close() is deprecated on 8.5.
                if (PHP_VERSION_ID < 80000) {
                    curl_close($ch);
                }
                return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => false, 'error' => 'unable to open stream target', 'bytes' => null];
            }
            // Write the body through an explicit callback instead of CURLOPT_FILE:
            // a short fwrite() (disk full, stream policy on a cheap host) must
            // abort the transfer and be reported, never silently truncate the
            // file. Returning the written length keeps cURL's contract.
            $curlOpts[CURLOPT_WRITEFUNCTION] = static function ($handle, string $chunk) use ($fileHandle): int {
                $length = strlen($chunk);
                $written = @fwrite($fileHandle, $chunk);
                if ($written === false || $written !== $length) {
                    return 0; // cURL aborts with a write error
                }
                return $length;
            };
        }

        if ($method === 'HEAD' || $noBody) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)($options['body'] ?? ''));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            $body = (string)($options['body'] ?? '');
            if ($body !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        curl_setopt_array($ch, $curlOpts);
        $output = curl_exec($ch);
        $error = (string)curl_error($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentLength = (float)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        if ($fileHandle !== null) {
            fclose($fileHandle);
        }
        // PHP 8.0+ frees handles automatically; curl_close() is deprecated on 8.5.
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        if ($bodyTooLarge) {
            return ['ok' => false, 'status' => $status, 'headers' => [], 'body' => false, 'error' => 'response body exceeds configured limit', 'bytes' => $maxBodyBytes];
        }
        if ($maxBodyBytes > 0 && $output === true) {
            $output = $boundedBody;
        }
        if ($output === false) {
            if ($streamTo !== '') {
                // Keep a partial transfer that made progress so the caller can
                // resume it (the whole point of a Range request). Only a file
                // that did not grow past its starting offset is removed, so a
                // connect failure does not throw away earlier work.
                clearstatcache(true, $streamTo);
                $current = is_file($streamTo) ? (int)filesize($streamTo) : 0;
                if ($current <= $rangeFrom) {
                    @unlink($streamTo);
                }
            }
            return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => false, 'error' => $error !== '' ? $error : 'curl error ' . $errno, 'bytes' => null];
        }

        $bytes = null;
        if ($streamTo !== '') {
            $bytes = is_file($streamTo) ? filesize($streamTo) : 0;
            $output = false;
        } elseif (is_string($output)) {
            $bytes = strlen($output);
        }

        return [
            'ok' => $status > 0 && $status < 400,
            'status' => $status,
            'headers' => [
                'HTTP/' . ($status > 0 ? $status : 0),
                $contentLength > 0 ? 'content-length: ' . (int)$contentLength : '',
                $contentType !== '' ? 'content-type: ' . $contentType : '',
            ],
            'body' => $output,
            'error' => '',
            'bytes' => $bytes,
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function requestViaStreams(string $url, string $method, array $headers, array $options, int $timeout, string $streamTo, bool $noBody, int $maxBodyBytes, int $rangeFrom = 0): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $headerBlock = implode("\r\n", $headerLines);
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => $headerBlock !== '' ? $headerBlock : '',
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contextOptions['http']['content'] = (string)($options['body'] ?? '');
        }

        if ($streamTo !== '' && $method === 'GET') {
            // Streaming download: copy in chunks so memory stays flat.
            $remote = @fopen($url, 'rb', false, stream_context_create($contextOptions));
            if ($remote === false) {
                return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => false, 'error' => 'unable to open remote', 'bytes' => null];
            }
            $status = self::statusFromHeaders($http_response_header ?? []);
            $local = @fopen($streamTo, $rangeFrom > 0 ? 'ab' : 'wb');
            if ($local === false) {
                fclose($remote);
                return ['ok' => false, 'status' => $status, 'headers' => [], 'body' => false, 'error' => 'unable to open stream target', 'bytes' => null];
            }
            $copied = @stream_copy_to_stream($remote, $local);
            fclose($remote);
            fclose($local);
            if ($copied === false) {
                // Same rule as the cURL path: keep bytes that did arrive so the
                // caller can resume instead of starting the transfer over.
                clearstatcache(true, $streamTo);
                $current = is_file($streamTo) ? (int)filesize($streamTo) : 0;
                if ($current <= $rangeFrom) {
                    @unlink($streamTo);
                }
                return ['ok' => false, 'status' => $status, 'headers' => [], 'body' => false, 'error' => 'stream copy failed', 'bytes' => null];
            }
            return [
                'ok' => $status > 0 && $status < 400,
                'status' => $status,
                'headers' => array_values(array_filter($http_response_header ?? [], static fn ($l): bool => is_string($l) && $l !== '')),
                'body' => false,
                'error' => '',
                'bytes' => (int)$copied,
            ];
        }

        if ($maxBodyBytes > 0) {
            $body = @file_get_contents($url, false, stream_context_create($contextOptions), 0, $maxBodyBytes + 1);
            if (is_string($body) && strlen($body) > $maxBodyBytes) {
                return ['ok' => false, 'status' => self::statusFromHeaders($http_response_header ?? []),
                    'headers' => array_values(array_filter($http_response_header ?? [], static fn ($l): bool => is_string($l) && $l !== '')),
                    'body' => false, 'error' => 'response body exceeds configured limit', 'bytes' => $maxBodyBytes];
            }
        } else {
            $body = @file_get_contents($url, false, stream_context_create($contextOptions));
        }
        if ($body === false) {
            $lastError = error_get_last();
            return [
                'ok' => false,
                'status' => self::statusFromHeaders($http_response_header ?? []),
                'headers' => array_values(array_filter($http_response_header ?? [], static fn ($l): bool => is_string($l) && $l !== '')),
                'body' => false,
                'error' => is_array($lastError) ? (string)($lastError['message'] ?? 'unknown') : 'unknown',
                'bytes' => null,
            ];
        }
        $status = self::statusFromHeaders($http_response_header ?? []);
        return [
            'ok' => $status > 0 && $status < 400,
            'status' => $status,
            'headers' => array_values(array_filter($http_response_header ?? [], static fn ($l): bool => is_string($l) && $l !== '')),
            'body' => $body,
            'error' => '',
            'bytes' => $noBody ? null : strlen($body),
        ];
    }

    /**
     * @param array<int,string> $headers
     */
    private static function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (is_string($header) && preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    /**
     * @param array<int,string> $headers
     */
    private static function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            $pos = strpos((string)$header, ':');
            if ($pos === false) {
                continue;
            }
            $key = strtolower(trim(substr((string)$header, 0, $pos)));
            if ($key === $name) {
                return trim(substr((string)$header, $pos + 1));
            }
        }
        return null;
    }
}
