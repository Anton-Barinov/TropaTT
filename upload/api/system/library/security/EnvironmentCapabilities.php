<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

/**
 * SEC-TASK-00: Single point of truth for runtime environment capabilities.
 *
 * All methods answer "what can this environment do?" at runtime.
 * Results are cached per-request (static), not persisted.
 *
 * Key principle: when uncertain, assume the capability is absent (safe default).
 */
final class EnvironmentCapabilities
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    public function hasFinfo(): bool
    {
        return self::$cache['hasFinfo'] ??= function_exists('finfo_open');
    }

    public function hasCurl(): bool
    {
        return self::$cache['hasCurl'] ??= function_exists('curl_init');
    }

    public function hasZip(): bool
    {
        return self::$cache['hasZip'] ??= class_exists('ZipArchive');
    }

    public function hasDns(): bool
    {
        return self::$cache['hasDns'] ??= function_exists('dns_get_record');
    }

    /**
     * Normalized web server identification.
     *
     * Returns one of: 'apache', 'nginx', 'litespeed', 'unknown'.
     * LiteSpeed is checked BEFORE Apache because LiteSpeed often
     * reports a string containing both words and supports .htaccess.
     */
    public function serverSoftware(): string
    {
        if (isset(self::$cache['serverSoftware'])) {
            return self::$cache['serverSoftware'];
        }

        $software = strtolower((string)($_SERVER['SERVER_SOFTWARE'] ?? ''));

        if ($software === '') {
            return self::$cache['serverSoftware'] = 'unknown';
        }

        // Check LiteSpeed first — it often contains 'apache' in its string
        if (str_contains($software, 'litespeed')) {
            return self::$cache['serverSoftware'] = 'litespeed';
        }

        if (str_contains($software, 'apache')) {
            return self::$cache['serverSoftware'] = 'apache';
        }

        if (str_contains($software, 'nginx')) {
            return self::$cache['serverSoftware'] = 'nginx';
        }

        return self::$cache['serverSoftware'] = 'unknown';
    }

    /**
     * Whether .htaccess files are expected to be honoured by the web server.
     *
     * Only Apache and LiteSpeed support .htaccess.
     * For 'unknown' we return false — safe default: we don't assume
     * protection we cannot verify.
     */
    public function htaccessSupported(): bool
    {
        if (isset(self::$cache['htaccessSupported'])) {
            return self::$cache['htaccessSupported'];
        }

        $server = $this->serverSoftware();
        return self::$cache['htaccessSupported'] = ($server === 'apache' || $server === 'litespeed');
    }

    /**
     * Whether the storage base directory is outside the DocumentRoot.
     *
     * Uses realpath() comparison via str_starts_with.
     * Returns false (unsafe assumption) if DOCUMENT_ROOT is empty
     * or realpath fails.
     */
    public function storageOutsideDocroot(): bool
    {
        if (isset(self::$cache['storageOutsideDocroot'])) {
            return self::$cache['storageOutsideDocroot'];
        }

        $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot === '') {
            return self::$cache['storageOutsideDocroot'] = false;
        }

        $docRootReal = realpath($docRoot);
        if ($docRootReal === false) {
            return self::$cache['storageOutsideDocroot'] = false;
        }

        // Storage base: prefer CRM_STORAGE_BASE, fall back to default
        $storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: '');
        if ($storageBase === '') {
            // Default from default.php: dirname(__DIR__, 2) . '/storage_api'
            // At runtime we reconstruct the same logic:
            // __DIR__ = api/system/library/security
            // dirname(__DIR__, 4) = api/  →  api/../.. is the project root
            $storageBase = dirname(__DIR__, 4) . '/storage_api';
        }

        $storageReal = realpath($storageBase);
        if ($storageReal === false) {
            // If the directory doesn't exist yet, check the parent
            $storageParent = realpath(dirname($storageBase));
            if ($storageParent === false) {
                return self::$cache['storageOutsideDocroot'] = false;
            }
            // Assume the storage will be created where specified
            $storageReal = rtrim($storageParent, '/') . '/' . basename($storageBase);
        }

        $docRootReal = rtrim($docRootReal, '/') . '/';
        $storageReal = rtrim($storageReal, '/') . '/';

        return self::$cache['storageOutsideDocroot'] = !str_starts_with($storageReal, $docRootReal);
    }

    /**
     * Determine if the current request is over HTTPS.
     *
     * Checks (in order):
     * 1. $_SERVER['HTTPS'] not empty and not 'off'
     * 2. $_SERVER['SERVER_PORT'] === '443'
     * 3. $_SERVER['REQUEST_SCHEME'] === 'https'
     * 4. Placeholder for trusted proxy X-Forwarded-Proto (TASK-05)
     *
     * NOTE: X-Forwarded-* headers are only considered when REMOTE_ADDR
     * is in the trusted_proxies list (see TASK-05). Currently not implemented.
     */
    public function isHttps(): bool
    {
        if (isset(self::$cache['isHttps'])) {
            return self::$cache['isHttps'];
        }

        // 1. Standard HTTPS flag
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower($https) !== 'off') {
            return self::$cache['isHttps'] = true;
        }

        // 2. Port 443
        if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return self::$cache['isHttps'] = true;
        }

        // 3. Request scheme
        if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
            return self::$cache['isHttps'] = true;
        }

        // 4. Placeholder for trusted proxy check (TASK-05):
        // if (trustedProxy && X-Forwarded-Proto === 'https') return true;

        return self::$cache['isHttps'] = false;
    }

    /**
     * Collect all warnings about the environment.
     *
     * Returns an array of warning objects:
     * ['code' => 'ENV_...', 'severity' => 'critical'|'warning', 'message_key' => 'health/messages.env_...']
     */
    public function collectWarnings(): array
    {
        $warnings = [];

        // Critical: storage inside docroot AND no htaccess protection
        if (!$this->htaccessSupported() && !$this->storageOutsideDocroot()) {
            $warnings[] = [
                'code' => 'ENV_STORAGE_EXPOSED',
                'severity' => 'critical',
                'message_key' => 'health/messages.env_storage_exposed',
            ];
        }

        // Warning: no finfo — file type detection limited
        if (!$this->hasFinfo()) {
            $warnings[] = [
                'code' => 'ENV_NO_FINFO',
                'severity' => 'warning',
                'message_key' => 'health/messages.env_no_finfo',
            ];
        }

        // Warning: no DNS resolution — SSRF filtering works in limited mode
        if (!$this->hasDns()) {
            $warnings[] = [
                'code' => 'ENV_NO_DNS',
                'severity' => 'warning',
                'message_key' => 'health/messages.env_no_dns',
            ];
        }

        // Warning: no curl — outgoing requests may use fallback
        if (!$this->hasCurl()) {
            $warnings[] = [
                'code' => 'ENV_NO_CURL',
                'severity' => 'warning',
                'message_key' => 'health/messages.env_no_curl',
            ];
        }

        return $warnings;
    }

    /**
     * Return all capabilities as an array for API reporting.
     */
    public function toArray(): array
    {
        return [
            'server_software' => $this->serverSoftware(),
            'htaccess_supported' => $this->htaccessSupported(),
            'storage_outside_docroot' => $this->storageOutsideDocroot(),
            'has_finfo' => $this->hasFinfo(),
            'has_curl' => $this->hasCurl(),
            'has_zip' => $this->hasZip(),
            'has_dns' => $this->hasDns(),
            'is_https' => $this->isHttps(),
            'warnings' => $this->collectWarnings(),
        ];
    }

    /**
     * Reset cached values (useful for testing).
     */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
