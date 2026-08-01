<?php
declare(strict_types=1);

function webHeadersAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$webIndexPath = dirname(__DIR__, 3) . '/web/index.php';
$source = file_get_contents($webIndexPath);
webHeadersAssert(is_string($source), 'Failed to read web/index.php');

$requiredHeaders = [
    "X-Content-Type-Options: nosniff",
    "X-Frame-Options: SAMEORIGIN",
    "Referrer-Policy: strict-origin-when-cross-origin",
    "Permissions-Policy: geolocation=(), microphone=(), camera=()",
    "Content-Security-Policy",
    "CRM_WEB_CSP_REPORT_ONLY",
];

foreach ($requiredHeaders as $needle) {
    webHeadersAssert(str_contains($source, $needle), 'Missing required web security header: ' . $needle);
}

echo "[OK] web_security_headers_contract_unit\n";
