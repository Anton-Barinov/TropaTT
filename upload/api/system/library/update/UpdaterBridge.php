<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

use Updater\Http\JsonResponse;
use Updater\UpdaterKernel;

/**
 * Executes local updater actions without making the installation call itself
 * over its public HTTP(S) address.
 *
 * Shared hosting commonly routes a request to the site's own public hostname
 * through a reverse proxy, WAF, NAT hairpin, or a different vhost. That can
 * time out even while the same URL works from an external client. The updater
 * is part of this application, so the normal path should stay in-process.
 */
final class UpdaterBridge
{
    /** @var array<string,bool> */
    private static array $autoloadRegistered = [];

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function dispatch(string $basePath, string $action, array $payload): array
    {
        self::registerUpdaterAutoloader($basePath);
        $kernel = new UpdaterKernel($basePath);
        $response = $kernel->dispatch($action, $payload);

        if (!$response instanceof JsonResponse) {
            throw new \RuntimeException('Updater returned an invalid response.');
        }

        return $response->payload;
    }

    /**
     * Resolve the optional legacy HTTP fallback URL.
     *
     * Only an explicit TROPATT_LOCAL_UPDATER_URL enables this fallback.
     * Normal local updater calls do not use this URL at all.
     *
     * @param array<string,mixed> $config
     */
    public static function publicBaseUrl(array $config): string
    {
        $configured = trim((string)($config['local_updater_url'] ?? ''));
        return $configured !== '' ? rtrim($configured, '/') : '';
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function dispatchHttpFallback(string $action, array $payload, array $config): array
    {
        $baseUrl = self::publicBaseUrl($config);
        if ($baseUrl === '') {
            return [
                'success' => false,
                'code' => 'UPDATER_LOCAL_DISPATCH_FAILED',
                'message' => 'The local updater could not be started in-process, and no explicit HTTP fallback is configured.',
            ];
        }

        $url = $baseUrl . '/updater/index.php?action=' . rawurlencode($action);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $timeout = max(5, (int)($config['timeouts']['apply_step'] ?? 60));
        $response = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                $response = curl_exec($ch);
                if (PHP_VERSION_ID < 80000) {
                    curl_close($ch);
                }
            }
        }

        if ($response === false || $response === '') {
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\\r\\n",
                    'content' => $body,
                    'ignore_errors' => true,
                    'timeout' => $timeout,
                ],
            ]));
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : [
            'success' => false,
            'code' => 'UPDATER_INVALID_RESPONSE',
            'message' => 'The updater fallback returned an invalid response.',
        ];
    }

    private static function registerUpdaterAutoloader(string $basePath): void
    {
        $basePath = rtrim($basePath, '/\\');
        if (isset(self::$autoloadRegistered[$basePath])) {
            return;
        }

        $updaterSource = $basePath . '/updater/src';
        spl_autoload_register(static function (string $class) use ($updaterSource): void {
            $prefix = 'Updater\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $path = $updaterSource . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
        self::$autoloadRegistered[$basePath] = true;
    }
}
