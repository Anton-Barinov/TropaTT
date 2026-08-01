<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$path = $root . '/web/assets/js/page-api-bindings.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] page_api_bindings_cookie_session_contract_smoke: {$message}\n");
    exit(1);
}

if (!is_file($path)) {
    failSmoke('file not found: ' . $path);
}

$content = file_get_contents($path);
if ($content === false) {
    failSmoke('unable to read file: ' . $path);
}

if (strpos($content, 'function request(') === false) {
    failSmoke('request wrapper must exist in page-api-bindings.js');
}

if (strpos($content, 'window.CRM.api.request') === false) {
    failSmoke('page-api-bindings.js must use window.CRM.api.request');
}

$forbiddenPatterns = [
    '/localStorage\s*\./i',
    '/sessionStorage\s*\./i',
    '/(?:getItem|setItem)\s*\([^\)]*(?:token|auth|session|user)/i',
    '/localStorage[^\n]{0,120}(?:token|auth|session|user)/i',
    '/sessionStorage[^\n]{0,120}(?:token|auth|session|user)/i',
];

foreach ($forbiddenPatterns as $pattern) {
    if (preg_match($pattern, $content) === 1) {
        failSmoke('forbidden token/session storage dependency detected: ' . $pattern);
    }
}

fwrite(STDOUT, "[OK] page_api_bindings_cookie_session_contract_smoke\n");
