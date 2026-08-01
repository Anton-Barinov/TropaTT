<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';
$jsRoot = $webRoot . '/assets/js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_cookie_client_contract_smoke: {$message}\n");
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

$aiJsPath = $jsRoot . '/ai.js';
$pageBindingsPath = $jsRoot . '/page-api-bindings.js';

$aiJs = readFileSafe($aiJsPath);
$pageBindings = readFileSafe($pageBindingsPath);

if (strpos($aiJs, 'window.CRM.api.request') === false) {
    failSmoke('ai.js must use window.CRM.api.request for AI requests');
}
if (strpos($pageBindings, 'window.CRM.api.request') === false) {
    failSmoke('page-api-bindings.js must use window.CRM.api.request for page AI bindings');
}

$forbiddenStoragePatterns = [
    '/localStorage\s*\./i',
    '/sessionStorage\s*\./i',
    '/getItem\s*\(/i',
    '/setItem\s*\(/i',
];

foreach ($forbiddenStoragePatterns as $pattern) {
    if (preg_match($pattern, $aiJs) === 1) {
        failSmoke('ai.js must not use browser storage APIs for auth state');
    }
}

$forbiddenAuthStoragePatterns = [
    '/localStorage[^\n]{0,120}(?:token|user|auth|session)/i',
    '/sessionStorage[^\n]{0,120}(?:token|user|auth|session)/i',
    '/(?:getItem|setItem)\s*\([^\)]*(?:token|user|auth|session)/i',
];

foreach ($forbiddenAuthStoragePatterns as $pattern) {
    if (preg_match($pattern, $pageBindings) === 1) {
        failSmoke('page-api-bindings.js must not persist/read auth token/user via storage APIs in AI bindings');
    }
}

fwrite(STDOUT, "[OK] web_ai_cookie_client_contract_smoke\n");
