<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


$projectRoot = dirname(__DIR__, 2);
$apiRoutesFile = $projectRoot . '/api/config/routes.php';
$openApiFile = $projectRoot . '/api/docs/openapi/openapi.v1.json';

/**
 * Install and migration routes are deliberately absent from the public
 * contract. Other exclusions are declared next to their route in routes.php
 * with openapi=false, so a route cannot silently disappear from this check.
 *
 * @var array<string,true>
 */
if (!is_file($apiRoutesFile) || !is_file($openApiFile)) {
    fwrite(STDERR, "[FAIL] Missing routes.php or openapi.v1.json\n");
    exit(1);
}

/** @var array<int,array<string,mixed>> $routes */
$routes = require $apiRoutesFile;

$openApiExclusions = [];
foreach ($routes as $route) {
    $pattern = (string)($route['pattern'] ?? '');
    if ($pattern === '') {
        continue;
    }
    $isInternal = str_starts_with($pattern, '/install/') || str_starts_with($pattern, '/internal/');
    if (!$isInternal && (($route['openapi'] ?? true) !== false)) {
        continue;
    }
    foreach ((array)($route['methods'] ?? []) as $method) {
        $method = strtoupper(trim((string)$method));
        if ($method !== '') {
            $openApiExclusions[$pattern . '|' . $method] = true;
        }
    }
}

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

$openApiRaw = file_get_contents($openApiFile);
$openApi = is_string($openApiRaw) ? json_decode($openApiRaw, true) : null;
if (!is_array($openApi) || !is_array($openApi['paths'] ?? null)) {
    fwrite(STDERR, "[FAIL] Cannot parse openapi.v1.json\n");
    exit(1);
}

$docKeys = [];
$httpMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
foreach ($openApi['paths'] as $endpoint => $operations) {
    if (!is_array($operations)) {
        continue;
    }
    foreach ($operations as $method => $_operation) {
        $method = strtoupper((string)$method);
        if (in_array($method, $httpMethods, true)) {
            $docKeys[(string)$endpoint . '|' . $method] = true;
        }
    }
}

$missingInDoc = [];
foreach (array_keys($routeKeys) as $key) {
    if (!isset($docKeys[$key]) && !isset($openApiExclusions[$key])) {
        $missingInDoc[] = $key;
    }
}

$extraInDoc = [];
foreach (array_keys($docKeys) as $key) {
    if (!isset($routeKeys[$key])) {
        $extraInDoc[] = $key;
    }
}

echo "API routes (method-level): " . count($routeKeys) . "\n";
echo "OpenAPI operations: " . count($docKeys) . "\n";
echo "Explicit internal exclusions: " . count($openApiExclusions) . "\n";

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

echo "[OK] API OpenAPI coverage is complete and consistent.\n";
exit(0);
