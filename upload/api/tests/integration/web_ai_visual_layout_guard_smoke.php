<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_visual_layout_guard_smoke: {$message}\n");
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

$componentsCss = readFileSafe($webRoot . '/assets/css/components.css');
$responsiveCss = readFileSafe($webRoot . '/assets/css/responsive.css');
$tokensCss = readFileSafe($webRoot . '/assets/css/tokens.css');
$pagesCss = readFileSafe($webRoot . '/assets/css/pages.css');
$aiJs = readFileSafe($webRoot . '/assets/js/ai.js');
$br1Js = readFileSafe($webRoot . '/assets/js/br1.js');
$pageBindingsJs = readFileSafe($webRoot . '/assets/js/page-api-bindings.js');
$calendarTemplate = readFileSafe($webRoot . '/view/template/page/calendar.php');
$taskDetailTemplate = readFileSafe($webRoot . '/view/template/page/task_detail.php');

// 1611: Desktop/mobile drawer overlap guardrails.
assertContains($componentsCss, '.crm-drawer-wide {', 'drawer width class missing');
assertContains($componentsCss, 'width: min(560px, 100vw) !important;', 'drawer width is not constrained by viewport');
assertContains($responsiveCss, '#aiSuggestionDrawer .offcanvas-body > .d-flex.gap-2 .btn {', 'mobile drawer action stack selector missing');
assertContains($responsiveCss, 'width: 100%;', 'mobile drawer action buttons must stretch full width');
assertContains($tokensCss, '--z-drawer: 1060;', 'z-index token for drawer missing');
assertContains($tokensCss, '--z-topbar: 1040;', 'topbar z-index token missing');

// 1612: Long AI text safety in cards/tables.
assertContains($aiJs, 'escapeHtml(', 'AI text sanitization helper usage missing');
assertContains($pagesCss, '.crm-admin-page #adminLogsPreview .crm-metric-tile > div {', 'admin AI metric tile wrapping rule missing');
assertContains($pagesCss, 'overflow-wrap: anywhere;', 'overflow-wrap guard missing');
assertContains($pagesCss, 'word-break: break-word;', 'word-break guard missing');

// 1613: Provider/disabled/rate-limit errors must have readable UI mapping.
assertContains($aiJs, "state === 'provider_missing'", 'provider_missing error-state mapping missing');
assertContains($aiJs, "state === 'disabled'", 'disabled error-state mapping missing');
assertContains($aiJs, "state === 'rate_limited'", 'rate_limited error-state mapping missing');
assertContains($aiJs, "state === 'conflict'", 'conflict error-state mapping missing');
assertContains($aiJs, 'AI-действие завершилось с ошибкой.', 'fallback readable AI error copy missing');

// 1614: Admin provider form should not expose raw secret after save.
assertContains($pageBindingsJs, 'credential_is_configured', 'provider credential configured marker missing');
assertContains($pageBindingsJs, 'credential_last4', 'masked provider credential tail marker missing');

// 1615: Task-detail AI actions + tabs coexistence.
assertContains($taskDetailTemplate, 'crm-task-tabs-nav', 'task tabs nav missing in task detail template');
assertContains($taskDetailTemplate, 'id="taskAiPrimaryActions"', 'task AI actions block missing in task detail template');
assertContains($taskDetailTemplate, 'id="taskAiSecondaryActions"', 'task AI secondary actions block missing in task detail template');
assertContains($br1Js, "document.getElementById('taskAiGenerateBtn')", 'task AI actions bindings missing');

// 1616: Calendar AI plan preview should live in side panel, not overlay grid layer.
assertContains($calendarTemplate, '<aside class="crm-calendar-side">', 'calendar side panel missing');
assertContains($calendarTemplate, 'id="calendarAiDayPlanCard"', 'calendar AI day plan card missing');
assertContains($calendarTemplate, 'data-calendar-surface', 'calendar main surface marker missing');
assertContains($pagesCss, '.crm-calendar-side {', 'calendar side CSS rule missing');
assertContains($pagesCss, 'position: sticky;', 'calendar side must remain sticky (not fixed overlay)');

fwrite(STDOUT, "[OK] web_ai_visual_layout_guard_smoke\n");
