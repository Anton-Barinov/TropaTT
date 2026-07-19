<?php
declare(strict_types=1);

/**
 * SEC-009: PathGuard unit tests.
 *
 * Tests the fix for directory entries in zip archives.
 *
 * Run: php -d auto_prepend_file= api/tests/unit/path_guard_unit.php
 */

// Path from api/tests/unit/ to updater/src/Package/
require_once __DIR__ . '/../../../updater/src/Package/PathGuard.php';

use Updater\Package\PathGuard;

$passed = 0;
$failed = 0;
$errors = [];

function assertTrue(bool $condition, string $label): void
{
    global $passed, $failed, $errors;
    if ($condition) { $passed++; echo "  PASS: $label\n"; }
    else { $failed++; $errors[] = "FAIL: $label"; echo "  FAIL: $label\n"; }
}

function assertFalse(bool $condition, string $label): void
{
    assertTrue(!$condition, $label);
}

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed, $errors;
    if ($expected === $actual) { $passed++; echo "  PASS: $label\n"; }
    else {
        $failed++; $errors[] = "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        echo "  FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

function assertNull(mixed $value, string $label): void
{
    global $passed, $failed, $errors;
    if ($value === null) { $passed++; echo "  PASS: $label\n"; }
    else {
        $failed++; $errors[] = "FAIL: $label — expected null, got " . var_export($value, true);
        echo "  FAIL: $label — expected null, got " . var_export($value, true) . "\n";
    }
}

// Create PathGuard with one generic protected pattern to test isAllowed
$guard = new PathGuard([]);

echo "=== Допустимые пути (normalize) ===\n";
assertEquals('lib', $guard->normalize('lib'), "normalize('lib') should return 'lib'");
assertEquals('lib/sub', $guard->normalize('lib/sub'), "normalize('lib/sub') should return 'lib/sub'");
assertEquals('lib/file.php', $guard->normalize('lib/file.php'), "normalize('lib/file.php') should return 'lib/file.php'");
assertEquals('a.txt', $guard->normalize('a.txt'), "normalize('a.txt') should return 'a.txt'");

echo "=== Допустимые пути-директории (SEC-009 fix) ===\n";
assertEquals('lib', $guard->normalize('lib/'), "normalize('lib/') should return 'lib' (was null before fix)");
assertEquals('lib/sub', $guard->normalize('lib/sub/'), "normalize('lib/sub/') should return 'lib/sub' (was null before fix)");
assertEquals('a', $guard->normalize('a/'), "normalize('a/') should return 'a'");
assertEquals('deep/nested/path', $guard->normalize('deep/nested/path/'), "normalize('deep/nested/path/') should return 'deep/nested/path'");

echo "=== Отклоняемые пути ===\n";
assertNull($guard->normalize('../etc/passwd'), "normalize('../etc/passwd') should be null (path traversal)");
assertNull($guard->normalize('/etc/passwd'), "normalize('/etc/passwd') should be null (absolute path)");
assertNull($guard->normalize('C:/win'), "normalize('C:/win') should be null (Windows drive letter)");
assertNull($guard->normalize('a/../../b'), "normalize('a/../../b') should be null (path traversal)");
assertNull($guard->normalize("a\0b"), "normalize('a\\0b') should be null (null byte)");
assertNull($guard->normalize('.'), "normalize('.') should be null");
assertNull($guard->normalize('..'), "normalize('..') should be null");
assertNull($guard->normalize(''), "normalize('') should be null");
assertNull($guard->normalize('///'), "normalize('///') should be null");
assertNull($guard->normalize('a/./b'), "normalize('a/./b') should be null (cur dir segment)");

echo "=== isAllowed с защищёнными паттернами ===\n";
$guardProtected = new PathGuard(['config/*', '.env*']);
assertFalse($guardProtected->isAllowed('config/database.php'), "config/database.php should be forbidden");
assertFalse($guardProtected->isAllowed('.env'), ".env should be forbidden");
assertFalse($guardProtected->isAllowed('.env.local'), ".env.local should be forbidden");
assertTrue($guardProtected->isAllowed('src/app.php'), "src/app.php should be allowed");
assertTrue($guardProtected->isAllowed('lib/'), "lib/ should be allowed (directory, even without entries in list)");

echo "=== Edge cases ===\n";
assertNull($guard->normalize("prefix\x00suffix"), "normalize with null byte should be null");
assertNull($guard->normalize('C:\\\\Windows\\\\system32'), "Windows backslash paths should be null");
assertEquals('file with spaces.txt', $guard->normalize('file with spaces.txt'), "spaces in path should be preserved");

// RESULTS
echo "\n========== RESULTS ==========\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
if ($errors !== []) {
    echo "Errors:\n";
    foreach ($errors as $error) { echo "  - $error\n"; }
    exit(1);
}
echo "All tests passed!\n";
exit(0);
