<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/security/EnvironmentCapabilities.php';

use Api\System\Library\Security\EnvironmentCapabilities;

function envAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    // --- serverSoftware() ---

    // Apache variants
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41 (Ubuntu)';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'apache', 'Apache/2.4.41 must be detected as apache');
    envAssert($caps->htaccessSupported() === true, 'Apache must support htaccess');

    // Nginx
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18.0';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'nginx', 'nginx/1.18.0 must be detected as nginx');
    envAssert($caps->htaccessSupported() === false, 'nginx must NOT support htaccess');

    // LiteSpeed (must be detected BEFORE apache)
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'LiteSpeed';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'litespeed', 'LiteSpeed must be detected as litespeed');
    envAssert($caps->htaccessSupported() === true, 'LiteSpeed must support htaccess');

    // LiteSpeed + Apache in string (LiteSpeed often reports both)
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41 (LiteSpeed)';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'litespeed', 'LiteSpeed must be detected even when Apache is in the string');
    envAssert($caps->htaccessSupported() === true, 'LiteSpeed (with Apache in string) must support htaccess');

    // Empty string
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = '';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'unknown', 'Empty SERVER_SOFTWARE must return unknown');
    envAssert($caps->htaccessSupported() === false, 'Unknown server must NOT assume htaccess support');

    // Missing key
    EnvironmentCapabilities::reset();
    unset($_SERVER['SERVER_SOFTWARE']);
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'unknown', 'Missing SERVER_SOFTWARE must return unknown');
    envAssert($caps->htaccessSupported() === false, 'Missing SERVER_SOFTWARE must NOT assume htaccess support');

    // Unknown value
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'Caddy/2.6.4';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->serverSoftware() === 'unknown', 'Unknown server (Caddy) must return unknown');
    envAssert($caps->htaccessSupported() === false, 'Unknown server must NOT support htaccess');

    // --- isHttps() ---

    // Standard HTTPS on
    EnvironmentCapabilities::reset();
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->isHttps() === true, 'HTTPS=on must be detected as HTTPS');

    // HTTPS off
    EnvironmentCapabilities::reset();
    $_SERVER['HTTPS'] = 'off';
    $_SERVER['SERVER_PORT'] = '80';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->isHttps() === false, 'HTTPS=off must be detected as non-HTTPS');

    // Port 443 only
    EnvironmentCapabilities::reset();
    unset($_SERVER['HTTPS']);
    $_SERVER['SERVER_PORT'] = '443';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->isHttps() === true, 'Port 443 must be detected as HTTPS');

    // REQUEST_SCHEME https
    EnvironmentCapabilities::reset();
    unset($_SERVER['HTTPS']);
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REQUEST_SCHEME'] = 'https';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->isHttps() === true, 'REQUEST_SCHEME=https must be detected as HTTPS');

    // Plain HTTP
    EnvironmentCapabilities::reset();
    unset($_SERVER['HTTPS']);
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->isHttps() === false, 'Plain HTTP must not be detected as HTTPS');

    // --- hasFinfo / hasCurl / hasZip / hasDns ---

    EnvironmentCapabilities::reset();
    $caps = new EnvironmentCapabilities();
    envAssert($caps->hasFinfo() === function_exists('finfo_open'), 'hasFinfo must match function_exists');
    envAssert($caps->hasCurl() === function_exists('curl_init'), 'hasCurl must match function_exists');
    envAssert($caps->hasZip() === class_exists('ZipArchive'), 'hasZip must match class_exists');
    envAssert($caps->hasDns() === function_exists('dns_get_record'), 'hasDns must match function_exists');

    // --- storageOutsideDocroot ---

    EnvironmentCapabilities::reset();
    $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
    // Set storage outside docroot
    putenv('CRM_STORAGE_BASE=/var/www/storage_api');
    $caps = new EnvironmentCapabilities();
    // This test can only assert the logic doesn't crash;
    // actual result depends on filesystem layout.
    envAssert(is_bool($caps->storageOutsideDocroot()), 'storageOutsideDocroot must return bool');

    // Empty DOCUMENT_ROOT
    EnvironmentCapabilities::reset();
    $_SERVER['DOCUMENT_ROOT'] = '';
    $caps = new EnvironmentCapabilities();
    envAssert($caps->storageOutsideDocroot() === false, 'Empty DOCUMENT_ROOT must return false');

    // --- collectWarnings / toArray ---

    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.18.0';
    putenv('CRM_STORAGE_BASE=');
    $caps = new EnvironmentCapabilities();
    $arr = $caps->toArray();
    envAssert(isset($arr['server_software']), 'toArray must have server_software');
    envAssert(isset($arr['htaccess_supported']), 'toArray must have htaccess_supported');
    envAssert(isset($arr['warnings']), 'toArray must have warnings');
    envAssert(is_array($arr['warnings']), 'warnings must be an array');
    // On nginx, if storage is inside docroot, there should be a critical warning
    // (actual depends on fs layout; we just check structure)
    foreach ($arr['warnings'] as $w) {
        envAssert(isset($w['code'], $w['severity'], $w['message_key']), 'Each warning must have code, severity, message_key');
        envAssert(in_array($w['severity'], ['critical', 'warning'], true), 'severity must be critical or warning');
    }

    // --- Cache works ---
    EnvironmentCapabilities::reset();
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41';
    $caps1 = new EnvironmentCapabilities();
    $first = $caps1->serverSoftware();
    $second = $caps1->serverSoftware();
    envAssert($first === $second, 'serverSoftware must return cached value on second call');
    envAssert($first === 'apache', 'Cached value must be correct');

    echo "[OK] environment_capabilities_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] environment_capabilities_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
