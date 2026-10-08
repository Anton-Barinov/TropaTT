<?php
declare(strict_types=1);

namespace Web\System\Core;

/**
 * Owner-configured product branding: a custom CRM name plus a custom logo for
 * the sidebar and the login screen.
 *
 * Values live in the `settings` table (scope `system`) and are read directly
 * from MySQL once per request — the same pattern GanttController uses for its
 * page setting — because the web shell must not depend on the API being
 * reachable. Everything is validated again here: templates receive only a
 * sanitized name and a generated public URL, never a raw setting value or a
 * filesystem path.
 */
final class Branding
{
    private const NAME_SETTING = 'branding.name';
    private const LOGO_SETTING = 'branding.logo';
    private const NAME_MAX_LENGTH = 64;
    /** Application-generated names, see SettingController::uploadLogo(). */
    private const LOGO_FILE_PATTERN = '/^[a-f0-9]{32}\.[a-z0-9]{3,4}$/';
    /** Extension => MIME, allowlist for serving the stored file. */
    private const LOGO_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    private static ?self $instance = null;
    private static string $instanceBaseDir = '';

    private string $name = '';
    private string $logo = '';

    private function __construct(private readonly string $baseDir)
    {
        $this->load();
    }

    public static function get(string $baseDir): self
    {
        if (self::$instance === null || self::$instanceBaseDir !== $baseDir) {
            self::$instance = new self($baseDir);
            self::$instanceBaseDir = $baseDir;
        }

        return self::$instance;
    }

    /** Drop the per-request memo (tests, long-running CLI). */
    public static function reset(): void
    {
        self::$instance = null;
        self::$instanceBaseDir = '';
    }

    /**
     * Custom product name, or '' when the localized default (app.name) is used.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Public URL of the custom logo, or '' when the default product mark
     * should be used. Cache-busted by the stored file's mtime.
     */
    public function logoUrl(string $basePath = ''): string
    {
        if ($this->logo === '') {
            return '';
        }

        $file = $this->logoPath();
        $version = $file !== null ? (string)(int)@filemtime($file) : '0';

        return rtrim($basePath, '/') . '/index.php?route=branding-logo&v=' . $version;
    }

    /**
     * Absolute path of the stored logo file, or null when no valid logo is
     * configured. The resolved path is guaranteed to stay inside the branding
     * storage directory (realpath guard).
     */
    public function logoPath(): ?string
    {
        if ($this->logo === '') {
            return null;
        }

        $dir = $this->storageDir();
        if ($dir === null) {
            return null;
        }

        $path = $dir . '/' . $this->logo;
        $real = @realpath($path);
        $realDir = @realpath($dir);
        if ($real === false || $realDir === false || !is_file($real)) {
            return null;
        }
        if (!str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    public function logoMimeType(): string
    {
        $ext = strtolower(pathinfo($this->logo, PATHINFO_EXTENSION));

        return self::LOGO_MIME_TYPES[$ext] ?? 'application/octet-stream';
    }

    private function load(): void
    {
        if (!function_exists('crmWebApiDbConnect')) {
            return;
        }

        try {
            $pdo = crmWebApiDbConnect($this->baseDir);
            if ($pdo === null) {
                return;
            }

            $stmt = $pdo->prepare(
                'SELECT name, value FROM settings WHERE scope = ? AND name IN (?, ?) LIMIT 2'
            );
            $stmt->execute(['system', self::NAME_SETTING, self::LOGO_SETTING]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $decoded = json_decode((string)($row['value'] ?? ''), true);
                $value = is_string($decoded) ? $decoded : '';
                if ((string)($row['name'] ?? '') === self::NAME_SETTING) {
                    $this->name = self::sanitizeName($value);
                } elseif ((string)($row['name'] ?? '') === self::LOGO_SETTING) {
                    $this->logo = self::sanitizeLogo($value);
                }
            }
        } catch (\Throwable $e) {
            \Api\System\Library\Support\AppLog::error('[Branding] failed to read branding settings: ' . $e->getMessage());
        }
    }

    /**
     * Trim, drop control characters and cap the length. Never returns markup:
     * templates escape the value, and a hostile value degrades to '' rather
     * than reaching the document.
     */
    public static function sanitizeName(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) > self::NAME_MAX_LENGTH) {
            $value = mb_substr($value, 0, self::NAME_MAX_LENGTH);
        }

        return $value;
    }

    /** Only our own generated file names are ever accepted. */
    public static function sanitizeLogo(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match(self::LOGO_FILE_PATTERN, $value) !== 1) {
            return '';
        }

        return $value;
    }

    /**
     * Branding storage directory (outside the web root). Null when unusable.
     */
    private function storageDir(): ?string
    {
        $storageBase = trim((string)getenv('CRM_STORAGE_BASE'));
        if ($storageBase === '') {
            $storageBase = dirname($this->baseDir) . '/storage_api';
        }

        return rtrim($storageBase, '/') . '/branding';
    }
}
