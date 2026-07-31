<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleCodeValidator
{
    /** @var array<int, string> */
    private array $forbiddenFunctions = [
        'eval', 'exec', 'system', 'shell_exec', 'passthru',
        'popen', 'proc_open', 'pcntl_exec', 'assert',
        'create_function', 'include', 'file_put_contents',
        'unlink', 'rmdir', 'chmod', 'chown',
        'dl', 'ffi',
    ];

    /**
     * @return array<int, array{file: string, line: int, function: string}>
     */
    public function validateFile(string $filePath): array
    {
        $violations = [];
        $content = file_get_contents($filePath);
        if ($content === false) {
            return $violations;
        }

        $tokens = token_get_all($content);
        $line = 1;

        for ($i = 0; $i < count($tokens); $i++) {
            if (is_array($tokens[$i])) {
                $line = $tokens[$i][2];
            }

            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                $funcName = strtolower($tokens[$i][1]);
                if (in_array($funcName, $this->forbiddenFunctions, true)) {
                    $violations[] = [
                        'file' => $filePath,
                        'line' => $line,
                        'function' => $tokens[$i][1],
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @return array<int, array{file: string, line: int, function: string}>
     */
    public function validateModule(string $moduleDir): array
    {
        $violations = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $fileViolations = $this->validateFile($file->getPathname());
            $violations = array_merge($violations, $fileViolations);
        }

        return $violations;
    }
}
