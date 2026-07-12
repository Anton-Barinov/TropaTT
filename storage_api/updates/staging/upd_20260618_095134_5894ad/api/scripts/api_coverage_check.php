<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$apiRoutesFile = $projectRoot . '/api/config/routes.php';
$coverageFile = $projectRoot . '/web/docs/api-coverage.md';

$allowedStatuses = [
    'Implemented',
    'Partial',
    'Missing',
    'Internal/API only',
    'Unknown',
];

if (!is_file($apiRoutesFile) || !is_file($coverageFile)) {
    fwrite(STDERR, "[FAIL] Missing routes.php or api-coverage.md\n");
    exit(1);
}

/** @var array<int,array<string,mixed>> $routes */
$routes = require $apiRoutesFile;

$routeKeys = [];
foreach ($routes as $route) {
    $pattern = (string)($route['pattern'] ?? '');
    if ($pattern === '') {
        continue;
    }
    foreach ((array)($route['methods'] ?? []) as $method) {
        $method = strtoupper(trim((string)$method));
        if ($method === '') {
            continue;
        }
        $routeKeys[$pattern . '|' . $method] = true;
    }
}

$lines = file($coverageFile, FILE_IGNORE_NEW_LINES);
if (!is_array($lines)) {
    fwrite(STDERR, "[FAIL] Cannot read coverage file\n");
    exit(1);
}

$docKeys = [];
$statusCount = [];
$unknownStatuses = [];

foreach ($lines as $line) {
    $trim = trim($line);
    if (!str_starts_with($trim, '| `/') || str_contains($trim, 'API endpoint')) {
        continue;
    }

    $parts = array_map('trim', explode('|', $trim));
    if (count($parts) < 8) {
        continue;
    }

    $endpoint = trim((string)$parts[1], "` ");
    $methodsRaw = trim((string)$parts[2], "` ");
    $status = trim((string)$parts[6], "` ");

    if ($endpoint === '' || $methodsRaw === '') {
        continue;
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $unknownStatuses[] = $status;
    }
    $statusCount[$status] = (int)($statusCount[$status] ?? 0) + 1;

    $methods = array_map('trim', explode(',', $methodsRaw));
    foreach ($methods as $method) {
        $method = strtoupper($method);
        if ($method === '') {
            continue;
        }
        $docKeys[$endpoint . '|' . $method] = $status;
    }
}

$missingInDoc = [];
foreach (array_keys($routeKeys) as $key) {
    if (!array_key_exists($key, $docKeys)) {
        $missingInDoc[] = $key;
    }
}

$extraInDoc = [];
foreach (array_keys($docKeys) as $key) {
    if (!array_key_exists($key, $routeKeys)) {
        $extraInDoc[] = $key;
    }
}

$unknownCount = (int)($statusCount['Unknown'] ?? 0);

echo "API routes (method-level): " . count($routeKeys) . "\n";
echo "Coverage rows (method-level): " . count($docKeys) . "\n";
echo "Status Unknown: " . $unknownCount . "\n";

if ($unknownStatuses !== []) {
    fwrite(STDERR, "[FAIL] Unknown status labels: " . implode(', ', array_values(array_unique($unknownStatuses))) . "\n");
    exit(1);
}

if ($unknownCount > 0) {
    fwrite(STDERR, "[FAIL] Coverage still contains Unknown statuses\n");
    exit(1);
}

if ($missingInDoc !== []) {
    fwrite(STDERR, "[FAIL] Missing routes in coverage: " . count($missingInDoc) . "\n");
    foreach (array_slice($missingInDoc, 0, 20) as $k) {
        fwrite(STDERR, "  - {$k}\n");
    }
    exit(1);
}

if ($extraInDoc !== []) {
    fwrite(STDERR, "[FAIL] Extra rows not found in routes.php: " . count($extraInDoc) . "\n");
    foreach (array_slice($extraInDoc, 0, 20) as $k) {
        fwrite(STDERR, "  - {$k}\n");
    }
    exit(1);
}

echo "[OK] API coverage classification is complete and consistent.\n";
exit(0);
