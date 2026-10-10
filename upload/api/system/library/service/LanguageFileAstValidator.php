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
 *
 * SEC note (audit 2026-10): a token-shape allowlist alone is not enough.
 * PHP executes a string literal followed by parentheses as a callable:
 *
 *     <?php "system"("id"); return [];
 *
 * produces only allowed tokens (T_CONSTANT_ENCAPSED_STRING, "(", ")"), so
 * detection must additionally model call syntax and statement structure.
 * Both are enforced below: validateStructure() requires exactly one
 * `return <array literal>;` statement, and a string literal immediately
 * followed by "(" is rejected as a callable.
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
        // SEC: T_CLOSE_TAG removed from the allowlist. No bundled dictionary
        // uses a closing tag, and the close-tag/parser interaction has a long
        // history of turning "harmless" tokens into execution.
    ];

    /**
     * Single-character punctuation tokens allowed in literal array declarations.
     * SEC: "(" and ")" remain allowed only because `array ( ... )` (var_export
     * style) uses them; the statement-structure check guarantees they never
     * form a call.
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

        // Significant stream: drop whitespace and comments so structural
        // checks and call detection see the real statement shape.
        $stream = [];
        foreach ($tokens as $token) {
            if (is_array($token) && isset(self::ALLOWED_TOKEN_TYPES[$token[0]])
                && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $stream[] = $token;
        }

        $hasReturn = false;

        // 3. Token allowlist pass
        foreach ($stream as $token) {
            if (is_string($token)) {
                if (!isset(self::ALLOWED_CHARS[$token])) {
                    $violations[] = [
                        'line' => 1,
                        'message' => sprintf('Forbidden character "%s" in declarative language file.', $token),
                        'token' => $token,
                    ];
                }
                continue;
            }

            [$tokenId, $tokenText, $tokenLine] = $token;

            if ($tokenId === T_RETURN) {
                $hasReturn = true;
                continue;
            }

            if ($tokenId === T_STRING) {
                $lower = strtolower($tokenText);
                if (!isset(self::ALLOWED_IDENTIFIERS[$lower])) {
                    $violations[] = [
                        'line' => $tokenLine,
                        'message' => sprintf('Forbidden identifier/function call "%s" in language file.', $tokenText),
                        'token' => 'T_STRING:' . $tokenText,
                    ];
                }
                continue;
            }

            if (!isset(self::ALLOWED_TOKEN_TYPES[$tokenId])) {
                $tokenName = token_name($tokenId);
                $violations[] = [
                    'line' => $tokenLine,
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
            return $violations;
        }

        if ($violations !== []) {
            // Structure checks only make sense on an allowlisted stream.
            return $violations;
        }

        // 4. Call detection: a constant string immediately followed by "(" is a
        //    callable in PHP ("system"("id")). Every other allowed token shape
        //    cannot form a call: identifiers are limited to true/false/null/
        //    strict_types, and no variables, concatenations or interpolations
        //    survive the allowlist above.
        $count = count($stream);
        for ($i = 0; $i < $count - 1; $i++) {
            $current = $stream[$i];
            $next = $stream[$i + 1];
            if (is_array($current) && $current[0] === T_CONSTANT_ENCAPSED_STRING && $next === '(') {
                $violations[] = [
                    'line' => $current[2],
                    'message' => 'String literal used as a callable (literal followed by "(") in language file.',
                    'token' => 'CALLABLE_STRING_LITERAL',
                ];
            }
        }

        // 5. Statement structure: exactly `declare(strict_types=1);` (optional)
        //    followed by a single `return <array literal>;` and nothing else.
        foreach ($this->validateStructure($stream) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * Structural validation of the significant token stream: the file must be
     * one return statement whose value is an array literal (square brackets or
     * the `array ( ... )` form), terminated by ";" with nothing after it.
     *
     * @param array<int, array{0:int,1:string,2:int}|string> $stream
     * @return array<int, array{line: int, message: string, token: string}>
     */
    private function validateStructure(array $stream): array
    {
        $violations = [];
        $fail = static function (string $message, string $token = 'STRUCTURE') use (&$violations): void {
            $violations[] = ['line' => 1, 'message' => $message, 'token' => $token];
        };

        $count = count($stream);
        if ($count === 0 || !(is_array($stream[0]) && $stream[0][0] === T_OPEN_TAG)) {
            $fail('Language file must begin with a <?php opening tag.', 'T_OPEN_TAG');
            return $violations;
        }

        $i = 1;

        // Optional declare(strict_types=1);
        if (isset($stream[$i]) && is_array($stream[$i]) && $stream[$i][0] === T_DECLARE) {
            $depth = 0;
            $closed = false;
            for (; $i < $count; $i++) {
                $token = $stream[$i];
                $text = is_array($token) ? $token[1] : $token;
                if ($text === '(') {
                    $depth++;
                } elseif ($text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $closed = true;
                    }
                } elseif ($text === ';' && $depth === 0) {
                    $i++;
                    break;
                }
            }
            if (!$closed) {
                $fail('Malformed declare() statement in language file.', 'T_DECLARE');
                return $violations;
            }
        }

        // Single return statement.
        if (!isset($stream[$i]) || !(is_array($stream[$i]) && $stream[$i][0] === T_RETURN)) {
            $fail('Language file must contain exactly one return statement of an array literal.', 'T_RETURN');
            return $violations;
        }
        $i++;

        // The returned value must be an array literal.
        if (!isset($stream[$i])) {
            $fail('Missing array literal after return.', 'T_ARRAY');
            return $violations;
        }
        $open = $stream[$i];
        $openText = is_array($open) ? $open[1] : $open;
        $closeChar = null;
        if ($openText === '[') {
            $closeChar = ']';
        } elseif (is_array($open) && $open[0] === T_ARRAY) {
            $closeChar = ')';
            $i++;
            if (!isset($stream[$i]) || $stream[$i] !== '(') {
                $fail('Malformed array() literal in language file.', 'T_ARRAY');
                return $violations;
            }
        } else {
            $fail('Language file must return an array literal.', 'T_ARRAY');
            return $violations;
        }

        // Walk the literal: only literal values, keys and nesting are allowed
        // inside it, and it must be balanced.
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $stream[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '[' || $text === '(') {
                $depth++;
                continue;
            }
            if ($text === ']' || $text === ')') {
                $depth--;
                if ($depth === 0) {
                    $i++;
                    break;
                }
                continue;
            }
            if (is_array($token)) {
                if (!in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_DOUBLE_ARROW, T_STRING, T_ARRAY], true)) {
                    $fail(sprintf('Forbidden token "%s" inside array literal.', token_name($token[0])), 'ARRAY_ELEMENT');
                }
                continue;
            }
            if ($text !== ',') {
                $fail(sprintf('Forbidden character "%s" inside array literal.', $text), 'ARRAY_ELEMENT');
            }
        }
        if ($depth !== 0) {
            $fail('Unterminated array literal in language file.', 'ARRAY_ELEMENT');
            return $violations;
        }
        if ($closeChar === ')' && $text !== ')') {
            $fail('Unterminated array() literal in language file.', 'ARRAY_ELEMENT');
            return $violations;
        }

        // The return statement must end with ";".
        if (!isset($stream[$i]) || $stream[$i] !== ';') {
            $fail('Return statement must end with ";".', ';');
            return $violations;
        }
        $i++;

        // Nothing may follow the single statement.
        if ($i < $count) {
            $fail('Only a single return statement is allowed in a language file.', 'EXTRA_STATEMENT');
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
