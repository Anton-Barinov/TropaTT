<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$targetDir = $projectRoot . '/web/assets/js';
$baseline = $projectRoot . '/web/docs/security/xss-html-sinks-baseline-2026-05-05.txt';

$cmd = "rg -n \"innerHTML|insertAdjacentHTML|outerHTML\" " . escapeshellarg($targetDir);
exec($cmd, $out, $code);
$current = implode("\n", $out);

/**
 * Ignore line number drift so baseline is stable across nearby edits.
 * Format from rg -n: /abs/path/file.js:123:source line
 */
function normalizeSinkLine(string $line): string
{
    $trim = trim($line);
    if ($trim === '') {
        return '';
    }
    if (preg_match('/^(.+?):\\d+:(.*)$/', $trim, $m) === 1) {
        return $m[1] . '::' . $m[2];
    }
    return $trim;
}

if (!is_file($baseline)) {
    fwrite(STDERR, "[FAIL] Baseline not found: {$baseline}\n");
    exit(1);
}

$baselineContent = trim((string)file_get_contents($baseline));
$currentContent = trim($current);

$baselineLines = $baselineContent === '' ? [] : (preg_split('/\R/', $baselineContent) ?: []);
$currentLines = $currentContent === '' ? [] : (preg_split('/\R/', $currentContent) ?: []);

$baselineNormalized = [];
foreach ($baselineLines as $line) {
    $normalized = normalizeSinkLine((string)$line);
    if ($normalized !== '') {
        $baselineNormalized[] = $normalized;
    }
}
$currentNormalized = [];
foreach ($currentLines as $line) {
    $normalized = normalizeSinkLine((string)$line);
    if ($normalized !== '') {
        $currentNormalized[] = $normalized;
    }
}

$baseSet = array_fill_keys($baselineNormalized, true);
$newLines = [];
foreach ($currentNormalized as $line) {
    if (!isset($baseSet[$line])) {
        $newLines[] = $line;
    }
}

if ($newLines !== []) {
    fwrite(STDERR, "[FAIL] New потенциально опасные HTML sinks обнаружены (вне baseline):\n");
    foreach ($newLines as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    exit(1);
}

echo "[OK] XSS static check: no new HTML sinks beyond baseline\n";
echo "Current sinks: " . count($currentNormalized) . "\n";
