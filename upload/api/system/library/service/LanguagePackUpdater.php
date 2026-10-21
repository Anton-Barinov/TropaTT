<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Security\UrlSafetyValidator;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class LanguagePackUpdater
{
    private const MAX_ARCHIVE_BYTES = 104857600;
    private const MAX_EXPANDED_BYTES = 104857600;
    private const MAX_ENTRY_BYTES = 10485760;
    private const MAX_ENTRIES = 3000;
    private const MAX_COMPRESSION_RATIO = 100;

    public function __construct(
        private readonly LanguageRegistryService $registry,
        private readonly LanguageFileAstValidator $astValidator,
        private readonly ModuleConfig $moduleConfig,
        private readonly string $basePath,
        private readonly UrlSafetyValidator $urlValidator
    ) {
    }

    public function updateFromUrl(string $url, string $expectedModuleCode, string $expectedVersion, string $expectedSha256, string $coreVersion): array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/', $expectedModuleCode)
            || !str_starts_with($expectedModuleCode, 'crm.language-pack-')
            || str_contains($expectedModuleCode, '..')) {
            throw new InvalidArgumentException('Invalid language pack module code.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $expectedSha256)) {
            throw new InvalidArgumentException('Invalid Marketplace SHA-256.');
        }
        $validation = $this->urlValidator->validateProviderUrl($url, true, ['https']);
        if (!$validation['ok']) {
            throw new InvalidArgumentException('Language pack URL rejected: ' . $validation['code']);
        }

        $tempDir = sys_get_temp_dir();
        $archive = rtrim($tempDir, DIRECTORY_SEPARATOR) . '/tropa-language-update-' . bin2hex(random_bytes(16)) . '.zip';
        try {
            $this->downloadBounded($url, $archive, (array)($validation['resolved_ips'] ?? []));
            $actual = hash_file('sha256', $archive);
            if (!is_string($actual) || !hash_equals(strtolower($expectedSha256), strtolower($actual))) {
                throw new RuntimeException('Language package SHA-256 mismatch.');
            }
            return $this->updateArchive($archive, $expectedModuleCode, $expectedVersion, $expectedSha256, $coreVersion);
        } finally {
            if (is_file($archive) && !is_link($archive)) {
                @unlink($archive);
            }
        }
    }

    public function updateArchive(string $archive, string $expectedModuleCode, string $expectedVersion, string $expectedSha256, string $coreVersion): array
    {
        if (!is_file($archive) || is_link($archive) || !preg_match('/^[a-f0-9]{64}$/i', $expectedSha256)
            || !preg_match('/^crm\.language-pack-[a-z]{2,3}(?:-[a-z0-9]{2,4})?$/', $expectedModuleCode)) {
            throw new InvalidArgumentException('Invalid update archive or SHA-256.');
        }
        $size = filesize($archive);
        if (!is_int($size) || $size < 4 || $size > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidArgumentException('Language package archive size is outside allowed limits.');
        }
        $actual = hash_file('sha256', $archive);
        if (!is_string($actual) || !hash_equals(strtolower($expectedSha256), strtolower($actual))) {
            throw new RuntimeException('Language package SHA-256 mismatch.');
        }
        $locale = substr($expectedModuleCode, strlen('crm.language-pack-'));
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,4})?$/', $locale)
            || $this->registry->normalizeLocaleCode($locale) !== $locale
            || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $expectedVersion)) {
            throw new InvalidArgumentException('Invalid locale code or Marketplace version.');
        }

        $root = dirname($this->basePath);
        $proxyDir = $root . '/modules/' . $expectedModuleCode;
        $proxyManifestPath = $proxyDir . '/manifest.json';
        $localeDir = $this->basePath . '/language/' . $locale;
        $localeManifestPath = $localeDir . '/manifest.json';
        $registryRow = $this->moduleConfig->getRegistry($expectedModuleCode);
        if ($registryRow === null || is_link($proxyDir) || !is_dir($proxyDir) || is_link($localeDir) || !is_dir($localeDir)) {
            throw new RuntimeException('Installed language pack or Marketplace proxy is missing or unsafe.');
        }
        $proxyBefore = $this->readManifest($proxyManifestPath);
        $localeBefore = $this->readManifest($localeManifestPath);
        $this->assertInstalledIdentity($proxyBefore, $expectedModuleCode, $locale);
        $this->assertInstalledIdentity($localeBefore, $expectedModuleCode, $locale);
        $oldVersion = (string)($localeBefore['version'] ?? '');
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $oldVersion)
            || (string)($proxyBefore['version'] ?? '') !== $oldVersion
            || (string)($registryRow['version'] ?? '') !== $oldVersion
            || version_compare($expectedVersion, $oldVersion, '<=')) {
            throw new RuntimeException('Marketplace version is not newer than installed language pack.');
        }

        $lockDir = $root . '/storage_api/locks';
        $this->ensurePrivateDirectory($lockDir);
        @chmod($lockDir, 0700);
        $lockPath = $lockDir . '/module-' . hash('sha256', $expectedModuleCode) . '.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Unsafe language update lock.');
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open language update lock.');
        }
        @chmod($lockPath, 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('A language pack update is already running.');
        }

        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/tropa-language-update-' . bin2hex(random_bytes(16));
        $stage = $workDir . '/stage';
        $backup = $workDir . '/backup';
        $committed = [];
        $retainBackup = false;
        try {
            $this->ensurePrivateDirectory($workDir);
            @chmod($workDir, 0700);
            $proxyNow = $this->readManifest($proxyManifestPath);
            $localeNow = $this->readManifest($localeManifestPath);
            $registryNow = $this->moduleConfig->getRegistry($expectedModuleCode);
            $this->assertInstalledIdentity($localeNow, $expectedModuleCode, $locale);
            $this->assertInstalledIdentity($proxyNow, $expectedModuleCode, $locale);
            if ((string)($localeNow['version'] ?? '') !== $oldVersion
                || (string)($proxyNow['version'] ?? '') !== (string)($proxyBefore['version'] ?? '')
                || !is_array($registryNow)
                || (string)($registryNow['version'] ?? '') !== (string)($registryRow['version'] ?? '')
                || (string)($registryNow['is_active'] ?? '') !== (string)($registryRow['is_active'] ?? '')) {
                throw new RuntimeException('Installed language pack changed before update lock was acquired.');
            }

            $validated = $this->readAndValidateArchive($archive, $locale, $expectedModuleCode, $expectedVersion, $coreVersion);
            $stageFiles = $this->stageFiles($validated['files'], $stage);
            $proxyAfter = $proxyBefore;
            $proxyAfter['version'] = $expectedVersion;
            $localeManifest = $validated['manifest'];
            $localeManifest['full_code'] = $expectedModuleCode;
            $localeManifest['version'] = $expectedVersion;
            $stageFiles[$localeManifestPath] = $this->stageOne($stage, $localeManifestPath, $this->json($localeManifest));
            $stageFiles[$proxyManifestPath] = $this->stageOne($stage, $proxyManifestPath, $this->json($proxyAfter));
            $replace = $stageFiles;

            $this->ensurePrivateDirectory($backup);
            @chmod($backup, 0700);
            uksort($replace, static fn(string $a, string $b): int => strcmp($a, $b));
            foreach ($replace as $target => $content) {
                $this->assertTargetPath($target, $root);
                $old = is_file($target) ? file_get_contents($target) : false;
                if (is_link($target) || ($old === false && file_exists($target))) {
                    throw new RuntimeException('Unsafe language update target.');
                }
                $backupPath = $backup . '/' . hash('sha256', $target);
                if ($old !== false && file_put_contents($backupPath, $old, LOCK_EX) !== strlen($old)) {
                    throw new RuntimeException('Cannot back up existing language file.');
                }
                if ($old !== false) @chmod($backupPath, 0600);
                $targetDir = dirname($target);
                $this->ensurePrivateDirectory($targetDir);
                $this->assertOpcacheInvalidatable($target);
                if (!is_string($content) || !is_file($content) || is_link($content)) {
                    throw new RuntimeException('Invalid staged language replacement file.');
                }
                $targetTmp = $targetDir . '/.lpu-' . bin2hex(random_bytes(12)) . '.tmp';
                if (!copy($content, $targetTmp)) throw new RuntimeException('Cannot prepare same-filesystem language replacement.');
                @chmod($targetTmp, 0640);
                if (!@rename($targetTmp, $target)) {
                    @unlink($targetTmp);
                    throw new RuntimeException('Cannot atomically replace language file.');
                }
                $committed[] = [$target, $backupPath, $old !== false];
                $this->assertOpcacheInvalidatable($target);
            }
            $this->moduleConfig->setVersion($expectedModuleCode, $expectedVersion);
            $this->registry->syncStorageCache();
            return [
                'code' => $locale,
                'version' => $expectedVersion,
                'name' => (string)($localeManifest['name'] ?? $locale),
                'is_enabled' => $this->registry->isLocaleEnabled($locale),
            ];
        } catch (\Throwable $e) {
            $restoreFailed = false;
            foreach (array_reverse($committed) as [$target, $backupPath, $hadOld]) {
                if ($hadOld && is_file($backupPath)) {
                    $restore = dirname($target) . '/.lpu-restore-' . bin2hex(random_bytes(12)) . '.tmp';
                    if (!@copy($backupPath, $restore) || !@rename($restore, $target)) {
                        @unlink($restore);
                        $restoreFailed = true;
                    } else {
                        try { $this->assertOpcacheInvalidatable($target); } catch (\Throwable) { $restoreFailed = true; }
                    }
                } elseif (!$hadOld && is_file($target)) {
                    if (!@unlink($target)) {
                        $restoreFailed = true;
                    } else {
                        try { $this->assertOpcacheInvalidatable($target); } catch (\Throwable) { $restoreFailed = true; }
                    }
                }
            }
            try {
                $this->moduleConfig->setVersion($expectedModuleCode, (string)($registryRow['version'] ?? $proxyBefore['version'] ?? $oldVersion));
                if (!$restoreFailed) {
                    $this->registry->syncStorageCache();
                }
            } catch (\Throwable) {
                $restoreFailed = true;
            }
            if ($restoreFailed) {
                $retainBackup = true;
                throw new RuntimeException('Language update failed and rollback was incomplete; inspect retained backup files.', 0, $e);
            }
            throw $e;
        } finally {
            if (!$retainBackup) $this->removeTree($workDir);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readArchiveManifest(string $archive): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('Cannot open language package ZIP.');
        }
        try {
            $index = $zip->locateName('manifest.json', ZipArchive::FL_NOCASE);
            if ($index === false) {
                throw new InvalidArgumentException('Language package manifest is missing.');
            }
            $raw = $zip->getFromIndex($index, 65536);
            $manifest = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($manifest)) {
                throw new InvalidArgumentException('Language package manifest is invalid.');
            }
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function readManifest(string $path): array
    {
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException('Installed language manifest is missing or unsafe.');
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('Installed language manifest is invalid.');
        }
        return $data;
    }

    private function assertInstalledIdentity(array $manifest, string $moduleCode, string $locale): void
    {
        if (($manifest['type'] ?? '') !== 'language_pack'
            || ($manifest['code'] ?? '') !== $locale
            || (($manifest['full_code'] ?? $moduleCode) !== $moduleCode)) {
            throw new RuntimeException('Installed language pack identity does not match its Marketplace module.');
        }
    }

    private function readAndValidateArchive(string $archive, string $locale, string $moduleCode, string $version, string $coreVersion): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('Cannot open language package ZIP.');
        }
        $files = [];
        $normalizedSeen = [];
        $expanded = 0;
        $manifest = null;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new InvalidArgumentException('Language package entry count exceeds limits.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name)) {
                    throw new InvalidArgumentException('Invalid language package entry name.');
                }
                $isDirectory = str_ends_with($name, '/');
                if ($isDirectory) $name = rtrim($name, '/');
                if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')
                    || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name)
                    || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                    throw new InvalidArgumentException('Unsafe language package path.');
                }
                $segments = explode('/', $name);
                foreach ($segments as $segment) {
                    if ($segment === '' || $segment === '.' || $segment === '..') {
                        throw new InvalidArgumentException('Unsafe language package path segment.');
                    }
                }
                $normalized = implode('/', $segments);
                $collisionKey = strtolower($normalized);
                if (isset($normalizedSeen[$collisionKey])) {
                    throw new InvalidArgumentException('Duplicate or colliding language package path.');
                }
                $normalizedSeen[$collisionKey] = true;
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $fileType = ($attributes >> 16) & 0170000;
                    if (in_array($fileType, [0120000, 0060000, 0020000, 0010000], true)) {
                        throw new InvalidArgumentException('Links and special files are forbidden in language package.');
                    }
                }
                if ($isDirectory) continue;
                $stat = $zip->statIndex($i);
                $entrySize = max(0, (int)($stat['size'] ?? 0));
                $compressed = max(0, (int)($stat['comp_size'] ?? 0));
                $expanded += $entrySize;
                if ($entrySize > self::MAX_ENTRY_BYTES || $expanded > self::MAX_EXPANDED_BYTES
                    || ($entrySize > 0 && ($compressed === 0 || $entrySize / $compressed > self::MAX_COMPRESSION_RATIO))) {
                    throw new InvalidArgumentException('Language package expands beyond allowed limits.');
                }
                $allowed = $normalized === 'manifest.json'
                    || $normalized === $locale . '.php'
                    || $normalized === 'web/' . $locale . '.php'
                    || $normalized === 'web/supplemental/packs/' . $locale . '.php'
                    || $normalized === 'web/language/supplemental/packs/' . $locale . '.php'
                    || preg_match('#^api/' . preg_quote($locale, '#') . '/[A-Za-z0-9._/-]+\.php$#', $normalized) === 1
                    || preg_match('#^api/[A-Za-z0-9._/-]+\.php$#', $normalized) === 1;
                if (!$allowed || !str_ends_with($normalized, '.php') && $normalized !== 'manifest.json') {
                    throw new InvalidArgumentException('Unexpected language package entry.');
                }
                $content = $zip->getFromIndex($i, self::MAX_ENTRY_BYTES + 1);
                if (!is_string($content) || strlen($content) !== $entrySize) {
                    throw new InvalidArgumentException('Cannot read bounded language package entry.');
                }
                if ($normalized === 'manifest.json') {
                    $manifest = json_decode($content, true);
                    if (!is_array($manifest)) {
                        throw new InvalidArgumentException('Language package manifest is invalid.');
                    }
                    continue;
                }
                $violations = $this->astValidator->validateCode($content, $normalized);
                if ($violations !== []) {
                    throw new InvalidArgumentException('Language dictionary failed AST validation.');
                }
                $target = $this->mapTarget($normalized, $locale);
                if (isset($files[$target])) throw new InvalidArgumentException('Different archive paths map to the same installed file.');
                $files[$target] = $content;
            }
        } finally {
            $zip->close();
        }
        if (!is_array($manifest) || ($manifest['type'] ?? '') !== 'language_pack'
            || strtolower(trim((string)($manifest['code'] ?? ''))) !== $locale
            || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string)($manifest['version'] ?? ''))
            || (string)$manifest['version'] !== $version
            || trim((string)($manifest['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Language package manifest code, type, name, or version is invalid.');
        }
        if (isset($manifest['full_code']) && (string)$manifest['full_code'] !== $moduleCode) {
            throw new InvalidArgumentException('Language package full_code does not match selected Marketplace module.');
        }
        $direction = strtolower((string)($manifest['direction'] ?? 'ltr'));
        if (!in_array($direction, ['ltr', 'rtl'], true)) {
            throw new InvalidArgumentException('Language package direction is invalid.');
        }
        $minCore = trim((string)($manifest['min_crm_version'] ?? ''));
        if ($minCore !== '') {
            $constraint = preg_replace('/^>=\s*/', '', $minCore) ?? $minCore;
            if (!preg_match('/^\d+(?:\.\d+){1,3}$/', $constraint)) {
                throw new InvalidArgumentException('Language package core version constraint is invalid.');
            }
            if (version_compare($coreVersion, $constraint, '<')) {
                throw new InvalidArgumentException('Language package is incompatible with this CRM core.');
            }
        }
        $hasWebDictionary = isset($files[dirname($this->basePath) . '/web/language/' . $locale . '.php']);
        $hasApiMessages = false;
        foreach (array_keys($files) as $path) {
            if (str_starts_with($path, $this->basePath . '/language/' . $locale . '/')
                && str_ends_with($path, '/messages.php')) { $hasApiMessages = true; break; }
        }
        if (!$hasWebDictionary || !$hasApiMessages) {
            throw new InvalidArgumentException('Language package must include the locale web dictionary and API messages.');
        }
        $manifest['full_code'] = $moduleCode;
        return ['manifest' => $manifest, 'files' => $files];
    }

    private function mapTarget(string $name, string $locale): string
    {
        $root = dirname($this->basePath);
        if ($name === $locale . '.php' || $name === 'web/' . $locale . '.php') {
            return $root . '/web/language/' . $locale . '.php';
        }
        if (str_starts_with($name, 'web/supplemental/packs/') || str_starts_with($name, 'web/language/supplemental/packs/')) {
            return $root . '/web/language/supplemental/packs/' . $locale . '.php';
        }
        $relative = preg_replace('#^api/' . preg_quote($locale, '#') . '/#', '', $name) ?? $name;
        $relative = preg_replace('#^api/#', '', $relative) ?? $relative;
        if ($relative === '' || str_contains($relative, '..')) {
            throw new InvalidArgumentException('Invalid API language path.');
        }
        return $this->basePath . '/language/' . $locale . '/' . $relative;
    }

    private function stageFiles(array $files, string $stage): array
    {
        $this->ensurePrivateDirectory($stage);
        @chmod($stage, 0700);
        $result = [];
        $targets = [];
        foreach ($files as $target => $content) {
            if (!is_string($target) || !is_string($content) || isset($targets[$target])) {
                throw new RuntimeException('Invalid or colliding staged translation entry.');
            }
            $targets[$target] = true;
            $result[$target] = $this->stageOne($stage, $target, $content);
        }
        return $result;
    }

    private function stageOne(string $stage, string $target, string $content): string
    {
        $path = $stage . '/' . hash('sha256', $target) . '.stage';
        if (file_put_contents($path, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('Cannot stage language update file.');
        }
        @chmod($path, 0640);
        return $path;
    }

    private function downloadBounded(string $url, string $dest, array $resolvedIps): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Secure package downloader is unavailable.');
        }
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
        $port = (int)(parse_url($url, PHP_URL_PORT) ?: 443);
        $ch = curl_init($url);
        $handle = @fopen($dest, 'xb');
        if ($ch === false || $handle === false) {
            if ($ch !== false) curl_close($ch);
            throw new RuntimeException('Cannot initialize language package download.');
        }
        @chmod($dest, 0600);
        $downloaded = 0;
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT => 'TropaTT-CRM-LanguageUpdater/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$downloaded, &$tooLarge): int {
                $downloaded += strlen($chunk);
                if ($downloaded > self::MAX_ARCHIVE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $written = fwrite($handle, $chunk);
                return $written === false ? 0 : $written;
            },
        ]);
        if ($host !== '' && isset($resolvedIps[0])) {
            curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . $port . ':' . trim((string)$resolvedIps[0])]);
        }
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);
        if ($ok !== true || $status < 200 || $status >= 300) {
            @unlink($dest);
            throw new RuntimeException($tooLarge ? 'Language package exceeds the download limit.' : 'Language package download failed: ' . $error);
        }
        $fp = @fopen($dest, 'rb');
        $magic = $fp ? fread($fp, 4) : false;
        if ($fp) fclose($fp);
        if (!is_string($magic) || strlen($magic) < 4 || $magic[0] !== 'P' || $magic[1] !== 'K') {
            @unlink($dest);
            throw new RuntimeException('Downloaded language package is not a ZIP archive.');
        }
    }

    private function assertTargetPath(string $target, string $root): void
    {
        $parent = realpath(dirname($target));
        $rootReal = realpath($root);
        if ($rootReal === false || ($parent !== false && !str_starts_with($parent . DIRECTORY_SEPARATOR, $rootReal . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Language update path escaped the application root.');
        }
        $cursor = dirname($target);
        while ($cursor !== $root && str_starts_with($cursor, $root . DIRECTORY_SEPARATOR)) {
            if (is_link($cursor)) throw new RuntimeException('Symlinked language update parent is forbidden.');
            $cursor = dirname($cursor);
        }
    }

    private function ensurePrivateDirectory(string $dir): void
    {
        if (is_link($dir)) throw new RuntimeException('Symlinked language update directory is forbidden.');
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create private language update directory.');
        }
        if (is_link($dir) || !is_dir($dir)) throw new RuntimeException('Unsafe language update directory.');
    }

    private function json(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        return $json . "\n";
    }

    private function assertOpcacheInvalidatable(string $path): void
    {
        $cached = function_exists('opcache_is_script_cached') && opcache_is_script_cached($path);
        if ($cached && (!function_exists('opcache_invalidate') || !opcache_invalidate($path, true))) {
            throw new RuntimeException('Cached language file could not be invalidated safely.');
        }
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) return;
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) $this->removeTree($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }
}
