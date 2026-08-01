<?php
declare(strict_types=1);

function fail(string $message): void
{
    fwrite(STDERR, "[FAIL] web_error_adapter_contract_smoke: {$message}\n");
    exit(1);
}

function read(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        fail('Cannot read file: ' . $path);
    }
    return $content;
}

$projectRoot = dirname(__DIR__, 3);
$apiJs = read($projectRoot . '/web/assets/js/api.js');
$bindingsJs = read($projectRoot . '/web/assets/js/page-api-bindings.js');
$loginJs = read($projectRoot . '/web/assets/js/br1.js');

// Core adapter API must exist.
if (!str_contains($apiJs, 'function normalizeError(')) fail('normalizeError function missing in api.js');
if (!str_contains($apiJs, 'function formatErrorMessage(')) fail('formatErrorMessage function missing in api.js');
if (!str_contains($apiJs, 'normalizeError: normalizeError')) fail('normalizeError export missing');
if (!str_contains($apiJs, 'formatErrorMessage: formatErrorMessage')) fail('formatErrorMessage export missing');

// Required normalized fields.
foreach (['fieldErrors:', 'retryAfter:', 'requestId:', 'correlationId:'] as $needle) {
    if (!str_contains($apiJs, $needle)) {
        fail('normalized error field missing: ' . $needle);
    }
}

// Required code families.
foreach (['NETWORK_TIMEOUT', 'REQUEST_ABORTED', 'RATE_LIMITED', 'AUTH_RATE_LIMITED'] as $needle) {
    if (!str_contains($apiJs, $needle)) {
        fail('error code mapping missing: ' . $needle);
    }
}

// Integration in generic request wrapper.
if (!str_contains($bindingsJs, 'window.CRM.api.normalizeError')) fail('tryRequest does not use normalizeError');
if (!str_contains($bindingsJs, 'window.CRM.api.formatErrorMessage')) fail('tryRequest does not use formatErrorMessage');
if (!str_contains($bindingsJs, 'request_id: normalized.requestId')) fail('tryRequest does not pass request_id');
if (!str_contains($bindingsJs, 'correlation_id: normalized.correlationId')) fail('tryRequest does not pass correlation_id');

// Integration in login flow.
if (!str_contains($loginJs, 'window.CRM.api.normalizeError')) fail('login flow does not use normalizeError');
if (!str_contains($loginJs, 'window.CRM.api.formatErrorMessage')) fail('login flow does not use formatErrorMessage');

fwrite(STDOUT, "[OK] web_error_adapter_contract_smoke\n");

