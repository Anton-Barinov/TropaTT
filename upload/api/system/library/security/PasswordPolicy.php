<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

/**
 * Single source of truth for the user-facing password rule.
 *
 * The rule that is *stated* to users is "at least 6 characters, including an
 * uppercase letter, a lowercase letter and a digit". Several call sites used to
 * implement it with ASCII-only classes (`[A-Z]`, `[a-z]`), so a password written
 * with Cyrillic (or any non-ASCII) letters was rejected even though it matched
 * the message — the external-invitation page reported "weak password" for a
 * perfectly valid password. Other sites additionally required a special
 * character that the message never mentions.
 *
 * The policy below is the stated rule and nothing more: 6+ characters
 * (multi-byte aware), an uppercase letter, a lowercase letter and a digit, using
 * Unicode character classes. No special character is required.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 6;
    public const MAX_LENGTH = 1024;

    /**
     * @return list<string> failing rules: too_short, too_long, missing_upper,
     *                      missing_lower, missing_digit
     */
    public static function failures(string $password): array
    {
        $failures = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            $failures[] = 'too_short';
        }
        if ($length > self::MAX_LENGTH) {
            $failures[] = 'too_long';
        }
        if (!self::matchesUnicodeClass($password, 'Lu', 'A-Z')) {
            $failures[] = 'missing_upper';
        }
        if (!self::matchesUnicodeClass($password, 'Ll', 'a-z')) {
            $failures[] = 'missing_lower';
        }
        if (!self::matchesUnicodeClass($password, 'Nd', '0-9')) {
            $failures[] = 'missing_digit';
        }

        return $failures;
    }

    public static function isStrong(string $password): bool
    {
        return self::failures($password) === [];
    }

    public static function isTooLong(string $password): bool
    {
        return mb_strlen($password) > self::MAX_LENGTH;
    }

    /**
     * Match a Unicode general category, falling back to the ASCII class when the
     * input is not valid UTF-8 (so malformed byte strings cannot slip through).
     */
    private static function matchesUnicodeClass(string $password, string $category, string $asciiClass): bool
    {
        $result = @preg_match('/\p{' . $category . '}/u', $password);
        if ($result === false) {
            return (bool)preg_match('/[' . $asciiClass . ']/', $password);
        }

        return $result === 1;
    }
}
