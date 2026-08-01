<?php
declare(strict_types=1);

/**
 * SEC-001: File upload safety unit tests.
 *
 * Tests the complete upload security logic independently of the full
 * FileService dependency chain (no PDO / autoloader needed).
 *
 * Tests:
 * - Forbidden extensions are REJECTED (isForbidden returns true)
 * - Double extensions (file.php.jpg) are REJECTED
 * - Legitimate files are ACCEPTED
 * - All path segments are checked (not just last extension)
 * - .bin extension files retain safe status
 * - MIME-based rejection works correctly
 *
 * Run: php api/tests/unit/file_service_upload_safety_unit.php
 */

// --- Security logic extracted from FileService for testing ---

/**
 * Tests the same logic as FileService::isForbidden()
 */
function is_forbidden(string $fileName, string $detectedMimeType, array $forbiddenExtensions, array $quarantineMimePrefixes): bool
{
    // Check by extension — including all segments (double extensions)
    $cleanName = str_replace('\\', '/', $fileName);
    $cleanName = basename($cleanName);
    $parts = explode('.', $cleanName);
    foreach ($parts as $part) {
        $part = strtolower(trim($part));
        if ($part !== '' && in_array($part, $forbiddenExtensions, true)) {
            return true;
        }
    }

    // Check for compound extensions (e.g. user.ini, .htaccess, .user.ini)
    if (count($parts) >= 2) {
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if ($parts[$i] === '') continue; // skip leading dot parts
            $compound = strtolower(trim($parts[$i] . '.' . $parts[$i + 1]));
            if (in_array($compound, $forbiddenExtensions, true)) {
                return true;
            }
        }
    }

    // Check by detected MIME
    $detectedMime = strtolower(trim($detectedMimeType));
    if ($detectedMime !== '') {
        foreach ($quarantineMimePrefixes as $prefix) {
            $normalizedPrefix = strtolower(trim($prefix));
            if ($normalizedPrefix !== '' && str_starts_with($detectedMime, $normalizedPrefix)) {
                return true;
            }
        }

        $execSignatures = [
            'application/x-php',
            'text/x-php',
            'application/x-msdownload',
            'application/x-dosexec',
            'application/x-sh',
            'text/x-shellscript',
        ];
        if (in_array($detectedMime, $execSignatures, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Tests the same logic as FileService::quarantineMimeOverride()
 */
function quarantine_mime_override(string $path, string $originalMime, string $quarantineDir, array $quarantineExtensions): string
{
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedQuarantine = str_replace('\\', '/', rtrim($quarantineDir, '/'));
    $isQuarantined = $normalizedQuarantine !== '' && str_starts_with($normalizedPath, $normalizedQuarantine . '/');

    if ($isQuarantined) {
        return 'application/octet-stream';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== '' && in_array($ext, $quarantineExtensions, true)) {
        return 'application/octet-stream';
    }

    return $originalMime;
}

// --- Test values ---
$forbidden = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'pht',
    'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'msi', 'dll',
    'so', 'jsp', 'jspx', 'asp', 'aspx', 'ashx', 'asmx', 'cfm', 'htaccess', 'user.ini',
];

$quarantine = ['svg', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'swf'];

$mimePrefixes = ['application/x-php', 'application/x-sh', 'application/x-msdownload'];

$passed = 0;
$failed = 0;
$errors = [];

function assertTrue(bool $condition, string $label): void
{
    global $passed, $failed, $errors;
    if ($condition) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        $errors[] = "FAIL: $label";
        echo "  FAIL: $label\n";
    }
}

function assertFalse(bool $condition, string $label): void
{
    assertTrue(!$condition, $label);
}

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        $errors[] = "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        echo "  FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

// ===== TESTS =====

echo "=== testForbiddenExtensions ===\n";
$forbiddenNames = [
    'shell.php', 'test.phtml', 'script.phar', 'code.php5', 'code.php7',
    'code.php8', 'payload.pht', 'exploit.cgi', 'attack.pl', 'hack.py',
    'malicious.rb', 'evil.sh', 'danger.bash', 'run.bat', 'exec.cmd',
    'virus.com', 'setup.exe', 'lib.msi', 'trojan.dll', 'native.so',
    'pwn.jsp', 'pwn.jspx', 'hack.asp', 'hack.aspx', 'hack.ashx',
    'hack.asmx', 'coldfusion.cfm',
];

foreach ($forbiddenNames as $name) {
    assertTrue(is_forbidden($name, '', $forbidden, $mimePrefixes), "is_forbidden('$name') should be TRUE");
}

echo "=== testDoubleExtensions ===\n";
$doubleExtNames = [
    'file.php.jpg', 'shell.phtml.svg', 'code.phar.png', 'script.php5.jpeg',
    'exploit.php.gif', 'doc.php.pdf', 'image.php.webp', 'report.php7.zip',
    'backup.php8.tar', 'page.php.png',
];

foreach ($doubleExtNames as $name) {
    assertTrue(is_forbidden($name, '', $forbidden, $mimePrefixes), "is_forbidden('$name') should be TRUE (double ext)");
}

echo "=== testSafeExtensions ===\n";
$safeNames = [
    'document.pdf', 'image.jpg', 'photo.jpeg', 'picture.png',
    'animation.gif', 'video.mp4', 'audio.mp3', 'archive.zip',
    'spreadsheet.xlsx', 'report.docx', 'presentation.pptx',
    'notes.txt', 'data.csv', 'config.json', 'stylesheet.css', 'script.js',
];

foreach ($safeNames as $name) {
    assertFalse(is_forbidden($name, '', $forbidden, $mimePrefixes), "is_forbidden('$name') should be FALSE (safe)");
}

echo "=== testAllSegmentsForbidden ===\n";
$multiSegmentNames = [
    'some.php.file.pdf',
    'test.phtml.name.png',
    'dir.php.name.pdf',
];

foreach ($multiSegmentNames as $name) {
    assertTrue(is_forbidden($name, '', $forbidden, $mimePrefixes), "is_forbidden('$name') should be TRUE (segment)");
}

echo "=== testDoubleExtensionWithBin ===\n";
$binNames = [
    'malicious.php.bin',
    'shell.php5.bin',
    'exploit.phtml.bin',
];

foreach ($binNames as $name) {
    assertTrue(is_forbidden($name, '', $forbidden, $mimePrefixes), "is_forbidden('$name') should be TRUE (double ext .bin)");
}

echo "=== testMimeRejection ===\n";
assertTrue(
    is_forbidden('safe.pdf', 'application/x-php', $forbidden, $mimePrefixes),
    "PDF with PHP MIME should be forbidden"
);
assertTrue(
    is_forbidden('file.txt', 'text/x-shellscript', $forbidden, $mimePrefixes),
    "TXT with shellscript MIME should be forbidden"
);
assertFalse(
    is_forbidden('document.pdf', 'application/pdf', $forbidden, $mimePrefixes),
    "PDF with PDF MIME should not be forbidden"
);

echo "=== testQuarantineMimeOverride ===\n";
$quarantineDir = '/tmp/quarantine_test';

assertEquals(
    'application/octet-stream',
    quarantine_mime_override($quarantineDir . '/file.svg', 'image/svg+xml', $quarantineDir, $quarantine),
    "SVG in quarantine should get octet-stream"
);
assertEquals(
    'application/octet-stream',
    quarantine_mime_override($quarantineDir . '/fil_abc.bin', 'image/svg+xml', $quarantineDir, $quarantine),
    "Binary file in quarantine should get octet-stream"
);
assertEquals(
    'image/jpeg',
    quarantine_mime_override('/tmp/uploads_test/fil_abc.jpg', 'image/jpeg', $quarantineDir, $quarantine),
    "JPEG in uploads should keep original MIME"
);

echo "=== testExceptionExtension ===\n";
// .htaccess and .user.ini are special cases with no leading dot
assertTrue(
    is_forbidden('.htaccess', '', $forbidden, $mimePrefixes),
    "'.htaccess' should be forbidden"
);
assertTrue(
    is_forbidden('.user.ini', '', $forbidden, $mimePrefixes),
    "'.user.ini' should be forbidden"
);

// ===== RESULTS =====
echo "\n========== RESULTS ==========\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
if ($errors !== []) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
echo "All tests passed!\n";
exit(0);
