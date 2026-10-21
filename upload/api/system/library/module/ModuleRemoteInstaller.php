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
    private const MAX_PACKAGE_BYTES = 104857600;
    private const MAX_ARCHIVE_ENTRIES = 10000;
    private const MAX_EXTRACTED_BYTES = 536870912;
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

    public function updateFromUrl(string $url, string $expectedName, string $expectedVersion, string $coreVersion, string $expectedSha256): string
    {
        $tmpDir = sys_get_temp_dir() . '/crm_module_update_' . bin2hex(random_bytes(8));
        if (!@mkdir($tmpDir, 0700, true)) {
            throw new RuntimeException('Cannot create temporary module update directory');
        }
        try {
            $archive = $tmpDir . '/module.zip';
            $this->download($url, $archive);
            return $this->updateFromFile($archive, $expectedName, $expectedVersion, $coreVersion, $expectedSha256);
        } finally {
            $this->cleanDir($tmpDir);
        }
    }

    /**
     * Update an already installed module from a marketplace package.
     * The current directory is kept as a sibling backup until validation and
     * migrations succeed. The registry row (including config/active state) is
     * intentionally updated in place only after the new code is ready.
     */
    public function updateFromFile(
        string $filePath,
        string $expectedName,
        string $expectedVersion,
        string $coreVersion,
        string $expectedSha256
    ): string {
        if (!is_file($filePath) || !ModuleMarketplaceClient::isValidCode($expectedName)) {
            throw new RuntimeException('Invalid module update package');
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $expectedSha256)) {
            throw new RuntimeException('Invalid sha256 supplied for package verification');
        }
        $actual = hash_file('sha256', $filePath);
        if (!is_string($actual) || !hash_equals(strtolower($expectedSha256), strtolower($actual))) {
            throw new ModulePackageHashMismatchException(strtolower($expectedSha256));
        }

        $currentDir = $this->projectRoot . '/modules/' . $expectedName;
        $modulesDir = realpath($this->projectRoot . '/modules');
        $currentReal = realpath($currentDir);
        if ($modulesDir === false || $currentReal === false || dirname($currentReal) !== $modulesDir || is_link($currentDir)) {
            throw new RuntimeException('Installed module directory is missing or unsafe');
        }
        $currentManifest = json_decode((string)@file_get_contents($currentDir . '/manifest.json'), true);
        if (!is_array($currentManifest) || (string)($currentManifest['name'] ?? '') !== $expectedName) {
            throw new RuntimeException('Installed module manifest does not match its directory');
        }
        $oldVersion = is_array($currentManifest) ? (string)($currentManifest['version'] ?? '') : '';
        if ($oldVersion === '' || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $oldVersion)) {
            throw new RuntimeException('Installed module version is invalid');
        }
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $expectedVersion)
            || version_compare($expectedVersion, $oldVersion, '<=')) {
            throw new RuntimeException('Marketplace version is not newer than the installed version');
        }

        $lockDir = $this->projectRoot . '/storage_api/locks';
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0700, true) && !is_dir($lockDir)) {
            throw new RuntimeException('Cannot create private module update lock directory');
        }
        if (is_link($lockDir)) {
            throw new RuntimeException('Unsafe module update lock directory');
        }
        $lockPath = $lockDir . '/module-' . hash('sha256', $expectedName) . '.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Unsafe module update lock file');
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open private module update lock');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('A module update is already running');
        }

        $extractDir = sys_get_temp_dir() . '/crm_module_update_' . bin2hex(random_bytes(8));
        if (!@mkdir($extractDir, 0700, true)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            throw new RuntimeException('Cannot create temporary update directory');
        }
        $stageDir = $modulesDir . '/.crm-update-' . bin2hex(random_bytes(8));
        $backupDir = $modulesDir . '/.crm-backup-' . bin2hex(random_bytes(8));
        $swapped = false;
        try {
            $this->extract($filePath, $extractDir);
            $manifestPath = $extractDir . '/manifest.json';
            $manifestData = json_decode((string)@file_get_contents($manifestPath), true);
            if (!is_array($manifestData) || (string)($manifestData['name'] ?? '') !== $expectedName
                || (string)($manifestData['version'] ?? '') !== $expectedVersion) {
                throw new ModulePackageMismatchException((string)($manifestData['name'] ?? ''), $expectedName);
            }

            // Marketplace SHA pin is the package's authenticated channel proof.
            $this->verifyPackageSignature($manifestData, true);
            $codeValidator = new ModuleCodeValidator();
            $violations = $codeValidator->validateModule($extractDir);
            if ($violations !== []) {
                throw new RuntimeException('Module update contains forbidden code');
            }
            if (!is_array($manifestData)) {
                throw new RuntimeException('Invalid module update manifest');
            }
            $candidateManifest = Manifest::fromArray($manifestData);
            $candidateErrors = $this->pm->validate($candidateManifest);
            if ($candidateErrors !== []) {
                throw new RuntimeException('Updated module manifest is invalid');
            }
            if (!$this->pm->checkCoreCompatibility($candidateManifest, $coreVersion)) {
                throw new RuntimeException('Updated module is incompatible with this CRM core version');
            }
            $minPhp = (string)($manifestData['min_php_version'] ?? '');
            if ($minPhp !== '' && version_compare(PHP_VERSION, $minPhp, '<')) {
                throw new RuntimeException('Updated module requires a newer PHP version');
            }
            if (!$this->copyDir($extractDir, $stageDir)) {
                throw new RuntimeException('Cannot stage module update');
            }
            // Hidden sibling staging/backup folders are denied by modules/.htaccess.
            if (file_put_contents($stageDir . '/.htaccess', "Require all denied\n", LOCK_EX) === false) {
                throw new RuntimeException('Cannot protect staged module files');
            }

            if (!@rename($currentDir, $backupDir)) {
                throw new RuntimeException('Cannot preserve current module before update');
            }
            if (!@rename($stageDir, $currentDir)) {
                if (!@rename($backupDir, $currentDir)) {
                    $this->mc->setInactive($expectedName);
                    throw new RuntimeException('Cannot activate the update or restore the previous module; backup retained for recovery');
                }
                throw new RuntimeException('Cannot activate staged module files');
            }
            $swapped = true;

            $this->pm->discover();
            $manifest = $this->pm->getManifest($expectedName);
            if ($manifest === null || $manifest->version !== $expectedVersion) {
                throw new RuntimeException('Updated module manifest failed discovery');
            }
            $errors = $this->pm->validate($manifest);
            if ($errors !== []) {
                throw new RuntimeException('Updated module validation failed');
            }
            if ($manifest->migrations !== null) {
                $migrationPath = $currentDir . '/' . $manifest->migrations;
                $realMigrationPath = realpath($migrationPath);
                $realCurrentDir = realpath($currentDir);
                if ($realMigrationPath === false || $realCurrentDir === false
                    || !is_dir($realMigrationPath)
                    || !str_starts_with($realMigrationPath, $realCurrentDir . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Updated module migration directory is missing or unsafe');
                }
                $result = $this->mm->migrate($expectedName, $realMigrationPath);
                if ($result['errors'] !== []) {
                    throw new RuntimeException('Module update migration failed');
                }
            }

            $this->mc->setVersion($expectedName, $expectedVersion);
            // Do not reset module configuration or the active flag.
            $this->mc->initFromManifest($expectedName, $manifest);
            $this->cleanDir($backupDir);
            $this->pm->discover();
            return $expectedName;
        } catch (\Throwable $e) {
            if ($swapped) {
                // A migration may partially apply DDL on MySQL/shared hosting.
                // Restore code and deactivate the module so no possibly
                // incompatible implementation executes until an admin reviews it.
                $this->cleanDir($currentDir);
                if (is_dir($currentDir) || !is_dir($backupDir) || !@rename($backupDir, $currentDir)) {
                    $this->mc->setVersion($expectedName, $oldVersion);
                    $this->mc->setInactive($expectedName);
                    $this->pm->discover();
                    throw new RuntimeException('Update failed and automatic file restoration was incomplete; backup retained for recovery', 0, $e);
                }
                $this->mc->setVersion($expectedName, $oldVersion);
                $this->mc->setInactive($expectedName);
                $this->pm->discover();
            }
            throw $e;
        } finally {
            $this->cleanDir($stageDir);
            $this->cleanDir($extractDir);
            flock($lock, LOCK_UN);
            fclose($lock);
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
                $fullCode = (string)($manifestData['full_code'] ?? '');
                if ($fullCode === $expectedName) {
                    $moduleName = $expectedName;
                } else {
                    throw new ModulePackageMismatchException($moduleName, $expectedName);
                }
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
        if ($sha256Pinned) {
            return;
        }

        $signingKey = trim((string)(getenv('MODULE_SIGNING_KEY') ?: ''));
        if ($signingKey === '') {
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

        $handle = @fopen($dest, 'xb');
        if ($handle === false) {
            curl_close($ch);
            throw new RuntimeException('Cannot create temporary module archive');
        }
        @chmod($dest, 0600);
        $downloaded = 0;
        $tooLarge = false;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use ($handle, &$downloaded, &$tooLarge): int {
            $downloaded += strlen($chunk);
            if ($downloaded > self::MAX_PACKAGE_BYTES) {
                $tooLarge = true;
                return 0;
            }
            $written = fwrite($handle, $chunk);
            return $written === false ? 0 : $written;
        });
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($content === false || $error !== '') {
            @unlink($dest);
            if ($tooLarge) {
                throw new RuntimeException('Module package exceeds the 100 MiB download limit');
            }
            throw new RuntimeException("Failed to download module: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            @unlink($dest);
            throw new RuntimeException("Module download failed with HTTP {$httpCode}");
        }

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
            if ($entryCount > self::MAX_ARCHIVE_ENTRIES) {
                $zip->close();
                throw new RuntimeException('Archive contains too many entries');
            }
            $totalUncompressedBytes = 0;
            for ($i = 0; $i < $entryCount; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) continue;
                $stat = $zip->statIndex($i);
                $entrySize = max(0, (int)($stat['size'] ?? 0));
                $totalUncompressedBytes += $entrySize;
                if ($entrySize > self::MAX_PACKAGE_BYTES || $totalUncompressedBytes > self::MAX_EXTRACTED_BYTES) {
                    $zip->close();
                    throw new RuntimeException('Archive expands beyond the allowed size');
                }
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
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $fileType = ($attributes >> 16) & 0170000;
                    if ($fileType === 0120000) {
                        $zip->close();
                        throw new RuntimeException("Archive entry is a symbolic link: {$name}");
                    }
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

    private function copyDir(string $source, string $dest): bool
    {
        if (!is_dir($dest)) {
            if (!@mkdir($dest, 0755, true) && !is_dir($dest)) return false;
        }

        $items = scandir($source);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . '/' . $item;
            $dst = $dest . '/' . $item;
            if (is_link($src)) return false;

            if (is_dir($src)) {
                if (!$this->copyDir($src, $dst)) return false;
            } elseif (!copy($src, $dst)) {
                return false;
            }
        }
        return true;
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
            if (is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->cleanDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
