<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$aiJsPath = $root . '/web/assets/js/ai.js';
$pageBindingsPath = $root . '/web/assets/js/page-api-bindings.js';
$templates = [
    $root . '/web/view/template/page/my_day.php',
    $root . '/web/view/template/page/my_week.php',
    $root . '/web/view/template/page/tasks.php',
    $root . '/web/view/template/page/admin_ai.php',
    $root . '/web/view/template/page/dashboard.php',
    $root . '/web/view/template/page/project_detail.php',
    $root . '/web/view/template/page/client_detail.php',
    $root . '/web/view/template/page/analytics.php',
    $root . '/web/view/template/page/calendar.php'
];

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_state_model_smoke: {$message}\n");
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

$aiJs = readFileSafe($aiJsPath);
$pageBindings = readFileSafe($pageBindingsPath);

assertContains($pageBindings, 'function resolveAiUiState(', 'resolveAiUiState helper missing in page-api-bindings.js');
assertContains($pageBindings, 'function setAiUiState(', 'setAiUiState helper missing in page-api-bindings.js');

$expectedPageStateWrappers = [
    'function setProjectAiState(stateCode, message)',
    'function setTasksAiState(stateCode, message)',
    'function setDashboardAiState(stateCode, message)',
    'function setAnalyticsAiState(stateCode, message)',
    'function setClientAiState(stateCode, message)',
    'function setMyDayAiState(stateCode, message)',
    'function setMyWeekAiState(stateCode, message)'
];

foreach ($expectedPageStateWrappers as $signature) {
    assertContains($pageBindings, $signature, 'missing page-level AI state wrapper');
}

$expectedStates = [
    'hidden', 'idle', 'loading', 'ready', 'empty', 'disabled',
    'provider_missing', 'rate_limited', 'error', 'conflict',
    'applied', 'partially_applied', 'dismissed'
];

foreach ($expectedStates as $state) {
    assertContains($aiJs, $state . ": 'Состояние: " . $state . "'", 'missing canonical state label in ai.js');
}

$templateChecks = [
    [$templates[0], 'id="myDayAiCard"', 'my-day AI card id missing'],
    [$templates[0], 'data-ai-state="idle"', 'my-day AI state attribute missing'],
    [$templates[1], 'id="myWeekAiCard"', 'my-week AI card id missing'],
    [$templates[1], 'data-ai-state="idle"', 'my-week AI state attribute missing'],
    [$templates[2], 'id="tasksAiPriorityCard"', 'tasks AI-priority card id missing'],
    [$templates[2], 'data-ai-state="idle"', 'tasks AI-priority state attribute missing'],
    [$templates[3], 'id="adminAiReviewCard"', 'admin-ai review card id missing'],
    [$templates[3], 'data-ai-state="empty"', 'admin-ai review state attribute missing'],
    [$templates[4], 'id="dashboardAiDigestCard"', 'dashboard AI digest card id missing'],
    [$templates[4], 'data-ai-state="idle"', 'dashboard AI digest state attribute missing'],
    [$templates[5], 'id="projectAiCard"', 'project AI card id missing'],
    [$templates[5], 'data-ai-state="idle"', 'project AI state attribute missing'],
    [$templates[6], 'id="clientAiCard"', 'client AI card id missing'],
    [$templates[6], 'data-ai-state="idle"', 'client AI state attribute missing'],
    [$templates[7], 'id="analyticsAiCard"', 'analytics AI card id missing'],
    [$templates[7], 'data-ai-state="idle"', 'analytics AI state attribute missing'],
    [$templates[8], 'id="calendarAiDayPlanCard"', 'calendar AI day-plan card id missing'],
    [$templates[8], 'data-ai-state="idle"', 'calendar AI day-plan state attribute missing']
];

foreach ($templateChecks as [$filePath, $needle, $message]) {
    $content = readFileSafe($filePath);
    assertContains($content, $needle, $message . ' in ' . $filePath);
}

// Ensure state helper is actively used (not dead code).
if (substr_count($pageBindings, 'setAiUiState(') < 12) {
    failSmoke('setAiUiState usage count is unexpectedly low; unified state model may be incomplete');
}
if (substr_count($pageBindings, 'resolveAiUiState(') < 10) {
    failSmoke('resolveAiUiState usage count is unexpectedly low; soft-error mapping may be incomplete');
}

fwrite(STDOUT, "[OK] web_ai_state_model_smoke\n");
