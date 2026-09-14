<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Support\AppLog;
use RuntimeException;

/**
 * HTTP client for the official TropaTT module marketplace (marketplace.tropatt.com).
 *
 * The marketplace owns the distribution catalog: it publishes module metadata,
 * their releases, and hands out short-lived signed download URLs. Nothing this
 * class returns is trusted: it is remote data, so callers cast/normalize every
 * field and installation still goes through ModuleRemoteInstaller (manifest
 * signature, AST code validation, archive magic bytes, zip-slip checks).
 *
 * Configuration lives in `config/update.php` under the `marketplace` key
 * (TROPATT_MARKETPLACE_* env vars), so the base URL never comes from a request
 * parameter and cannot be used as an SSRF pivot by an admin session.
 */
final class ModuleMarketplaceClient
{
    /**
     * Module code shape: exactly "<vendor>.<module>", the same rule
     * PluginManager::isValidName() and the installer enforce, so a code accepted
     * here is one the installer can place under modules/<code>.
     */
    private const CODE_PATTERN = '/^[a-z0-9]+\.[a-z0-9-]+$/';

    /**
     * Catalogue product types that are not installable modules.
     *
     * The marketplace also lists virtual products (currently the donation). They
     * have prices and orders but no package, so they must never reach the
     * "installable modules" list of a CRM installation. The marketplace already
     * filters them out of /api/v1/catalog; this set is the client-side guard for
     * a stale catalogue cache or an older marketplace build.
     */
    private const VIRTUAL_PRODUCT_TYPES = ['donation', 'virtual', 'service'];

    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly string $cacheDir,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? true);
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string)($this->config['base_url'] ?? '')), '/');
    }

    /**
     * Marketplace health snapshot used by the admin UI to distinguish
     * "catalog is empty" from "marketplace is unreachable / not configured".
     *
     * @return array{enabled:bool,configured:bool,reachable:bool,error:string,total:int,version:string}
     */
    public function status(): array
    {
        $status = [
            'enabled' => $this->isEnabled(),
            'configured' => $this->baseUrl() !== '',
            'reachable' => false,
            'error' => '',
            'total' => 0,
            'version' => '',
        ];

        if (!$status['enabled'] || !$status['configured']) {
            return $status;
        }

        try {
            $ping = $this->get('/api/v1/ping');
            $status['reachable'] = strtolower((string)($ping['status'] ?? '')) === 'ok';
            $status['version'] = (string)($ping['version'] ?? '');
            if (!$status['reachable']) {
                $status['error'] = 'unexpected_ping_response';
            }
        } catch (RuntimeException $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Browse the published catalog.
     *
     * @param array{q?:string,category?:string,page?:int,limit?:int,refresh?:bool} $filters
     * @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function catalog(array $filters = []): array
    {
        $query = [
            'page' => max(1, (int)($filters['page'] ?? 1)),
            'limit' => min(48, max(1, (int)($filters['limit'] ?? 12))),
        ];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $query['q'] = mb_substr($q, 0, 120);
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '' && $category !== 'all') {
            // Category slugs come from the marketplace itself; keep the charset narrow.
            if (preg_match('/^[a-z0-9_-]{1,40}$/', $category) !== 1) {
                throw new RuntimeException('Invalid marketplace category');
            }
            $query['category'] = $category;
        }

        $coreVersion = trim((string)($filters['core_version'] ?? ''));
        if ($coreVersion !== '') {
            $query['core_version'] = $coreVersion;
        }

        // `refresh` (the admin's refresh button) bypasses the catalog cache so a
        // just-published release is visible immediately instead of after the TTL.
        $response = $this->cachedGet('/api/v1/catalog', $query, !empty($filters['refresh']));
        $items = is_array($response['data'] ?? null) ? $response['data'] : [];
        $meta = is_array($response['meta'] ?? null) ? $response['meta'] : [];

        $items = array_values(array_filter($items, 'is_array'));
        $before = count($items);
        $items = array_values(array_filter($items, [$this, 'isInstallableProduct']));
        $dropped = $before - count($items);

        return [
            'items' => array_values(array_map([$this, 'normalizeModuleSummary'], $items)),
            'meta' => [
                'page' => max(1, (int)($meta['page'] ?? $query['page'])),
                'limit' => max(1, (int)($meta['limit'] ?? $query['limit'])),
                // Virtual products were filtered out of this page, so the count
                // the UI paginates on has to shrink by the same amount.
                'total' => max(0, (int)($meta['total'] ?? $before) - $dropped),
                'pages' => max(1, (int)($meta['pages'] ?? 1)),
            ],
        ];
    }

    /**
     * Is this raw catalogue entry an installable module rather than a virtual
     * product (a donation)?
     *
     * @param mixed $raw
     */
    public function isInstallableProduct($raw): bool
    {
        if (!is_array($raw)) {
            return false;
        }

        if (($raw['installable'] ?? true) === false) {
            return false;
        }

        if ((int)($raw['is_donation'] ?? 0) === 1) {
            return false;
        }

        $type = strtolower(trim((string)($raw['product_type'] ?? 'module')));

        return $type === '' || !in_array($type, self::VIRTUAL_PRODUCT_TYPES, true);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function categories(): array
    {
        $response = $this->cachedGet('/api/v1/categories');
        $items = is_array($response['data'] ?? null) ? $response['data'] : [];

        $categories = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = (string)($item['slug'] ?? '');
            if ($slug === '' || preg_match('/^[a-z0-9_-]{1,40}$/', $slug) !== 1) {
                continue;
            }
            $categories[] = [
                'slug' => $slug,
                'title_ru' => (string)($item['title_ru'] ?? $slug),
                'title_en' => (string)($item['title_en'] ?? $slug),
                'title_zh' => (string)($item['title_zh'] ?? $slug),
                'icon' => (string)($item['icon'] ?? ''),
                'description_ru' => (string)($item['description_ru'] ?? ''),
            ];
        }

        return $categories;
    }

    /**
     * Full detail for one module, including its published releases.
     *
     * @return array<string,mixed>
     */
    public function module(string $fullCode): array
    {
        $fullCode = $this->assertCode($fullCode);
        $response = $this->cachedGet('/api/v1/modules/' . rawurlencode($fullCode));
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        if ($data === []) {
            throw new RuntimeException('Marketplace module not found');
        }

        $detail = $this->normalizeModuleSummary($data);
        $releases = [];
        foreach ((array)($data['releases'] ?? []) as $release) {
            if (!is_array($release)) {
                continue;
            }
            $releases[] = [
                'version' => (string)($release['version'] ?? ''),
                'changelog_markdown' => (string)($release['changelog_markdown'] ?? ''),
                'min_core_version' => (string)($release['min_core_version'] ?? ''),
                'min_php_version' => (string)($release['min_php_version'] ?? ''),
                'released_at' => (string)($release['released_at'] ?? ''),
                'package_file_size' => max(0, (int)($release['package_file_size'] ?? 0)),
                'sha256_checksum' => strtolower((string)($release['sha256_checksum'] ?? '')),
            ];
        }
        $detail['releases'] = $releases;
        $detail['reviews_count'] = count((array)($data['reviews'] ?? []));

        return $detail;
    }

    /**
     * Ask the marketplace for a short-lived signed download URL for the latest
     * published release of a module.
     *
     * @return array{full_code:string,version:string,sha256:string,download_url:string,expires_in:int}
     */
    public function requestInstall(string $fullCode, string $coreVersion, string $instanceDomain): array
    {
        $fullCode = $this->assertCode($fullCode);

        $response = $this->post('/api/v1/marketplace/install-request', [
            'full_code' => $fullCode,
            'core_version' => mb_substr($coreVersion, 0, 32),
            'instance_domain' => mb_substr($instanceDomain, 0, 190),
        ]);

        $url = trim((string)($response['download_url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('Marketplace did not return a download URL');
        }
        $this->assertDownloadUrl($url);

        return [
            'full_code' => (string)($response['module_name'] ?? $fullCode),
            'version' => (string)($response['version'] ?? ''),
            'sha256' => strtolower((string)($response['sha256'] ?? '')),
            'download_url' => $url,
            'expires_in' => max(0, (int)($response['expires_in'] ?? 0)),
        ];
    }

    /**
     * Installation domain reported to the marketplace (download metrics only).
     * Same normalization the update center uses, so both channels report the
     * same host for one installation.
     */
    public function currentDomain(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '') {
            $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
            if ($origin !== '') {
                $host = (string)(parse_url($origin, PHP_URL_HOST) ?: '');
            }
        }
        $host = strtolower($host);
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');
            return $end === false ? '' : substr($host, 1, $end - 1);
        }
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return preg_match('/^[a-z0-9.-]+$/', $host) === 1 ? trim($host, '.') : '';
    }

    /**
     * The download URL is signed by the marketplace and points back at it. It is
     * validated against the configured host so a compromised/typo'd catalog
     * response cannot redirect the installer to an arbitrary origin.
     */
    private function assertDownloadUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid marketplace download URL');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Invalid marketplace download URL scheme');
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        $expected = strtolower((string)(parse_url($this->baseUrl(), PHP_URL_HOST) ?: ''));
        if ($expected === '' || $host === '' || $host !== $expected) {
            throw new RuntimeException('Marketplace download URL host does not match the configured marketplace');
        }
    }

    /**
     * Whether a string is a module code the marketplace can publish and the
     * installer can place under modules/<code>.
     *
     * Public so a caller can answer with a proper "invalid parameter" before a
     * filesystem path or an outbound request is built from the value; the
     * pattern is the module-code shape used by PluginManager::isValidName() and
     * the installer, so a code accepted here is one the installer accepts.
     */
    public static function isValidCode(string $fullCode): bool
    {
        $fullCode = trim($fullCode);

        return $fullCode !== ''
            && strlen($fullCode) <= 64
            && !str_contains($fullCode, '..')
            && !str_contains($fullCode, '/')
            && !str_contains($fullCode, '\\')
            && preg_match(self::CODE_PATTERN, $fullCode) === 1;
    }

    private function assertCode(string $fullCode): string
    {
        $fullCode = trim($fullCode);
        if (!self::isValidCode($fullCode)) {
            throw new RuntimeException('Invalid marketplace module code');
        }

        return $fullCode;
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalizeModuleSummary(array $raw): array
    {
        return [
            'full_code' => (string)($raw['full_code'] ?? ''),
            'module_name' => (string)($raw['module_name'] ?? ''),
            'vendor_name' => (string)($raw['vendor_name'] ?? ''),
            'title' => (string)($raw['title'] ?? ($raw['full_code'] ?? '')),
            'summary' => (string)($raw['summary'] ?? ''),
            'category_slug' => (string)($raw['category_slug'] ?? ''),
            'icon_url' => $this->safeHttpUrl((string)($raw['icon_url'] ?? '')),
            'price_model' => (string)($raw['price_model'] ?? 'free'),
            'price_minor' => max(0, (int)($raw['price_minor'] ?? 0)),
            'currency' => (string)($raw['currency'] ?? ''),
            'min_core_version' => (string)($raw['min_core_version'] ?? ''),
            'total_downloads' => max(0, (int)($raw['total_downloads'] ?? 0)),
            'rating_avg' => max(0.0, (float)($raw['rating_avg'] ?? 0)),
            'rating_count' => max(0, (int)($raw['rating_count'] ?? 0)),
            'author' => (string)($raw['author'] ?? ''),
            'author_url' => $this->safeHttpUrl((string)($raw['author_url'] ?? '')),
            'latest_version' => (string)($raw['latest_version'] ?? ($raw['version'] ?? '')),
            'product_type' => strtolower(trim((string)($raw['product_type'] ?? 'module'))) ?: 'module',
        ];
    }

    private function safeHttpUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    /**
     * @param array<string,scalar> $query
     * @return array<string,mixed>
     */
    private function cachedGet(string $path, array $query = [], bool $refresh = false): array
    {
        $ttl = max(0, (int)($this->config['catalog_cache_ttl'] ?? 300));
        $cacheFile = $ttl > 0 ? $this->cacheFile($path, $query) : '';
        if (!$refresh && $cacheFile !== '' && is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = $this->get($path, $query);

        if ($cacheFile !== '') {
            $this->writeCache($cacheFile, $response);
        }

        return $response;
    }

    /**
     * @param array<string,scalar> $query
     * @return array<string,mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $url = $this->url($path, $query);
        return $this->request('GET', $url, null);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $this->url($path), $body);
    }

    /**
     * @param array<string,scalar> $query
     */
    private function url(string $path, array $query = []): string
    {
        $base = $this->baseUrl();
        if ($base === '') {
            throw new RuntimeException('Module marketplace URL is not configured');
        }
        if (!$this->isEnabled()) {
            throw new RuntimeException('Module marketplace integration is disabled');
        }

        $url = $base . (str_starts_with($path, '/') ? $path : '/' . $path);

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, ?array $body): array
    {
        $timeout = max(1, (int)($this->config['timeout_sec'] ?? 8));
        $payload = $body === null ? null : (string)json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $raw = false;
        $status = 0;
        $lastError = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                $options = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                    CURLOPT_TIMEOUT => $timeout,
                    // The catalog is a fixed trusted origin from config; do not
                    // follow redirects off it.
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_USERAGENT => 'TropaTT-CRM-Marketplace/1.0',
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ];
                if ($method === 'POST') {
                    $options[CURLOPT_POST] = true;
                    $options[CURLOPT_POSTFIELDS] = $payload ?? '';
                    $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                }
                curl_setopt_array($ch, $options);

                $raw = curl_exec($ch);
                if ($raw === false) {
                    $lastError = (string)curl_error($ch);
                } else {
                    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                }
                if (PHP_VERSION_ID < 80000) {
                    curl_close($ch);
                }
            }
        }

        if ($raw === false && $status === 0) {
            // Shared hosting frequently disables allow_url_fopen; curl covers
            // almost everything, and this is the last-resort fallback.
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", array_filter([
                        'Accept: application/json',
                        $method === 'POST' ? 'Content-Type: application/json' : '',
                    ])),
                    'content' => $payload ?? '',
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                $error = error_get_last();
                $lastError = (string)($error['message'] ?? 'request failed');
                $status = 0;
            } else {
                $statusLine = $http_response_header[0] ?? '';
                $status = preg_match('#\s(\d{3})\s#', $statusLine, $m) === 1 ? (int)$m[1] : 200;
            }
        }

        if ($raw === false) {
            AppLog::error('[ModuleMarketplaceClient] ' . $method . ' ' . $url . ' failed: ' . $lastError);
            throw new RuntimeException('Module marketplace is unreachable');
        }

        if ($status < 200 || $status >= 300) {
            AppLog::error('[ModuleMarketplaceClient] ' . $method . ' ' . $url . ' returned HTTP ' . $status);
            throw new RuntimeException('Module marketplace returned HTTP ' . $status);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Module marketplace returned an invalid response');
        }

        if (($decoded['success'] ?? true) === false) {
            throw new RuntimeException((string)($decoded['error'] ?? 'Marketplace request failed'));
        }

        return $decoded;
    }

    /**
     * @param array<string,scalar> $query
     */
    private function cacheFile(string $path, array $query): string
    {
        if ($this->cacheDir === '') {
            return '';
        }
        ksort($query);
        $key = $path . '?' . http_build_query($query);

        return $this->cacheDir . '/' . sha1($key) . '.json';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function writeCache(string $cacheFile, array $data): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        // Atomic write: a partially written cache file would poison every later
        // catalog render (same failure mode the update center hit in production).
        $tmp = $cacheFile . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            @unlink($tmp);
            return;
        }
        if (!@rename($tmp, $cacheFile)) {
            @unlink($tmp);
        }
    }
}
