<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';
$jsRoot = $webRoot . '/assets/js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_contract_smoke: {$message}\n");
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

$aiJsPath = $jsRoot . '/ai.js';
$aiJs = readFileSafe($aiJsPath);

if (strpos($aiJs, 'return window.CRM.api.request(route, options || {});') === false) {
    failSmoke('ai.js must route requests via window.CRM.api.request');
}

$jsFiles = collectJsFiles($jsRoot);
if ($jsFiles === []) {
    failSmoke('no JS files found under web/assets/js');
}

$forbiddenDirectProviderCall = '/(?:fetch|axios|XMLHttpRequest)\\s*\\([^\\n]*(?:openai|anthropic|openrouter|deepseek|mistral|moonshot|generativelanguage|z\\.ai|ollama|lmstudio)/i';
$forbiddenLegacyAiRoute = '/(?:route=ai\\/|\\/api\\/ai\\/|api\\/index\\.php\\?route=ai\\/)/i';
$forbiddenAiFetchBypass = '/fetch\\s*\\([^\\n]*api\\/v1\\/ai\\//i';

foreach ($jsFiles as $path) {
    $content = readFileSafe($path);

    if (preg_match($forbiddenDirectProviderCall, $content) === 1) {
        failSmoke('direct provider network call pattern found in ' . $path);
    }

    if (preg_match($forbiddenLegacyAiRoute, $content) === 1) {
        failSmoke('legacy AI route pattern found in ' . $path);
    }

    if (preg_match($forbiddenAiFetchBypass, $content) === 1) {
        failSmoke('direct fetch to /api/v1/ai detected (must use CRM api client): ' . $path);
    }
}

$aiDrawerTemplateOccurrences = 0;
$viewIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webRoot . '/view', FilesystemIterator::SKIP_DOTS));
foreach ($viewIter as $item) {
    if (!$item->isFile()) {
        continue;
    }
    if (!in_array(strtolower($item->getExtension()), ['php', 'html', 'htm'], true)) {
        continue;
    }
    $content = readFileSafe($item->getPathname());
    $aiDrawerTemplateOccurrences += substr_count($content, 'id="aiSuggestionDrawer"');
}

if ($aiDrawerTemplateOccurrences !== 0) {
    failSmoke('aiSuggestionDrawer placeholder must not be duplicated in templates');
}

if (substr_count($aiJs, 'id="aiSuggestionDrawer"') !== 1) {
    failSmoke('aiSuggestionDrawer runtime template must be defined exactly once in ai.js');
}

fwrite(STDOUT, "[OK] web_ai_contract_smoke\n");
