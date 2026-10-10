<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class LanguagePackInstaller
{
    public function __construct(
        private readonly LanguageRegistryService $registry,
        private readonly LanguageFileAstValidator $astValidator,
        private readonly string $basePath, // points to upload/api
        private readonly ?UrlSafetyValidator $urlValidator = null
    ) {
    }

    /**
     * Inspect and strictly validate a language pack ZIP archive.
     *
     * @return array{
     *     ok: bool,
     *     manifest: array<string, mixed>,
     *     files: list<string>,
     *     violations: list<array<string, mixed>>
     * }
     */
    public function validateArchive(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            throw new InvalidArgumentException('Package archive not found.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new InvalidArgumentException(sprintf('Failed to open ZIP archive (code: %s).', (string)$openResult));
        }

        $violations = [];
        $files = [];
        $manifestContent = null;

        $numFiles = $zip->numFiles;
        for ($i = 0; $i < $numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }

            // Zip-Slip & traversal detection
            if (str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
                $violations[] = ['entry' => $entry, 'message' => 'Absolute paths forbidden in archive.'];
                continue;
            }
            if (str_contains($entry, '../') || str_contains($entry, '..\\')) {
                $violations[] = ['entry' => $entry, 'message' => 'Directory traversal (..) detected in archive entry.'];
                continue;
            }
            if (preg_match('/[^\x20-\x7e]/', $entry)) {
                $violations[] = ['entry' => $entry, 'message' => 'Non-printable or control characters in archive path.'];
                continue;
            }

            // Strip trailing slash for directories
            if (str_ends_with($entry, '/')) {
                continue;
            }

            $entryNormalized = ltrim(str_replace('\\', '/', $entry), './');
            $files[] = $entryNormalized;

            if ($entryNormalized === 'manifest.json') {
                $raw = $zip->getFromIndex($i);
                if (is_string($raw)) {
                    $manifestContent = $raw;
                }
            } elseif (str_ends_with(strtolower($entryNormalized), '.php')) {
                $code = $zip->getFromIndex($i);
                if (is_string($code)) {
                    $astViolations = $this->astValidator->validateCode($code, $entryNormalized);
                    foreach ($astViolations as $v) {
                        $violations[] = [
                            'entry' => $entryNormalized,
                            'line' => $v['line'],
                            'message' => $v['message'],
                            'token' => $v['token'],
                        ];
                    }
                }
            } else {
                // Reject unexpected non-translation files (e.g. .exe, .sh, .phtml, etc.)
                $violations[] = [
                    'entry' => $entryNormalized,
                    'message' => 'Disallowed file type in language pack. Only manifest.json and .php dictionary files are permitted.',
                ];
            }
        }

        $manifest = [];
        if ($manifestContent === null) {
            $violations[] = ['entry' => 'manifest.json', 'message' => 'Archive is missing required manifest.json file.'];
        } else {
            $decoded = json_decode($manifestContent, true);
            if (!is_array($decoded)) {
                $violations[] = ['entry' => 'manifest.json', 'message' => 'manifest.json contains invalid JSON.'];
            } else {
                $manifest = $decoded;
                $this->validateManifestStructure($manifest, $violations);
            }
        }

        $zip->close();

        return [
            'ok' => empty($violations),
            'manifest' => $manifest,
            'files' => $files,
            'violations' => $violations,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<array<string, mixed>> $violations
     */
    private function validateManifestStructure(array $manifest, array &$violations): void
    {
        $type = (string)($manifest['type'] ?? '');
        if ($type !== 'language_pack') {
            $violations[] = ['entry' => 'manifest.json', 'message' => 'Field "type" must be "language_pack".'];
        }

        $code = strtolower(trim((string)($manifest['code'] ?? '')));
        if ($code === '' || !preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', $code)) {
            $violations[] = ['entry' => 'manifest.json', 'message' => 'Field "code" is required and must be a valid locale code (e.g. ar-sa, he-il).'];
        }

        $name = trim((string)($manifest['name'] ?? ''));
        if ($name === '') {
            $violations[] = ['entry' => 'manifest.json', 'message' => 'Field "name" is required in manifest.json.'];
        }

        $direction = strtolower(trim((string)($manifest['direction'] ?? '')));
        if ($direction !== '' && !in_array($direction, ['ltr', 'rtl'], true)) {
            $violations[] = ['entry' => 'manifest.json', 'message' => 'Field "direction" must be either "ltr" or "rtl".'];
        }
    }

    /**
     * Install a language pack from a ZIP archive on disk.
     *
     * @return array{ok: bool, code: string, name: string, is_enabled: bool}
     */
    public function installArchive(string $zipPath): array
    {
        $validation = $this->validateArchive($zipPath);
        if (!$validation['ok']) {
            $messages = array_map(
                static fn(array $v): string => sprintf('[%s] %s', $v['entry'] ?? 'archive', $v['message'] ?? 'Error'),
                $validation['violations']
            );
            throw new InvalidArgumentException('Language pack validation failed: ' . implode('; ', $messages));
        }

        $manifest = $validation['manifest'];
        $code = strtolower(trim((string)$manifest['code']));

        $webLangDir = dirname($this->basePath) . '/web/language';
        $apiLangDir = $this->basePath . '/language/' . $code;

        if (!is_dir($webLangDir)) {
            @mkdir($webLangDir, 0775, true);
        }
        if (!is_dir($apiLangDir)) {
            @mkdir($apiLangDir, 0775, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Failed to reopen archive for extraction.');
        }

        // Save manifest.json to API language directory
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($apiLangDir . '/manifest.json', $manifestJson, LOCK_EX);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || str_ends_with($entry, '/')) {
                continue;
            }

            $norm = ltrim(str_replace('\\', '/', $entry), './');
            if ($norm === 'manifest.json') {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if (!is_string($content)) {
                continue;
            }

            // Web language file
            if ($norm === 'web/' . $code . '.php' || $norm === $code . '.php') {
                $targetFile = $webLangDir . '/' . $code . '.php';
                file_put_contents($targetFile, $content, LOCK_EX);
                @chmod($targetFile, 0640);
                continue;
            }

            // Optional per-pack supplemental dictionary. This keeps locale-
            // specific late fixes out of the core overrides files while still
            // allowing the web runtime and early responses to load them.
            // The package layout uses web/supplemental/packs/<code>.php, but
            // I18n/EarlyResponse read web/language/supplemental/packs/<code>.php
            // (the same path exportPackage/deletePackage use), so the entry
            // must be remapped to the runtime directory on install.
            $supplementalPrefix = null;
            if (str_starts_with($norm, 'web/supplemental/packs/')) {
                $supplementalPrefix = 'web/supplemental/packs/';
            } elseif (str_starts_with($norm, 'web/language/supplemental/packs/')) {
                $supplementalPrefix = 'web/language/supplemental/packs/';
            }
            if ($supplementalPrefix !== null) {
                $relative = substr($norm, strlen($supplementalPrefix));
                $targetFile = dirname($this->basePath) . '/web/language/supplemental/packs/' . $relative;
                $targetDir = dirname($targetFile);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                file_put_contents($targetFile, $content, LOCK_EX);
                @chmod($targetFile, 0640);
                continue;
            }

            // API language files
            if (str_starts_with($norm, 'api/' . $code . '/')) {
                $subPath = substr($norm, strlen('api/' . $code . '/'));
                $targetFile = $apiLangDir . '/' . $subPath;
                $targetDir = dirname($targetFile);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                file_put_contents($targetFile, $content, LOCK_EX);
                @chmod($targetFile, 0640);
                continue;
            }

            if (str_starts_with($norm, 'api/')) {
                $subPath = substr($norm, 4);
                $targetFile = $apiLangDir . '/' . $subPath;
                $targetDir = dirname($targetFile);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                file_put_contents($targetFile, $content, LOCK_EX);
                @chmod($targetFile, 0640);
                continue;
            }
        }

        $zip->close();

        // Auto-enable newly installed language pack
        $enabled = $this->registry->getEnabledCodes();
        if (!in_array($code, $enabled, true)) {
            $enabled[] = $code;
            $this->registry->toggle($code, true);
        } else {
            $this->registry->syncStorageCache();
        }

        return [
            'ok' => true,
            'code' => $code,
            'name' => (string)($manifest['name'] ?? $code),
            'is_enabled' => true,
        ];
    }

    /**
     * Download and install a language pack from URL.
     *
     * @return array{ok: bool, code: string, name: string, is_enabled: bool}
     */
    public function installFromUrl(string $url, ?string $expectedHash = null): array
    {
        $trimmed = trim($url);
        // SEC-001: an unpinned package must be fetched over TLS. Cleartext HTTP
        // is accepted only when the caller also pins the expected SHA-256, so a
        // network attacker cannot substitute the archive that gets installed.
        // The scheme check is applied unconditionally, before the SSRF check, so
        // it still fails closed when no UrlSafetyValidator was injected.
        $pinned = $expectedHash !== null && $expectedHash !== '';
        $allowedSchemes = $pinned ? ['https', 'http'] : ['https'];
        $parts = parse_url($trimmed);
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        if (!in_array($scheme, $allowedSchemes, true)) {
            throw new InvalidArgumentException(
                'Package download URL is not allowed: AI_PROVIDER_URL_SCHEME_NOT_ALLOWED'
            );
        }
        if ($this->urlValidator !== null) {
            $validation = $this->urlValidator->validateProviderUrl($trimmed, true, $allowedSchemes);
            if (!$validation['ok']) {
                throw new InvalidArgumentException('Package download URL is not allowed: ' . $validation['code']);
            }
        }

        $tempDir = dirname($this->basePath) . '/storage/temp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tempZip = $tempDir . '/lang_pack_' . bin2hex(random_bytes(8)) . '.zip';

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'TropaTT-CRM-LanguageInstaller/1.0',
                'follow_location' => 1,
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $in = @fopen($trimmed, 'rb', false, $ctx);
        if ($in === false) {
            throw new RuntimeException('Failed to connect to language pack URL.');
        }

        $out = fopen($tempZip, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException('Failed to create temporary archive file.');
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($expectedHash !== null && $expectedHash !== '') {
            $actualHash = hash_file('sha256', $tempZip);
            if (!hash_equals($expectedHash, (string)$actualHash)) {
                @unlink($tempZip);
                throw new InvalidArgumentException('Package SHA256 checksum mismatch.');
            }
        }

        try {
            $result = $this->installArchive($tempZip);
        } finally {
            @unlink($tempZip);
        }

        return $result;
    }

    /**
     * Export an installed language to a standard ZIP package archive.
     *
     * @return string Absolute path to the generated ZIP package file.
     */
    public function exportPackage(string $code): string
    {
        $code = $this->registry->normalizeLocaleCode($code);
        $all = $this->registry->discoverInstalledLocales();
        if (!isset($all[$code])) {
            throw new InvalidArgumentException(sprintf('Locale "%s" is not installed.', $code));
        }

        $localeMeta = $all[$code];
        $manifest = [
            'type' => 'language_pack',
            'code' => $code,
            'name' => (string)($localeMeta['name'] ?? $code),
            'native_name' => (string)($localeMeta['native_name'] ?? $code),
            'direction' => (string)($localeMeta['direction'] ?? ($this->registry->isRtlCode($code) ? 'rtl' : 'ltr')),
            'author' => (string)($localeMeta['author'] ?? 'TropaTT CRM Team'),
            'version' => (string)($localeMeta['version'] ?? '1.0.0'),
            'min_crm_version' => '20260928.001',
            'description' => sprintf('%s language pack for TropaTT CRM', $localeMeta['name'] ?? $code),
        ];

        $tempDir = dirname($this->basePath) . '/storage/temp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $zipPath = $tempDir . '/language-pack-' . $code . '-' . date('YmdHis') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create export ZIP archive.');
        }

        // Add manifest.json
        $zip->addFromString('manifest.json', (string)json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Add web translation file if present
        $webFile = dirname($this->basePath) . '/web/language/' . $code . '.php';
        if (is_file($webFile)) {
            $zip->addFile($webFile, 'web/' . $code . '.php');
        }

        $packSupplemental = dirname($this->basePath) . '/web/language/supplemental/packs/' . $code . '.php';
        if (is_file($packSupplemental)) {
            $zip->addFile($packSupplemental, 'web/supplemental/packs/' . $code . '.php');
        }

        // Add API translation files if present
        $apiDir = $this->basePath . '/language/' . $code;
        if (is_dir($apiDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($apiDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relPath = substr($file->getPathname(), strlen($apiDir) + 1);
                    $zip->addFile($file->getPathname(), 'api/' . $code . '/' . $relPath);
                }
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Delete an installed language pack.
     * Built-in and default languages cannot be deleted.
     *
     * @return array{ok: bool, code: string, message: string}
     */
    public function deletePackage(string $code): array
    {
        $code = $this->registry->normalizeLocaleCode($code);

        if ($this->registry->isBuiltin($code)) {
            throw new InvalidArgumentException('Cannot delete built-in system language.');
        }

        $default = $this->registry->getDefaultLocale();
        if ($code === $default) {
            throw new InvalidArgumentException('Cannot delete the default system language.');
        }

        $installed = $this->registry->discoverInstalledLocales();
        if (!isset($installed[$code])) {
            throw new InvalidArgumentException(sprintf('Locale "%s" is not installed.', $code));
        }

        // 1. Remove from enabled codes if present (must be done before deleting files so discoverInstalledLocales sees it)
        $enabled = $this->registry->getEnabledCodes();
        if (in_array($code, $enabled, true)) {
            $this->registry->toggle($code, false);
        }

        // 2. Remove web file
        $webFile = dirname($this->basePath) . '/web/language/' . $code . '.php';
        if (is_file($webFile)) {
            @unlink($webFile);
        }

        $packSupplemental = dirname($this->basePath) . '/web/language/supplemental/packs/' . $code . '.php';
        if (is_file($packSupplemental)) {
            @unlink($packSupplemental);
        }

        // Clean up the legacy install location (older builds wrote the pack
        // supplemental file to web/supplemental/packs/ instead of the runtime
        // directory) plus the directories when they became empty.
        $legacySupplemental = dirname($this->basePath) . '/web/supplemental/packs/' . $code . '.php';
        if (is_file($legacySupplemental)) {
            @unlink($legacySupplemental);
            @rmdir(dirname($legacySupplemental));
            @rmdir(dirname(dirname($legacySupplemental)));
        }

        // 3. Remove API language folder recursively
        $apiDir = $this->basePath . '/language/' . $code;
        if (is_dir($apiDir)) {
            $this->removeDirectoryRecursively($apiDir);
        }

        $this->registry->syncStorageCache();

        return [
            'ok' => true,
            'code' => $code,
            'message' => sprintf('Language pack "%s" successfully deleted.', $code),
        ];
    }

    /**
     * Whether a locale's language files are present on disk.
     */
    public function isLocaleInstalled(string $code): bool
    {
        $code = $this->registry->normalizeLocaleCode($code);

        return isset($this->registry->discoverInstalledLocales()[$code]);
    }

    /**
     * Remove the marketplace proxy module of a language pack (the manifest.json
     * + directory a marketplace install creates next to the language files).
     *
     * The directory is deleted only when its manifest declares a language_pack
     * for exactly this locale, so an unrelated module directory can never be
     * touched. Returns true when files were actually removed.
     */
    public function removeModuleProxy(string $code): bool
    {
        $code = $this->registry->normalizeLocaleCode($code);
        $moduleCode = 'crm.language-pack-' . $code;
        $dir = dirname($this->basePath) . '/modules/' . $moduleCode;
        $manifestPath = $dir . '/manifest.json';

        if (!is_file($manifestPath)) {
            return false;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)
            || (string)($manifest['type'] ?? '') !== 'language_pack'
            || (string)($manifest['code'] ?? '') !== $code) {
            return false;
        }

        $this->removeDirectoryRecursively($dir);

        return !is_dir($dir);
    }

    private function removeDirectoryRecursively(string $dir): void
    {
        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Alias for installArchive.
     */
    public function installFromZip(string $zipPath): array
    {
        return $this->installArchive($zipPath);
    }

    /**
     * Alias for exportPackage.
     */
    public function exportToZip(string $code): string
    {
        return $this->exportPackage($code);
    }

    /**
     * Alias for deletePackage.
     */
    public function delete(string $code): array
    {
        return $this->deletePackage($code);
    }
}
