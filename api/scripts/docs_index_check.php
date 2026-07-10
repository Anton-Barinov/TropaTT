<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$indexFile = $projectRoot . '/tz/crm-docs-index-and-baseline-2026-05-05.md';

if (!is_file($indexFile)) {
    fwrite(STDERR, "[FAIL] Missing docs index file: {$indexFile}\n");
    exit(1);
}

$content = file_get_contents($indexFile);
if (!is_string($content) || $content === '') {
    fwrite(STDERR, "[FAIL] Failed to read docs index file\n");
    exit(1);
}

preg_match_all('/`([^`]+\.md)`/', $content, $matches);
$paths = array_values(array_unique($matches[1] ?? []));

if ($paths === []) {
    fwrite(STDERR, "[FAIL] No markdown references found in docs index\n");
    exit(1);
}

$missing = [];
foreach ($paths as $path) {
    $candidate = $projectRoot . '/' . ltrim($path, '/');
    if (!is_file($candidate)) {
        $missing[] = $path;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Missing referenced docs:\n");
    foreach ($missing as $path) {
        fwrite(STDERR, "  - {$path}\n");
    }
    exit(1);
}

echo "[OK] docs_index_check (" . count($paths) . " refs)\n";

