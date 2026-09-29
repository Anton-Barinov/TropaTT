<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

/**
 * Strict AST/token-level validator for CRM translation files.
 *
 * Translation files in TropaTT CRM are declarative dictionary files:
 *   <?php
 *   return [
 *       'key' => 'value',
 *       ...
 *   ];
 *
 * This validator strictly rejects ANY executable code, function calls,
 * classes, variables, superglobals, system calls, or includes.
 */
final class LanguageFileAstValidator
{
    /**
     * Allowed PHP token IDs in translation files.
     * @var array<int, bool>
     */
    private const ALLOWED_TOKEN_TYPES = [
        T_OPEN_TAG => true,
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
        T_RETURN => true,
        T_ARRAY => true,
        T_CONSTANT_ENCAPSED_STRING => true,
        T_LNUMBER => true,
        T_DNUMBER => true,
        T_DOUBLE_ARROW => true,
        T_DECLARE => true,
        T_CLOSE_TAG => true,
    ];

    /**
     * Single-character punctuation tokens allowed in literal array declarations.
     * @var array<string, bool>
     */
    private const ALLOWED_CHARS = [
        '[' => true,
        ']' => true,
        '(' => true,
        ')' => true,
        ',' => true,
        ';' => true,
        '=' => true,
    ];

    /**
     * Allowed literal identifiers (when tokenized as T_STRING).
     * @var array<string, bool>
     */
    private const ALLOWED_IDENTIFIERS = [
        'true' => true,
        'false' => true,
        'null' => true,
        'strict_types' => true,
    ];

    /**
     * Validate translation file content.
     *
     * @return array<int, array{line: int, message: string, token: string}> Empty if clean.
     */
    public function validateCode(string $code, string $filename = 'inline'): array
    {
        $violations = [];

        // 1. Basic structural checks
        if (!str_contains($code, '<?php')) {
            $violations[] = [
                'line' => 1,
                'message' => 'Language file must begin with <?php opening tag.',
                'token' => 'T_OPEN_TAG',
            ];
            return $violations;
        }

        // 2. Tokenize and enforce strict declarative whitelist
        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $violations[] = [
                'line' => $e->getLine(),
                'message' => 'PHP syntax error in language file: ' . $e->getMessage(),
                'token' => 'PARSE_ERROR',
            ];
            return $violations;
        }

        $hasReturn = false;
        $line = 1;

        foreach ($tokens as $token) {
            if (is_string($token)) {
                if (!isset(self::ALLOWED_CHARS[$token])) {
                    $violations[] = [
                        'line' => $line,
                        'message' => sprintf('Forbidden character "%s" in declarative language file.', $token),
                        'token' => $token,
                    ];
                }
                continue;
            }

            [$tokenId, $tokenText, $tokenLine] = $token;
            $line = $tokenLine;

            if ($tokenId === T_RETURN) {
                $hasReturn = true;
                continue;
            }

            if ($tokenId === T_STRING) {
                $lower = strtolower($tokenText);
                if (!isset(self::ALLOWED_IDENTIFIERS[$lower])) {
                    $violations[] = [
                        'line' => $line,
                        'message' => sprintf('Forbidden identifier/function call "%s" in language file.', $tokenText),
                        'token' => 'T_STRING:' . $tokenText,
                    ];
                }
                continue;
            }

            if (!isset(self::ALLOWED_TOKEN_TYPES[$tokenId])) {
                $tokenName = token_name($tokenId);
                $violations[] = [
                    'line' => $line,
                    'message' => sprintf('Forbidden token "%s" (%s) in language file.', $tokenName, $tokenText),
                    'token' => $tokenName,
                ];
            }
        }

        if (!$hasReturn && empty($violations)) {
            $violations[] = [
                'line' => 1,
                'message' => 'Language file must contain a return statement.',
                'token' => 'T_RETURN',
            ];
        }

        return $violations;
    }

    /**
     * Validate an actual file on disk.
     *
     * @return array<int, array{line: int, message: string, token: string}>
     */
    public function validateFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [
                [
                    'line' => 1,
                    'message' => 'File not found: ' . $filePath,
                    'token' => 'FILE_NOT_FOUND',
                ],
            ];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [
                [
                    'line' => 1,
                    'message' => 'Could not read file: ' . $filePath,
                    'token' => 'FILE_READ_ERROR',
                ],
            ];
        }

        return $this->validateCode($content, $filePath);
    }

    /**
     * Convenient boolean check for code.
     */
    public function isValid(string $code): bool
    {
        return empty($this->validateCode($code));
    }

    /**
     * Convenient boolean check for file.
     */
    public function isValidFile(string $filePath): bool
    {
        return empty($this->validateFile($filePath));
    }
}

