<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';
$jsRoot = $webRoot . '/assets/js';
$viewRoot = $webRoot . '/view/template/page';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_drawer_lifecycle_smoke: {$message}\n");
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

function assertContains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failSmoke($message . ' (needle: ' . $needle . ')');
    }
}

$aiJs = readFileSafe($jsRoot . '/ai.js');
$br1Js = readFileSafe($jsRoot . '/br1.js');
$pageBindingsJs = readFileSafe($jsRoot . '/page-api-bindings.js');
$myDayTemplate = readFileSafe($viewRoot . '/my_day.php');
$myWeekTemplate = readFileSafe($viewRoot . '/my_week.php');

// Global drawer lifecycle must be present in shared AI module.
assertContains($aiJs, 'function openSuggestionDrawer(', 'openSuggestionDrawer helper missing in ai.js');
assertContains($aiJs, 'function defaultRefreshHandler()', 'default refresh lifecycle handler missing in ai.js');
assertContains($aiJs, 'function defaultDismissHandler()', 'default dismiss lifecycle handler missing in ai.js');
assertContains($aiJs, 'api/v1/ai/suggestions/', 'canonical suggestions lifecycle route missing in ai.js');

// Canonical preview endpoint must be used; legacy apply-preview alias should not be used in web JS.
if (strpos($pageBindingsJs, '/apply-preview') !== false || strpos($br1Js, '/apply-preview') !== false || strpos($aiJs, '/apply-preview') !== false) {
    failSmoke('legacy /apply-preview alias detected in web JS; use canonical /preview-apply only');
}

assertContains($pageBindingsJs, '/preview-apply', 'canonical /preview-apply lifecycle call missing in page-api-bindings.js');
assertContains($br1Js, '/preview-apply', 'canonical /preview-apply lifecycle call missing in br1.js');

// Cross-page flows must open the unified global drawer.
assertContains($pageBindingsJs, 'window.CRM.ai.openSuggestionDrawer', 'global drawer open wiring missing in page-api-bindings.js');
assertContains($br1Js, '.openSuggestionDrawer(', 'global drawer open wiring missing in br1.js');

// my-day/my-week parity controls exist in templates and handlers.
assertContains($myDayTemplate, 'id="myDayAiPreviewBtn"', 'my-day preview button missing in template');
assertContains($myDayTemplate, 'id="myDayAiDismissBtn"', 'my-day dismiss button missing in template');
assertContains($myWeekTemplate, 'id="myWeekAiPreviewBtn"', 'my-week preview button missing in template');
assertContains($myWeekTemplate, 'id="myWeekAiDismissBtn"', 'my-week dismiss button missing in template');

assertContains($pageBindingsJs, "document.getElementById('myDayAiPreviewBtn')", 'my-day preview handler binding missing');
assertContains($pageBindingsJs, "document.getElementById('myDayAiDismissBtn')", 'my-day dismiss handler binding missing');
assertContains($pageBindingsJs, "document.getElementById('myWeekAiPreviewBtn')", 'my-week preview handler binding missing');
assertContains($pageBindingsJs, "document.getElementById('myWeekAiDismissBtn')", 'my-week dismiss handler binding missing');

// Ensure web keeps canonical API path namespace for AI requests.
if (preg_match('/api\\/index\\.php\\?route=ai\\//i', $pageBindingsJs . $br1Js . $aiJs) === 1) {
    failSmoke('legacy OpenCart-style AI route detected in web JS');
}

fwrite(STDOUT, "[OK] web_ai_drawer_lifecycle_smoke\n");
