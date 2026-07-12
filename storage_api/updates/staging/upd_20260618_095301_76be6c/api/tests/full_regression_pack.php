<?php
declare(strict_types=1);

/**
 * CI-level full regression pack:
 * - contract live tests
 * - feature live tests
 * - regression live tests
 * - critical unit tests
 */

function collectPhpTests(string $dir): array
{
    $files = glob(rtrim($dir, '/\\') . '/*.php');
    if ($files === false) {
        return [];
    }

    $result = [];
    foreach ($files as $file) {
        if (is_file($file)) {
            $result[] = $file;
        }
    }

    sort($result);
    return $result;
}

function runCommand(string $cmd, string $cwd): array
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptor, $pipes, $cwd);
    if (!is_resource($proc)) {
        return ['code' => 1, 'output' => 'Failed to start process'];
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

function runPack(string $label, array $files, string $cwd): array
{
    $ok = 0;
    $fail = 0;
    $failed = [];

    echo "\n=== {$label} ===\n";

    foreach ($files as $file) {
        $name = str_replace($cwd . '/', '', $file);
        echo "[RUN] {$name}\n";
        $result = runCommand('php ' . escapeshellarg($file), $cwd);
        if ((int)$result['code'] === 0) {
            $ok++;
            echo "[OK] {$name}\n";
            continue;
        }

        $fail++;
        $failed[] = ['file' => $name, 'output' => (string)$result['output']];
        echo "[FAIL] {$name}\n";
        if ($result['output'] !== '') {
            echo $result['output'] . "\n";
        }
    }

    return [
        'ok' => $ok,
        'fail' => $fail,
        'failed' => $failed,
    ];
}

$projectRoot = dirname(__DIR__);
$testsRoot = __DIR__;

$contract = collectPhpTests($testsRoot . '/contract');
$feature = collectPhpTests($testsRoot . '/feature');
$regression = collectPhpTests($testsRoot . '/regression');

$criticalUnits = [
    $testsRoot . '/unit/security_unit.php',
    $testsRoot . '/unit/idempotency_service_unit.php',
    $testsRoot . '/unit/task_row_version_unit.php',
    $testsRoot . '/unit/entity_access_service_unit.php',
    $testsRoot . '/unit/file_service_unit.php',
    $testsRoot . '/unit/webhook_service_unit.php',
    $testsRoot . '/unit/workflow_service_unit.php',
    $testsRoot . '/unit/sla_service_unit.php',
    $testsRoot . '/unit/approval_service_unit.php',
    $testsRoot . '/unit/worklog_service_unit.php',
    $testsRoot . '/unit/knowledge_repository_unit.php',
];

$summary = [
    'ok' => 0,
    'fail' => 0,
    'failed' => [],
];

$packs = [
    'CONTRACT LIVE' => $contract,
    'FEATURE LIVE' => $feature,
    'REGRESSION LIVE' => $regression,
    'CRITICAL UNIT' => $criticalUnits,
];

foreach ($packs as $name => $files) {
    $packResult = runPack($name, $files, $projectRoot);
    $summary['ok'] += (int)$packResult['ok'];
    $summary['fail'] += (int)$packResult['fail'];
    foreach ((array)$packResult['failed'] as $item) {
        $summary['failed'][] = $item;
    }
}

echo "\n=== FULL REGRESSION PACK SUMMARY ===\n";
echo 'Total OK: ' . $summary['ok'] . "\n";
echo 'Total FAIL: ' . $summary['fail'] . "\n";

if ($summary['fail'] > 0) {
    echo "Failed tests:\n";
    foreach ($summary['failed'] as $item) {
        echo '- ' . $item['file'] . "\n";
    }
    exit(1);
}

echo "=== FULL REGRESSION PACK PASSED ===\n";

