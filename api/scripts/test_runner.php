<?php
declare(strict_types=1);

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/**
 * Integration test runner for repository integration coverage.
 * Usage: php scripts/test_runner.php [group]
 *
 * Groups:
 *   all       - Run all available integration tests
 *   sticky    - Run only Sticky Notes tests
 *   modules   - Run only Project Modules tests
 *   fast      - Same as 'all'
 */

function runTest(string $file, string $cwd): array
{
    $cmd = 'php ' . escapeshellarg($file);

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptor, $pipes, $cwd);
    if (!is_resource($proc)) {
        return ['code' => 1, 'output' => "Failed to start process: {$file}"];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($proc);
    $output = trim((string)$stdout . "\n" . (string)$stderr);

    return ['code' => $exitCode, 'output' => $output];
}

function extractResultSummary(string $output): array
{
    $passed = 0;
    $failed = 0;

    if (preg_match('/=== Results:\s*(\d+)\s+passed,\s*(\d+)\s+failed\s*===/i', $output, $m)) {
        $passed = (int)$m[1];
        $failed = (int)$m[2];
    } elseif (preg_match('/(\d+)\s+passed,\s*(\d+)\s+failed/i', $output, $m)) {
        $passed = (int)$m[1];
        $failed = (int)$m[2];
    } elseif (preg_match('/Passed:\s*(\d+)\s*\R\s*Failed:\s*(\d+)/i', $output, $m)) {
        $passed = (int)$m[1];
        $failed = (int)$m[2];
    } elseif (preg_match('/All tests passed!/i', $output) === 1) {
        $passed = preg_match_all('/\bPASS:/', $output);
    }

    return ['passed' => $passed, 'failed' => $failed];
}

$projectRoot = dirname(__DIR__, 2); // project root (two levels up from scripts/)
$testsDir = $projectRoot . '/api/tests';

$groups = [
    'sticky' => [
        'label' => 'Sticky Notes',
        'files' => [$testsDir . '/StickyNoteIntegrationTest.php'],
    ],
    'modules' => [
        'label' => 'Project Modules',
        'files' => [$testsDir . '/ProjectModuleIntegrationTest.php'],
    ],
    'knowledge' => [
        'label' => 'Knowledge Page Versions',
        'files' => [$testsDir . '/KnowledgePageVersionIntegrationTest.php'],
    ],
    'cycles' => [
        'label' => 'Work Cycles',
        'files' => [$testsDir . '/WorkCycleIntegrationTest.php'],
    ],
    'companies' => [
        'label' => 'Company Compatibility',
        'files' => [$testsDir . '/CompanyCompatibilityTest.php'],
    ],
];

$group = isset($_SERVER['argv'][1]) ? trim((string)$_SERVER['argv'][1]) : 'all';

// Composer exposes these compatibility commands. The project currently uses a
// single self-contained integration runner, so each alias executes its full
// available coverage instead of failing with an unknown group.
if (in_array($group, ['unit', 'integration', 'contract', 'openapi', 'e2e-web', 'live', 'full'], true)) {
    $group = 'all';
}

if ($group === 'all' || $group === 'fast') {
    $selectedFiles = [];
    foreach ($groups as $g) {
        foreach ($g['files'] as $f) {
            $selectedFiles[] = $f;
        }
    }
    $label = 'ALL MODULE INTEGRATION TESTS';
} elseif (isset($groups[$group])) {
    $selectedFiles = $groups[$group]['files'];
    $label = strtoupper($groups[$group]['label']) . ' TESTS';
} else {
    echo "Unknown group: {$group}\n";
    echo "Available: all, fast, sticky, modules, knowledge, cycles, companies\n";
    exit(1);
}

// Filter to existing files only
$existingFiles = [];
foreach ($selectedFiles as $file) {
    if (is_file($file)) {
        $existingFiles[] = $file;
    } else {
        echo "[SKIP] File not found: {$file}\n";
    }
}

if ($existingFiles === []) {
    echo "No test files found to run.\n";
    exit(1);
}

$totalPassed = 0;
$totalFailed = 0;
$allOutput = [];

echo str_repeat('=', 60) . "\n";
echo " {$label}\n";
echo str_repeat('=', 60) . "\n\n";

foreach ($existingFiles as $file) {
    $name = str_replace($projectRoot . '/', '', $file);
    echo "--- Running: {$name} ---\n";

    $result = runTest($file, $projectRoot);

    // Print output (but trim trailing newlines)
    $output = $result['output'];
    if ($output !== '') {
        echo $output . "\n";
    }

    $summary = extractResultSummary($output);
    $totalPassed += $summary['passed'];
    $totalFailed += $summary['failed'];

    $status = $result['code'] === 0 ? 'OK' : 'FAIL';
    echo "[{$status}] Exit code: {$result['code']}";
    if ($summary['passed'] > 0 || $summary['failed'] > 0) {
        echo " | {$summary['passed']} passed, {$summary['failed']} failed";
    }
    echo "\n\n";

    $allOutput[] = [
        'file' => $name,
        'status' => $status,
        'exit_code' => $result['code'],
        'passed' => $summary['passed'],
        'failed' => $summary['failed'],
    ];
}

echo str_repeat('=', 60) . "\n";
echo " RESULTS SUMMARY\n";
echo str_repeat('=', 60) . "\n";

foreach ($allOutput as $item) {
    $icon = $item['failed'] > 0 ? '❌' : '✅';
    echo " {$icon} {$item['file']}: {$item['passed']} passed, {$item['failed']} failed\n";
}

echo "\n";
echo " Total: {$totalPassed} passed, {$totalFailed} failed\n";
echo str_repeat('=', 60) . "\n";

exit($totalFailed > 0 ? 1 : 0);
