<?php
declare(strict_types=1);

function failTimeoutAbort(string $message): void
{
    fwrite(STDERR, "[FAIL] web_api_timeout_abort_contract_smoke: {$message}\n");
    exit(1);
}

function readTimeoutAbort(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failTimeoutAbort('Cannot read file: ' . $path);
    }
    return $content;
}

$projectRoot = dirname(__DIR__, 3);
$apiJsPath = $projectRoot . '/web/assets/js/api.js';
$apiJs = readTimeoutAbort($apiJsPath);

foreach ([
    'var timeoutMs = Math.max(0, Math.floor(toNumber(opts.timeoutMs, 15000)));',
    'var controller = typeof AbortController !== \'undefined\' ? new AbortController() : null;',
    'opts.signal.addEventListener(\'abort\', abortListener, { once: true });',
    'timedOut = true;',
    "var tError = new Error('NETWORK_TIMEOUT');",
    "code: 'NETWORK_TIMEOUT',",
    "var aError = new Error('REQUEST_ABORTED');",
    "code: 'REQUEST_ABORTED',",
    'if (timeoutHandle) clearTimeout(timeoutHandle);',
    'opts.signal.removeEventListener(\'abort\', abortListener);',
] as $needle) {
    if (!str_contains($apiJs, $needle)) {
        failTimeoutAbort('Missing timeout/abort contract marker: ' . $needle);
    }
}

fwrite(STDOUT, "[OK] web_api_timeout_abort_contract_smoke\n");

