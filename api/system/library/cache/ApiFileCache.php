<?php
declare(strict_types=1);

namespace Api\System\Library\Cache;

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;

final class ApiFileCache
{
    private const CACHE_KEY_PREFIX = 'api_cache_';
    private const VERSION_FILE_SUFFIX = '.version';
    private const TMP_SUFFIX = '.tmp';

    private string $basePath;
    private bool $enabled;
    private int $defaultTtl;
    private bool $debug;
    private ?JsonLogger $logger = null;

    public function __construct(Config $config, ?JsonLogger $logger = null)
    {
        $storageCache = (string)($config->get('default.storage.cache', ''));
        $this->basePath = $storageCache !== ''
            ? rtrim($storageCache, '/') . '/api'
            : dirname(__DIR__, 4) . '/storage/cache/api';

        $this->enabled = (bool)($config->get('default.api_file_cache.enabled', false));
        $this->defaultTtl = (int)($config->get('default.api_file_cache.default_ttl', 60));
        $this->debug = (bool)($config->get('default.api_file_cache.debug', false));
        $this->logger = $logger;

        if ($this->enabled && !is_dir($this->basePath)) {
            $this->ensureDirectoryExists($this->basePath);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
    }

    public function setDefaultTtl(int $ttl): void
    {
        $this->defaultTtl = max(1, $ttl);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /** @return array{fileCount: int, totalSizeBytes: int, basePath: string} */
    public function stats(): array
    {
        $fileCount = 0;
        $totalSizeBytes = 0;

        $pattern = $this->basePath . '/' . self::CACHE_KEY_PREFIX . '*.json';
        $files = @glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                $fileCount++;
                $size = @filesize($file);
                if ($size !== false) {
                    $totalSizeBytes += $size;
                }
            }
        }

        return [
            'fileCount' => $fileCount,
            'totalSizeBytes' => $totalSizeBytes,
            'basePath' => $this->basePath,
        ];
    }

    public function clearAll(): void
    {
        $files = @glob($this->basePath . '/' . self::CACHE_KEY_PREFIX . '*.json');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        $versionFiles = @glob($this->basePath . '/*' . self::VERSION_FILE_SUFFIX);
        if ($versionFiles !== false) {
            foreach ($versionFiles as $file) {
                @unlink($file);
            }
        }
        $this->log('cache_clear_all', []);
    }

    public function remember(string $namespace, string $key, int $ttl, callable $callback): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $version = $this->getNamespaceVersion($namespace);
        $cacheKey = $this->hashKey($namespace, $key, $version);
        $cached = $this->read($cacheKey, $ttl);

        if ($cached !== null) {
            $this->log('cache_hit', ['namespace' => $namespace, 'key' => $key, 'version' => $version]);
            return $cached;
        }

        $this->log('cache_miss', ['namespace' => $namespace, 'key' => $key, 'version' => $version]);

        try {
            $data = $callback();
        } catch (\Throwable $e) {
            $this->log('callback_error', ['namespace' => $namespace, 'key' => $key, 'error' => $e->getMessage()]);
            throw $e;
        }

        $this->write($cacheKey, $data);
        $this->log('cache_set', ['namespace' => $namespace, 'key' => $key, 'version' => $version]);

        return $data;
    }

    public function invalidateNamespace(string $namespace): void
    {
        if (!$this->enabled) {
            return;
        }

        $versionFile = $this->versionFilePath($namespace);
        $newVersion = (string)(int)(microtime(true) * 1000);

        try {
            $tmp = $versionFile . self::TMP_SUFFIX;
            $written = @file_put_contents($tmp, $newVersion, LOCK_EX);
            if ($written === false) {
                $this->log('version_write_failed', ['namespace' => $namespace, 'file' => $versionFile]);
                return;
            }
            @rename($tmp, $versionFile);
            $this->log('cache_invalidate', ['namespace' => $namespace, 'version' => $newVersion]);
        } catch (\Throwable $e) {
            $this->log('version_write_error', [
                'namespace' => $namespace,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function clear(string $namespace = ''): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($namespace !== '') {
            $versionFile = $this->versionFilePath($namespace);
            @unlink($versionFile);
            $prefix = self::CACHE_KEY_PREFIX . $namespace . '_';
            $this->deleteFilesByPrefix($prefix);
            $this->log('cache_clear', ['namespace' => $namespace]);
            return;
        }

        $files = @glob($this->basePath . '/' . self::CACHE_KEY_PREFIX . '*');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        $versionFiles = @glob($this->basePath . '/*' . self::VERSION_FILE_SUFFIX);
        if ($versionFiles !== false) {
            foreach ($versionFiles as $file) {
                @unlink($file);
            }
        }
        $this->log('cache_clear_all', []);
    }

    private function getNamespaceVersion(string $namespace): string
    {
        $versionFile = $this->versionFilePath($namespace);
        if (!is_file($versionFile)) {
            return '0';
        }

        $version = @file_get_contents($versionFile);
        if ($version === false || trim($version) === '') {
            return '0';
        }

        return trim($version);
    }

    private function hashKey(string $namespace, string $key, string $version): string
    {
        return self::CACHE_KEY_PREFIX . $namespace . '_' . sha1($namespace . '|' . $key . '|' . $version);
    }

    private function filePath(string $cacheKey): string
    {
        return $this->basePath . '/' . $cacheKey . '.json';
    }

    private function versionFilePath(string $namespace): string
    {
        return $this->basePath . '/' . self::CACHE_KEY_PREFIX . $namespace . self::VERSION_FILE_SUFFIX;
    }

    private function read(string $cacheKey, int $ttl): mixed
    {
        $path = $this->filePath($cacheKey);
        if (!is_file($path)) {
            return null;
        }

        $stat = @stat($path);
        if ($stat === false) {
            return null;
        }

        $age = time() - $stat['mtime'];
        if ($age > $ttl) {
            @unlink($path);
            $this->log('cache_expired', ['cacheKey' => $cacheKey, 'age' => $age, 'ttl' => $ttl]);
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !array_key_exists('data', $decoded)) {
            @unlink($path);
            $this->log('cache_corrupted', ['cacheKey' => $cacheKey]);
            return null;
        }

        return $decoded['data'];
    }

    private function write(string $cacheKey, mixed $data): void
    {
        $path = $this->filePath($cacheKey);
        $payload = json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $this->log('cache_encode_failed', ['cacheKey' => $cacheKey]);
            return;
        }

        try {
            $tmp = $path . self::TMP_SUFFIX;
            $written = @file_put_contents($tmp, $payload, LOCK_EX);
            if ($written === false) {
                $this->log('cache_write_failed', ['cacheKey' => $cacheKey]);
                return;
            }
            @rename($tmp, $path);
        } catch (\Throwable $e) {
            $this->log('cache_write_error', ['cacheKey' => $cacheKey, 'error' => $e->getMessage()]);
        }
    }

    private function deleteFilesByPrefix(string $prefix): void
    {
        $pattern = $this->basePath . '/' . $prefix . '*.json';
        $files = @glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            try {
                @mkdir($path, 0775, true);
            } catch (\Throwable $e) {
                $this->log('directory_create_error', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }
    }

    private function log(string $event, array $context = []): void
    {
        if (!$this->debug || $this->logger === null) {
            return;
        }

        $context['event'] = $event;
        $context['type'] = 'api_cache';

        try {
            $this->logger->info('api_cache', $context);
        } catch (\Throwable $e) {
            error_log('ApiFileCache log error: ' . $e->getMessage());
        }
    }
}
