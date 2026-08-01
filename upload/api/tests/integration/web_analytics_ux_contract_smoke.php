<?php
declare(strict_types=1);

function failAnalyticsUx(string $message): void
{
    fwrite(STDERR, "[FAIL] web_analytics_ux_contract_smoke: {$message}\n");
    exit(1);
}

function readAnalyticsUxFile(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failAnalyticsUx('Cannot read file: ' . $path);
    }
    return $content;
}

$root = dirname(__DIR__, 3);
$template = readAnalyticsUxFile($root . '/web/view/template/page/analytics.php');
$bindings = readAnalyticsUxFile($root . '/web/assets/js/page-api-bindings.js');
$styles = readAnalyticsUxFile($root . '/web/assets/css/pages.css');

foreach ([
    'id="analyticsExportBtn"',
    'Экспорт CSV',
    'id="analyticsProjectFilter"',
    'id="analyticsTeamFilter"',
    'data-analytics-filter-note',
    'crm-analytics-ai-result',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failAnalyticsUx('Analytics template missing contract node: ' . $needle);
    }
}

foreach ([
    'populateAnalyticsFilter',
    'filterAnalyticsRows',
    'renderAnalyticsLists',
    'exportAnalyticsCsv',
    'analyticsCsvCell',
    "if (/^[=+\\-@]/.test(text))",
    'analyticsExportBtn.disabled = false',
    'analyticsProjectFilter.onchange = renderAnalyticsLists',
    'analyticsTeamFilter.onchange = renderAnalyticsLists',
] as $needle) {
    if (!str_contains($bindings, $needle)) {
        failAnalyticsUx('Page bindings missing analytics UX/security hook: ' . $needle);
    }
}

foreach ([
    '.crm-analytics-toolbar',
    '.crm-analytics-row-stats',
    '.crm-analytics-ai-result',
    '.crm-analytics-row-stats span.is-alert',
] as $needle) {
    if (!str_contains($styles, $needle)) {
        failAnalyticsUx('Analytics styles missing visual contract: ' . $needle);
    }
}

fwrite(STDOUT, "[OK] web_analytics_ux_contract_smoke\n");
