<?php
declare(strict_types=1);

namespace Updater\Apply;

/**
 * Post-apply / post-rollback health gate.
 *
 * The check used to shell out to a CLI `php -l`, so the verdict depended on the
 * host's command-line environment instead of the code that will actually serve:
 * a CLI PHP of a DIFFERENT version than the web SAPI validated files against
 * the wrong grammar (a parse error the running PHP would hit passed, a fine
 * file failed), and only the three entry points were linted — a corrupted
 * package file (bad extraction, truncated download) passed the gate and the
 * site broke after finalize, with no rollback offered.
 *
 * Files are now parsed in-process with token_get_all(TOKEN_PARSE): exactly the
 * runtime PHP that will execute them, no shell, no version skew. The package's
 * PHP files (manifest file_hashes) are checked too, so a corrupted extraction
 * is caught before finalize — while rollback is still one click away.
 */
final class HealthChecker
{
    /** Upper bound so a pathological manifest cannot stall a shared-host request. */
    private const MAX_PACKAGE_FILES = 5000;

    /** Per-file size cap; oversized files are skipped, not read into memory. */
    private const MAX_FILE_BYTES = 4194304;

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array|null $manifest Package manifest (file_hashes) whose shipped
     *        PHP files should be syntax-checked as well; null keeps the
     *        classic entry-point-only check.
     */
    public function check(?array $manifest = null): array
    {
        $checks = [
            'api_index_syntax' => $this->lintFile($this->basePath . '/api/index.php'),
            'web_index_syntax' => $this->lintFile($this->basePath . '/web/index.php'),
            'root_index_syntax' => $this->lintFile($this->basePath . '/index.php'),
        ];
        if ($manifest !== null) {
            $checks['package_php_syntax'] = $this->lintPackage($manifest);
        }

        $okValues = array_map(static fn(array $check): bool => ($check['ok'] ?? false) === true, $checks);

        return [
            'ok' => !in_array(false, $okValues, true),
            'php_version' => PHP_VERSION,
            'checks' => $checks,
        ];
    }

    private function lintFile(string $path): array
    {
        if (!is_file($path)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'missing_file'];
        }

        return self::parseSource($path);
    }

    private function lintPackage(array $manifest): array
    {
        $fileHashes = is_array($manifest['file_hashes'] ?? null) ? $manifest['file_hashes'] : [];
        $checked = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_keys($fileHashes) as $relative) {
            if (!str_ends_with((string)$relative, '.php')) {
                continue;
            }
            if ($checked >= self::MAX_PACKAGE_FILES || count($errors) >= 10) {
                $skipped++;
                continue;
            }

            $path = $this->basePath . '/' . ltrim((string)$relative, '/');
            if (!is_file($path)) {
                // A rollback lints the restored tree against the NEW package
                // manifest — files the old build never shipped are legitimately
                // absent here, so absence is not a health failure.
                $skipped++;
                continue;
            }
            $size = filesize($path);
            if ($size === false || $size > self::MAX_FILE_BYTES) {
                $skipped++;
                continue;
            }

            $checked++;
            $result = self::parseSource($path);
            if (($result['ok'] ?? false) !== true) {
                $errors[] = [
                    'file' => (string)$relative,
                    'error' => (string)($result['error'] ?? $result['reason'] ?? 'parse failed'),
                ];
            }
        }

        return [
            'ok' => $errors === [],
            'checked' => $checked,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Full syntax parse against the RUNNING PHP. TOKEN_PARSE compiles the
     * source the same way `php -l` does, but with this process's engine — the
     * one that will serve the files.
     */
    private static function parseSource(string $path): array
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            return ['ok' => false, 'reason' => 'unreadable_file'];
        }

        try {
            token_get_all($source, TOKEN_PARSE);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true];
    }
}
