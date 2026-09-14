<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleCodeValidator
{
    /**
     * Constructs this scan rejects: code execution, process spawning and the
     * PHP escape hatches. Filesystem calls are deliberately NOT part of the list
     * — `file_put_contents()`, `unlink()`, `rmdir()`, `chmod()` and `chown()` are
     * ordinary module work (exports, temp files, cleanup) with no sandbox value:
     * `fopen()` / `fwrite()` / `rename()` / `mkdir()` are allowed anyway, so
     * blocking them only made published modules uninstallable (the marketplace
     * release of crm.activecollab-migration was rejected for calling `unlink()`
     * on its own temporary files).
     *
     * `include` / `require` targets are not part of the list: they are language
     * constructs (T_INCLUDE, T_REQUIRE) rather than T_STRING names, so a token
     * scan cannot see them at all, and module bootstrapping legitimately requires
     * its own files.
     *
     * @var array<int, string>
     */
    private array $forbiddenFunctions = [
        'eval', 'exec', 'system', 'shell_exec', 'passthru',
        'popen', 'proc_open', 'pcntl_exec', 'assert',
        'create_function',
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
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i])) {
                $line = $tokens[$i][2];
            }

            // eval() is a language construct (T_EVAL), so it never reaches the
            // T_STRING branch below and used to go unreported entirely.
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_EVAL) {
                $violations[] = [
                    'file' => $filePath,
                    'line' => $line,
                    'function' => 'eval',
                ];
                continue;
            }

            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING) {
                continue;
            }

            $funcName = strtolower($tokens[$i][1]);
            if (!in_array($funcName, $this->forbiddenFunctions, true)) {
                continue;
            }

            // Only a CALL to the global function is a violation. A method with
            // the same name ($client->exec(), FileSystem::exec()), a declaration
            // (function exec()) or a plain constant is not.
            if (!$this->isGlobalFunctionCall($tokens, $i)) {
                continue;
            }

            $violations[] = [
                'file' => $filePath,
                'line' => $line,
                'function' => $tokens[$i][1],
            ];
        }

        return $violations;
    }

    /**
     * Whether the T_STRING at $index is a call of the global function it names.
     *
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function isGlobalFunctionCall(array $tokens, int $index): bool
    {
        $previous = $this->previousSignificantToken($tokens, $index);

        if ($previous !== null) {
            if (is_string($previous)) {
                // "->" / "?->" / "::" seen as raw characters.
                if ($previous === '>' || $previous === ':') {
                    return false;
                }
            } elseif (in_array($previous[0], $this->nonCallPredecessors(), true)) {
                return false;
            }
        }

        // A call must be followed by an opening parenthesis.
        return $this->nextSignificantToken($tokens, $index) === '(';
    }

    /**
     * Token types that turn a matching name into something other than a call of
     * the global function: a declaration, a member/static call, a constant.
     *
     * @return array<int, int>
     */
    private function nonCallPredecessors(): array
    {
        $types = [T_FUNCTION, T_CONST, T_NEW];
        foreach (['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON'] as $name) {
            if (defined($name)) {
                $types[] = constant($name);
            }
        }

        return $types;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     * @return array{0:int,1:string,2:int}|string|null
     */
    private function previousSignificantToken(array $tokens, int $index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if ($this->isIgnorableToken($tokens[$i])) {
                continue;
            }
            return $tokens[$i];
        }

        return null;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     * @return array{0:int,1:string,2:int}|string|null
     */
    private function nextSignificantToken(array $tokens, int $index)
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            if ($this->isIgnorableToken($tokens[$i])) {
                continue;
            }
            return $tokens[$i];
        }

        return null;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    private function isIgnorableToken($token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        return $token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT;
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
