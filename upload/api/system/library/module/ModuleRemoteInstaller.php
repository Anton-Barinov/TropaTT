<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\ModuleCodeValidator;
use Api\System\Library\Security\UrlSafetyValidator;
use RuntimeException;

final class ModuleRemoteInstaller
{
    public function __construct(
        private readonly PluginManager $pm,
        private readonly ModuleConfig $mc,
        private readonly ModuleMigrationRunner $mm,
        private readonly string $projectRoot,
    ) {}

    /**
     * Install a module from a remote URL.
     *
     * @param string|null $expectedName Canonical module code the caller selected
     *        (for example a marketplace `full_code`). When given, the package
     *        manifest must declare exactly this name.
     * @param string|null $expectedSha256 Hex sha256 the marketplace install-request
     *        declared for this release. When given, the downloaded archive is
     *        verified against it (channel-verified install — see installFromFile()).
     * @return string Module name
     */
    public function installFromUrl(string $url, bool $verifySignature = true, ?string $expectedName = null, ?string $expectedSha256 = null): string
    {
        $tmpDir = sys_get_temp_dir() . '/crm_module_' . bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0755, true);

        try {
            $archive = $tmpDir . '/module.zip';
            $this->download($url, $archive);
            return $this->installFromFile($archive, $verifySignature, $expectedName, $expectedSha256);
        } finally {
            $this->cleanDir($tmpDir);
        }
    }

    /**
     * Install from a local package file.
     *
     * @param string|null $expectedName Canonical module code the caller selected;
     *        see installFromUrl().
     * @param string|null $expectedSha256 Hex sha256 of the archive as declared by
     *        the trusted channel it came from (the marketplace install-request,
     *        fetched over TLS from the configured base_url). A mismatch throws
     *        ModulePackageHashMismatchException BEFORE anything is extracted.
     *        A pinned archive is "channel-verified": its integrity was proven
     *        against the marketplace response, so the shared HMAC key is not
     *        required for it (without a pin, verification stays fail-closed on
     *        MODULE_SIGNING_KEY — audit C-2).
     * @return string Module name
     */
    public function installFromFile(string $filePath, bool $verifySignature = true, ?string $expectedName = null, ?string $expectedSha256 = null): string
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Package file not found: {$filePath}");
        }

        $sha256Pinned = false;
        if ($expectedSha256 !== null && $expectedSha256 !== '') {
            if (!preg_match('/^[0-9a-f]{64}$/i', $expectedSha256)) {
                throw new RuntimeException("Invalid sha256 supplied for package verification");
            }
            $actual = hash_file('sha256', $filePath);
            if ($actual === false || !hash_equals(strtolower($expectedSha256), strtolower($actual))) {
                throw new ModulePackageHashMismatchException(strtolower($expectedSha256));
            }
            $sha256Pinned = true;
        }

        $extractDir = dirname($filePath) . '/extracted_' . bin2hex(random_bytes(4));
        @mkdir($extractDir, 0755, true);

        try {
            $this->extract($filePath, $extractDir);

            $manifestPath = $extractDir . '/manifest.json';
            if (!is_file($manifestPath)) {
                throw new RuntimeException("Package does not contain manifest.json");
            }

            $manifestContent = file_get_contents($manifestPath);
            if ($manifestContent === false) {
                throw new RuntimeException("Cannot read manifest.json");
            }

            $manifestData = json_decode($manifestContent, true);
            if (!is_array($manifestData)) {
                throw new RuntimeException("Invalid manifest.json");
            }

            // SEC-012: Verify module package signature if requested
            if ($verifySignature) {
                $this->verifyPackageSignature($manifestData, $sha256Pinned);
            }

            $moduleName = $manifestData['name'] ?? '';
            if ($moduleName === '') {
                throw new RuntimeException("Module name not specified in manifest");
            }

            // Sanitize module name. Published module codes use the
            // "<vendor>.<name>" form (for example "crm.wip-limit"), which is the
            // same shape PluginManager::isValidName() accepts elsewhere — a dots-
            // rejecting pattern here made every marketplace package impossible to
            // install. Dots stay blocked as traversal sequences ("..") and as
            // path separators (no slashes are allowed by the pattern).
            if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/', $moduleName) || str_contains($moduleName, '..')) {
                throw new RuntimeException("Invalid module name: {$moduleName}");
            }

            // A package must install under the name its caller selected. A catalog
            // release whose archived manifest names a different module (a bare
            // "name" while the catalog advertises "<vendor>.<module>") would
            // otherwise be rejected several layers later by manifest validation,
            // or silently register under an unexpected code.
            $expectedName = $expectedName !== null ? trim($expectedName) : null;
            if ($expectedName !== null && $expectedName !== '' && $moduleName !== $expectedName) {
                throw new ModulePackageMismatchException($moduleName, $expectedName);
            }

            $targetDir = $this->projectRoot . '/modules/' . $moduleName;
            if (is_dir($targetDir)) {
                throw new RuntimeException("Module already exists: {$moduleName}");
            }

            // C-1: run code validator before copying files into the project.
            // This is defense-in-depth — the blocklist approach is inherently
            // weak, but catches the most obvious forbidden calls (eval, exec,
            // system, etc.) in module code before it enters the trusted tree.
            $codeValidator = new ModuleCodeValidator();
            $violations = $codeValidator->validateModule($extractDir);
            if ($violations !== []) {
                $this->cleanDir($extractDir);
                $details = array_map(
                    fn(array $v): string => "{$v['file']}:{$v['line']} — {$v['function']}()",
                    $violations
                );
                throw new RuntimeException(
                    'Module code contains forbidden function calls: ' . implode('; ', $details)
                );
            }

            $this->copyDir($extractDir, $targetDir);

            $this->pm->discover();
            $manifest = $this->pm->getManifest($moduleName);

            if ($manifest === null) {
                throw new RuntimeException("Failed to discover installed module");
            }

            $errors = $this->pm->validate($manifest);
            if ($errors !== []) {
                $this->cleanDir($targetDir);
                $errorStr = implode('; ', array_map(fn($e) => $e['message'], $errors));
                throw new RuntimeException("Module validation failed: {$errorStr}");
            }

            $this->mc->register($moduleName, $manifest->vendor, $manifest->version);

            if ($manifest->migrations !== null) {
                $migDir = $targetDir . '/' . $manifest->migrations;
                $result = $this->mm->migrate($moduleName, $migDir);
                if ($result['errors'] !== []) {
                    $this->cleanDir($targetDir);
                    $this->mc->unregister($moduleName);
                    throw new RuntimeException("Migration failed: " . implode('; ', $result['errors']));
                }
            }

            $this->mc->initFromManifest($moduleName, $manifest);

            return $moduleName;
        } finally {
            $this->cleanDir($extractDir);
        }
    }

    /**
     * Create a package from an installed module.
     * @return string Path to created archive
     */
    public function package(string $moduleName, string $format = 'zip'): string
    {
        $manifest = $this->pm->getManifest($moduleName);
        if ($manifest === null) {
            throw new RuntimeException("Module not found: {$moduleName}");
        }

        $sourceDir = $this->projectRoot . '/modules/' . $moduleName;
        if (!is_dir($sourceDir)) {
            throw new RuntimeException("Module directory not found: {$sourceDir}");
        }

        $outputPath = $this->projectRoot . '/modules/' . $moduleName . '-' . $manifest->version . '.' . $format;
        $this->createArchive($sourceDir, $outputPath);

        return $outputPath;
    }

    /**
     * Verify module package integrity using HMAC-SHA256 signature.
     * Verifies the manifest content (excluding signature field) with MODULE_SIGNING_KEY.
     *
     * Two trust paths:
     *  - $sha256Pinned (official marketplace channel): the archive hash was
     *    already checked against the install-request response fetched over TLS
     *    from the configured base_url — both the hash and the archive come from
     *    the same authenticated channel, so the shared HMAC key adds nothing and
     *    is not required. This is what makes one-click marketplace installs work
     *    out of the box on a fresh installation, where the key is never set.
     *  - No pin (direct URL / uploaded ZIP): fail-closed on MODULE_SIGNING_KEY
     *    (audit C-2) — unsigned packages are rejected by default.
     */
    private function verifyPackageSignature(array $manifestData, bool $sha256Pinned = false): void
    {
        $signingKey = trim((string)(getenv('MODULE_SIGNING_KEY') ?: ''));
        if ($signingKey === '') {
            if ($sha256Pinned) {
                return;
            }
            throw new ModuleSigningKeyMissingException();
        }

        $signature = (string)($manifestData['signature'] ?? '');
        if ($signature === '') {
            throw new RuntimeException("Module package signature is missing and verification is required");
        }

        // Verify against manifest content excluding the signature field itself
        $verifyData = $manifestData;
        unset($verifyData['signature']);
        $payload = json_encode($verifyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new RuntimeException("Cannot encode manifest for signature verification");
        }

        $expected = hash_hmac('sha256', $payload, $signingKey);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException("Module package signature verification failed");
        }
    }

    private function download(string $url, string $dest): void
    {
        $validator = new UrlSafetyValidator();
        $result = $validator->validateProviderUrl($url, true, ['http', 'https']);
        if (!$result['ok']) {
            throw new RuntimeException("Invalid or unsafe URL for module download: {$result['code']}");
        }
        $resolvedIps = (array)($result['resolved_ips'] ?? []);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_USERAGENT => 'CRM-Module-Installer/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        // SEC-002: DNS pinning — force cURL to use validated IP
        if (!empty($resolvedIps) && defined('CURLOPT_RESOLVE')) {
            $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
            $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: 'https'));
            $port = (int)(parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
            if ($host !== '') {
                $resolveEntry = $host . ':' . $port . ':' . trim((string)$resolvedIps[0]);
                curl_setopt($ch, CURLOPT_RESOLVE, [$resolveEntry]);
            }
        }

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || $error !== '') {
            throw new RuntimeException("Failed to download module: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Module download failed with HTTP {$httpCode}");
        }

        file_put_contents($dest, $content);

        // SEC-008: Verify the body is actually an archive before extraction.
        // Catches SSRF targets returning HTML/JSON/plaintext, and prevents
        // a malicious mirror from delivering a PHP payload masquerading as a
        // module. Magic-byte signatures: ZIP "PK\x03/05/07 \x04/06/08", or
        // gzip "\x1f\x8b\x08" (which is also the entry header for .tar.gz).
        $this->validateArchiveMagic($dest);
    }

    /**
     * Reject downloaded archives whose first bytes do not match a known
     * archive signature. Removes the file on failure so a partial download
     * does not linger in the temp directory.
     */
    private function validateArchiveMagic(string $path): void
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open downloaded archive for magic-byte validation: {$path}");
        }
        $header = fread($handle, 4);
        fclose($handle);
        if ($header === false || strlen($header) < 4) {
            @unlink($path);
            throw new RuntimeException("Downloaded archive is too small for magic-byte validation");
        }

        $isZip = ($header[0] === 'P' && $header[1] === 'K'
            && in_array($header[2] . $header[3], ["\x03\x04", "\x05\x06", "\x07\x08"], true));
        $isGzip = ($header[0] === "\x1f" && $header[1] === "\x8b" && $header[2] === "\x08");

        if (!$isZip && !$isGzip) {
            @unlink($path);
            throw new RuntimeException("Downloaded file is not a recognized archive format (magic-byte check failed)");
        }
    }

    private function extract(string $archive, string $destDir): void
    {
        $realDestDir = realpath($destDir);
        // Сравниваем реальные пути: на macOS /var — симлинк на /private/var,
        // поэтому realpath('/var/folders/…') не начинается с сырого sys_get_temp_dir().
        $realTempDir = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
        if ($realDestDir === false || !str_starts_with($realDestDir, $realTempDir)) {
            throw new RuntimeException('Invalid extraction directory');
        }

        if (str_ends_with($archive, '.zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new RuntimeException("Cannot open ZIP archive: {$archive}");
            }

            // Validate each entry before extraction (zip-slip protection).
            // The entry count must come from numFiles: numEntries does not exist
            // on every PHP build (PHP 8.2 here reports it as an undefined
            // property), and an undefined bound evaluated to null made this loop
            // run zero times — so traversal entries were only caught by the
            // post-extraction realpath check, i.e. after extractTo() had already
            // written the files outside $destDir.
            $entryCount = $zip->numFiles;
            for ($i = 0; $i < $entryCount; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) continue;
                $normalizedName = str_replace('\\', '/', $name);
                // Block path traversal, absolute paths, and symlinks
                if (
                    str_contains($normalizedName, '..')
                    || str_starts_with($normalizedName, '/')
                    || preg_match('/^[a-zA-Z]:\\//', $normalizedName)
                ) {
                    $zip->close();
                    throw new RuntimeException("Archive entry contains path traversal: {$name}");
                }
            }

            $zip->extractTo($destDir);
            $zip->close();

            // Verify no files escaped the target directory
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($destDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $realPath = $file->getRealPath();
                if ($realPath === false || !str_starts_with($realPath, $realDestDir)) {
                    throw new RuntimeException('Archive extraction escaped target directory');
                }
            }

            return;
        }

        if (str_ends_with($archive, '.tar.gz') || str_ends_with($archive, '.tgz')) {
            $phar = new \PharData($archive);

            // Validate tar entries before extraction
            foreach (new \RecursiveIteratorIterator($phar) as $entry) {
                $entryPath = str_replace('\\', '/', $entry->getPathname());
                if (str_contains($entryPath, '..') || str_starts_with($entryPath, '/')) {
                    throw new RuntimeException("Archive entry contains path traversal: {$entryPath}");
                }
            }

            $phar->extractTo($destDir);

            // Verify no files escaped
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($destDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $realPath = $file->getRealPath();
                if ($realPath === false || !str_starts_with($realPath, $realDestDir)) {
                    throw new RuntimeException('Archive extraction escaped target directory');
                }
            }

            return;
        }

        throw new RuntimeException("Unsupported archive format: {$archive}");
    }

    private function createArchive(string $sourceDir, string $outputPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP archive: {$outputPath}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function copyDir(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            @mkdir($dest, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . '/' . $item;
            $dst = $dest . '/' . $item;

            if (is_dir($src)) {
                $this->copyDir($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->cleanDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
