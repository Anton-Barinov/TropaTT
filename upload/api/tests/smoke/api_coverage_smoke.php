<?php
declare(strict_types=1);

/**
 * Smoke test: API coverage by web UI.
 * Compares API routes from api/config/routes.php with JS calls in web/assets/js/*.
 */

function apiCoverageAssert(bool $condition, string $message): void
{
    if (!$condition) {
        echo "[FAIL] " . $message . "\n";
        $GLOBALS['api_coverage_failures']++;
        return;
    }
    echo "[OK] " . $message . "\n";
}

$GLOBALS['api_coverage_failures'] = 0;

$apiRoutesPath = dirname(__DIR__, 2) . '/config/routes.php';
$webBaseDir = dirname(__DIR__, 3);
$webJsDir = $webBaseDir . '/web/assets/js';

$apiSource = file_get_contents($apiRoutesPath);
apiCoverageAssert(is_string($apiSource), 'Failed to read api/config/routes.php');

// Extract API route patterns from config
preg_match_all("/'pattern'\s*=>\s*'([^']+)'/", $apiSource, $patternMatches);
$apiPatterns = $patternMatches[1] ?? [];

apiCoverageAssert(count($apiPatterns) > 50, 'Expected 50+ API route patterns, found ' . count($apiPatterns));

// Collect all JS file contents
$jsContents = '';
$jsFiles = glob($webJsDir . '/*.js');
foreach ($jsFiles as $jsFile) {
    $content = file_get_contents($jsFile);
    if (is_string($content)) {
        $jsContents .= ' ' . $content;
    }
}

apiCoverageAssert(strlen($jsContents) > 10000, 'Expected 10000+ chars of JS, found ' . strlen($jsContents));

// Check that key API domains are referenced in JS
$keyDomains = [
    'api/v1/auth/',
    'api/v1/tasks',
    'api/v1/projects',
    'api/v1/users',
    'api/v1/notifications',
    'api/v1/comments',
    'api/v1/files',
    'api/v1/worklogs',
    'api/v1/tags',
    'api/v1/webhooks',
    'api/v1/template/',
    'api/v1/history/',
    'api/v1/mentions',
    'api/v1/calendar/',
    'api/v1/retention/',
    'api/v1/settings',
    'api/v1/ops/',
];

$foundDomains = 0;
foreach ($keyDomains as $domain) {
    if (str_contains($jsContents, $domain)) {
        $foundDomains++;
    }
}

apiCoverageAssert($foundDomains >= count($keyDomains) - 2, "Expected most key domains in JS, found $foundDomains/" . count($keyDomains));

// Check that page-api-bindings.js has render functions for key pages
$bindingsPath = $webJsDir . '/page-api-bindings.js';
$bindingsSource = file_get_contents($bindingsPath);

$requiredRenderers = [
    'renderAdminTagsPage',
    'renderAdminWebhooksPage',
    'renderAdminTemplatesPage',
    'renderMentionsPage',
    'renderAdminCalendarPage',
    'renderAdminSettingsPage',
];

foreach ($requiredRenderers as $renderer) {
    apiCoverageAssert(
        is_string($bindingsSource) && str_contains($bindingsSource, $renderer),
        "Renderer found: $renderer in page-api-bindings.js"
    );
}

// Check that routes.php has routes for new pages
$webRoutesPath = $webBaseDir . '/web/config/routes.php';
$webRoutesSource = file_get_contents($webRoutesPath);

$requiredRoutes = [
    'admin-tags',
    'admin-webhooks',
    'admin-templates',
    'mentions',
    'admin-calendar',
    'admin-settings',
];

foreach ($requiredRoutes as $route) {
    apiCoverageAssert(
        is_string($webRoutesSource) && str_contains($webRoutesSource, $route),
        "Route found: $route in web/config/routes.php"
    );
}

// Summary
$failures = $GLOBALS['api_coverage_failures'];
if ($failures === 0) {
    echo "\n[OK] api_coverage_smoke: All checks passed\n";
} else {
    echo "\n[FAIL] api_coverage_smoke: $failures check(s) failed\n";
    exit(1);
}
