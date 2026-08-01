<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$pageTemplatesDir = $root . '/web/view/template/page';
$jsDir = $root . '/web/assets/js';
$pageBindingsPath = $jsDir . '/page-api-bindings.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_provider_settings_scope_smoke: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke('file not found: ' . $path);
    }

    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke('unable to read file: ' . $path);
    }

    return $content;
}

function collectFilesByExtension(string $dir, string $extension): array
{
    if (!is_dir($dir)) {
        failSmoke('directory not found: ' . $dir);
    }

    $result = [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $item) {
        if (!$item->isFile()) {
            continue;
        }
        if (strtolower($item->getExtension()) !== strtolower($extension)) {
            continue;
        }
        $result[] = $item->getPathname();
    }

    sort($result);
    return $result;
}

$templateFiles = collectFilesByExtension($pageTemplatesDir, 'php');
if ($templateFiles === []) {
    failSmoke('no page templates found');
}

$adminAiTemplate = $pageTemplatesDir . '/admin_ai.php';
if (!in_array($adminAiTemplate, $templateFiles, true)) {
    failSmoke('admin_ai.php template is required');
}

$forbiddenTemplatePatterns = [
    '/name="provider_code"/i',
    '/name="base_url"/i',
    '/name="api_path"/i',
    '/name="default_model"/i',
    '/name="embeddings_endpoint"/i',
    '/data-ai-provider-/i',
];

foreach ($templateFiles as $templatePath) {
    if ($templatePath === $adminAiTemplate) {
        continue;
    }

    $content = readFileSafe($templatePath);
    foreach ($forbiddenTemplatePatterns as $pattern) {
        if (preg_match($pattern, $content) === 1) {
            failSmoke('AI provider setting control leaked outside admin-ai template: ' . $templatePath . ' pattern=' . $pattern);
        }
    }
}

$adminAiContent = readFileSafe($adminAiTemplate);
$requiredAdminAiPatterns = [
    '/name="provider_code"/i',
    '/name="base_url"/i',
    '/name="api_path"/i',
    '/name="default_model"/i',
];

foreach ($requiredAdminAiPatterns as $pattern) {
    if (preg_match($pattern, $adminAiContent) !== 1) {
        failSmoke('expected provider setting control is missing in admin-ai template: ' . $pattern);
    }
}

$providerJsMarkers = [
    'data-ai-provider-',
    '[name="provider_code"]',
    '[name="base_url"]',
    '[name="api_path"]',
    '[name="default_model"]',
    '[name="embeddings_endpoint"]',
    '/api/v1/ai/providers',
];

$jsFiles = collectFilesByExtension($jsDir, 'js');
foreach ($jsFiles as $jsPath) {
    if ($jsPath === $pageBindingsPath) {
        continue;
    }

    $content = readFileSafe($jsPath);
    foreach ($providerJsMarkers as $marker) {
        if (strpos($content, $marker) !== false) {
            failSmoke('provider settings marker leaked outside admin-ai bindings in JS: ' . $jsPath . ' marker=' . $marker);
        }
    }
}

$pageBindings = readFileSafe($pageBindingsPath);
$lines = preg_split('/\R/', $pageBindings) ?: [];
$adminFunctionStart = null;
foreach ($lines as $index => $line) {
    if (strpos($line, 'async function renderAdminAiPage()') !== false) {
        $adminFunctionStart = $index + 1;
        break;
    }
}

if (!is_int($adminFunctionStart)) {
    failSmoke('renderAdminAiPage() function not found in page-api-bindings.js');
}

foreach ($lines as $index => $line) {
    $lineNo = $index + 1;
    if ($lineNo < $adminFunctionStart) {
        foreach ($providerJsMarkers as $marker) {
            if (strpos($line, $marker) !== false) {
                failSmoke('provider settings marker found before admin-ai renderer in page-api-bindings.js line ' . $lineNo . ' marker=' . $marker);
            }
        }
    }
}

fwrite(STDOUT, "[OK] web_ai_provider_settings_scope_smoke\n");
