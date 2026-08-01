<?php
declare(strict_types=1);

function failDashboardUx(string $message): void
{
    fwrite(STDERR, "[FAIL] web_dashboard_ux_security_contract_smoke: {$message}\n");
    exit(1);
}

function readDashboardUxFile(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failDashboardUx('Cannot read file: ' . $path);
    }
    return $content;
}

function assertDashboardContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        failDashboardUx($message . ': ' . $needle);
    }
}

$root = dirname(__DIR__, 3);
$template = readDashboardUxFile($root . '/web/view/template/page/dashboard.php');
$bindings = readDashboardUxFile($root . '/web/assets/js/page-api-bindings.js');
$pagesCss = readDashboardUxFile($root . '/web/assets/css/pages.css');

foreach ([
    'class="crm-content crm-dashboard-page"',
    'id="dashboardAiDigestCard"',
    'id="dashboardAiDigestActions"',
    'crm-dashboard-table-wrap',
] as $needle) {
    assertDashboardContains($template, $needle, 'Dashboard template missing UX contract');
}

if (str_contains($template, 'crm-dashboard-actions-more')) {
    failDashboardUx('Dashboard template still contains non-functional more action');
}

foreach ([
    '.slice(0, 5)',
    'crm-dashboard-overview-row',
    'var aiActions = digestSuggestedActions.length > 0',
    ': recommendedActions;',
    'По текущим данным нет срочных действий.',
    'data-dashboard-ai-action-index',
] as $needle) {
    assertDashboardContains($bindings, $needle, 'Dashboard bindings missing UX contract');
}

$start = strpos($bindings, 'async function renderDashboardPage()');
$end = strpos($bindings, 'async function renderAnalyticsPage()', $start ?: 0);
if ($start === false || $end === false || $end <= $start) {
    failDashboardUx('Cannot isolate renderDashboardPage');
}
$dashboardSource = substr($bindings, $start, $end - $start);
foreach (['window.confirm', 'window.prompt'] as $forbidden) {
    if (str_contains($dashboardSource, $forbidden)) {
        failDashboardUx('Dashboard renderer contains forbidden native dialog: ' . $forbidden);
    }
}

foreach ([
    'Dashboard QA pass',
    '.crm-dashboard-page .crm-dashboard-kpi',
    '.crm-dashboard-page .crm-dashboard-actions',
    '.crm-dashboard-page .crm-dashboard-table-wrap',
    '.crm-dashboard-page .crm-dashboard-overview-row',
    '.crm-dashboard-page .crm-dashboard-widget .crm-timeline',
] as $needle) {
    assertDashboardContains($pagesCss, $needle, 'Dashboard CSS missing visual contract');
}

fwrite(STDOUT, "[OK] web_dashboard_ux_security_contract_smoke\n");
