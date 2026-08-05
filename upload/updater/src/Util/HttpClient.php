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
     * @param array{method?:string,body?:string,headers?:array<string,string>,timeout?:int,stream_to?:string,follow?:bool,no_body?:bool} $options
     * @return array{ok:bool,status:int,headers:array<int,string>,body:string|false,error:string,bytes:int|null}
     */
    public static function request(string $url, array $options = []): array
    {
        $method = strtoupper((string)($options['method'] ?? 'GET'));
        $timeout = max(1, (int)($options['timeout'] ?? 30));
        $streamTo = (string)($options['stream_to'] ?? '');
        $noBody = (bool)($options['no_body'] ?? false);
        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];

        if (function_exists('curl_init')) {
            return self::requestViaCurl($url, $method, $headers, $options, $timeout, $streamTo, $noBody);
        }
        return self::requestViaStreams($url, $method, $headers, $options, $timeout, $streamTo, $noBody);
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
    private static function requestViaCurl(string $url, string $method, array $headers, array $options, int $timeout, string $streamTo, bool $noBody): array
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
            CURLOPT_RETURNTRANSFER => true,
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

        $fileHandle = null;
        if ($streamTo !== '') {
            $fileHandle = @fopen($streamTo, 'wb');
            if ($fileHandle === false) {
                // PHP 8.0+ frees handles automatically; curl_close() is deprecated on 8.5.
                if (PHP_VERSION_ID < 80000) {
                    curl_close($ch);
                }
                return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => false, 'error' => 'unable to open stream target', 'bytes' => null];
            }
            $curlOpts[CURLOPT_FILE] = $fileHandle;
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

        if ($output === false) {
            if ($streamTo !== '') {
                @unlink($streamTo);
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
    private static function requestViaStreams(string $url, string $method, array $headers, array $options, int $timeout, string $streamTo, bool $noBody): array
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
            $local = @fopen($streamTo, 'wb');
            if ($local === false) {
                fclose($remote);
                return ['ok' => false, 'status' => $status, 'headers' => [], 'body' => false, 'error' => 'unable to open stream target', 'bytes' => null];
            }
            $copied = @stream_copy_to_stream($remote, $local);
            fclose($remote);
            fclose($local);
            if ($copied === false) {
                @unlink($streamTo);
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

        $body = @file_get_contents($url, false, stream_context_create($contextOptions));
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
