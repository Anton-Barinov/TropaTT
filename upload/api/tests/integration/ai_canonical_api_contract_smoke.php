<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$routesPath = $root . '/api/config/routes.php';
$webJsPath = $root . '/web/assets/js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] ai_canonical_api_contract_smoke: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke("file not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke("unable to read file: {$path}");
    }

    return $content;
}

function collectJsFiles(string $dir): array
{
    if (!is_dir($dir)) {
        failSmoke("directory not found: {$dir}");
    }

    $result = [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($iter as $item) {
        if (!$item->isFile()) {
            continue;
        }

        if (strtolower($item->getExtension()) !== 'js') {
            continue;
        }

        $result[] = $item->getPathname();
    }

    sort($result);
    return $result;
}

$routes = require $routesPath;
if (!is_array($routes) || $routes === []) {
    failSmoke('api/config/routes.php must return non-empty array');
}

$aiPatterns = [];
foreach ($routes as $index => $route) {
    if (!is_array($route) || !isset($route['pattern']) || !is_string($route['pattern'])) {
        failSmoke('invalid route definition at index ' . $index);
    }

    $pattern = $route['pattern'];
    if (strpos($pattern, '/api/v1/ai') !== 0) {
        continue;
    }

    $aiPatterns[] = $pattern;
}

if ($aiPatterns === []) {
    failSmoke('no AI routes with /api/v1/ai prefix found');
}

$requiredPatterns = [
    '/api/v1/ai/providers',
    '/api/v1/ai/actions/{action_type}',
    '/api/v1/ai/suggestions/{public_id}/preview-apply',
    '/api/v1/ai/jobs',
];

foreach ($requiredPatterns as $requiredPattern) {
    if (!in_array($requiredPattern, $aiPatterns, true)) {
        failSmoke('required canonical AI route missing: ' . $requiredPattern);
    }
}

$legacyRoutePattern = '/(?:\/api\/ai\/|api\/index\.php\?route=ai\/|route=ai\/)/i';
$routesRaw = readFileSafe($routesPath);
if (preg_match($legacyRoutePattern, $routesRaw) === 1) {
    failSmoke('legacy AI route alias found in routes.php');
}

$canonicalPreviewApply = '/api/v1/ai/suggestions/{public_id}/preview-apply';
$legacyPreviewApplyAlias = '/api/v1/ai/suggestions/{public_id}/apply-preview';

if (!in_array($canonicalPreviewApply, $aiPatterns, true)) {
    failSmoke('canonical preview-apply endpoint is missing');
}

$jsFiles = collectJsFiles($webJsPath);
if ($jsFiles === []) {
    failSmoke('no JS files found under web/assets/js');
}

foreach ($jsFiles as $path) {
    $content = readFileSafe($path);

    if (strpos($content, 'api/v1/ai/suggestions/') === false) {
        continue;
    }

    if (strpos($content, '/apply-preview') !== false) {
        failSmoke('web JS uses legacy apply-preview alias: ' . $path);
    }
}

if (in_array($legacyPreviewApplyAlias, $aiPatterns, true) && !in_array($canonicalPreviewApply, $aiPatterns, true)) {
    failSmoke('legacy apply-preview alias cannot exist without canonical preview-apply endpoint');
}

fwrite(STDOUT, "[OK] ai_canonical_api_contract_smoke\n");
