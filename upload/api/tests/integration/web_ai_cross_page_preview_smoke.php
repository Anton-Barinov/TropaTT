<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';
$bindingsPath = $webRoot . '/assets/js/page-api-bindings.js';
$templates = [
    'dashboard' => $webRoot . '/view/template/page/dashboard.php',
    'project' => $webRoot . '/view/template/page/project_detail.php',
    'client' => $webRoot . '/view/template/page/client_detail.php',
    'analytics' => $webRoot . '/view/template/page/analytics.php',
    'admin_ai' => $webRoot . '/view/template/page/admin_ai.php',
    'my_day' => $webRoot . '/view/template/page/my_day.php',
    'my_week' => $webRoot . '/view/template/page/my_week.php',
];

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_cross_page_preview_smoke: {$message}\n");
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

$bindings = readFileSafe($bindingsPath);
$dashboardTemplate = readFileSafe($templates['dashboard']);
$projectTemplate = readFileSafe($templates['project']);
$clientTemplate = readFileSafe($templates['client']);
$analyticsTemplate = readFileSafe($templates['analytics']);
$adminAiTemplate = readFileSafe($templates['admin_ai']);
$myDayTemplate = readFileSafe($templates['my_day']);
$myWeekTemplate = readFileSafe($templates['my_week']);

// Cross-page UI anchors for preview/dismiss controls.
foreach ([
    [$projectTemplate, 'id="projectAiPreviewBtn"', 'project preview button missing'],
    [$projectTemplate, 'id="projectAiDismissBtn"', 'project dismiss button missing'],
    [$clientTemplate, 'id="clientAiPreviewBtn"', 'client preview button missing'],
    [$clientTemplate, 'id="clientAiDismissBtn"', 'client dismiss button missing'],
    [$analyticsTemplate, 'id="analyticsAiPreviewBtn"', 'analytics preview button missing'],
    [$analyticsTemplate, 'id="analyticsAiDismissBtn"', 'analytics dismiss button missing'],
    [$adminAiTemplate, 'id="adminAiReviewPreviewBtn"', 'admin-ai review preview button missing'],
    [$adminAiTemplate, 'id="adminAiReviewDismissBtn"', 'admin-ai review dismiss button missing'],
    [$myDayTemplate, 'id="myDayAiPreviewBtn"', 'my-day preview button missing'],
    [$myDayTemplate, 'id="myDayAiDismissBtn"', 'my-day dismiss button missing'],
    [$myWeekTemplate, 'id="myWeekAiPreviewBtn"', 'my-week preview button missing'],
    [$myWeekTemplate, 'id="myWeekAiDismissBtn"', 'my-week dismiss button missing'],
    [$dashboardTemplate, 'id="dashboardAiDigestActions"', 'dashboard AI actions container missing'],
] as [$content, $needle, $message]) {
    assertContains($content, $needle, $message);
}

// Dashboard recommendation-click preview must open unified drawer.
assertContains($bindings, 'function openDigestActionPreview(action)', 'dashboard preview helper missing');
assertContains($bindings, 'data-dashboard-ai-action-index', 'dashboard AI action index binding missing');
assertContains($bindings, 'openDigestActionPreview(recommendedActions[actionIndex]);', 'dashboard click->preview wiring missing');
assertContains($bindings, "window.CRM.ai.openSuggestionDrawer(previewSuggestion, preview, {", 'dashboard drawer open wiring missing');
assertContains($bindings, 'window.CRM.ai.dismissSuggestion(latestDigestSuggestion.public_id);', 'dashboard dismiss wiring missing');

// project/client/analytics/admin review preview+dismiss must use canonical suggestion lifecycle.
foreach ([
    'projectAiPreviewBtn.addEventListener(\'click\'',
    'projectAiDismissBtn.addEventListener(\'click\'',
    'analyticsAiPreviewBtn.onclick = async function () {',
    'analyticsAiDismissBtn.onclick = async function () {',
    'clientAiPreviewBtn.onclick = async function () {',
    'clientAiDismissBtn.onclick = async function () {',
    'adminAiReviewPreviewBtn.onclick = async function () {',
    'adminAiReviewDismissBtn.onclick = async function () {',
    'openMyDaySuggestionDrawer(loadedSuggestion, preview)',
    'openMyWeekSuggestionDrawer(loadedSuggestion, preview)',
    "modal.querySelector('[data-calendar-ai-preview-btn]')",
    "modal.querySelector('[data-calendar-ai-dismiss-btn]')",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentProjectAiSuggestion.public_id) + '/preview-apply",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentProjectAiSuggestion.public_id) + '/dismiss",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentClientAiSuggestion.public_id) + '/preview-apply",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentClientAiSuggestion.public_id) + '/dismiss",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentAnalyticsSuggestion.public_id) + '/preview-apply",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentAnalyticsSuggestion.public_id) + '/dismiss",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentAdminReviewSuggestion.public_id) + '/preview-apply",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentAdminReviewSuggestion.public_id) + '/dismiss",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentSuggestion.public_id) + '/preview-apply",
    'api/v1/ai/suggestions/' . "' + encodeURIComponent(currentSuggestion.public_id) + '/dismiss",
] as $needle) {
    assertContains($bindings, $needle, 'cross-page lifecycle wiring missing');
}

// Ensure global drawer is actively used in multiple independent page flows.
if (substr_count($bindings, 'window.CRM.ai.openSuggestionDrawer(') < 8) {
    failSmoke('openSuggestionDrawer usage count is unexpectedly low for cross-page flows');
}

fwrite(STDOUT, "[OK] web_ai_cross_page_preview_smoke\n");
