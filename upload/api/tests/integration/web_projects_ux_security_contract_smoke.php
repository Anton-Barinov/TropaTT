<?php
declare(strict_types=1);

function failProjectsUx(string $message): void
{
    fwrite(STDERR, "[FAIL] web_projects_ux_security_contract_smoke: {$message}\n");
    exit(1);
}

function readProjectsUxFile(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failProjectsUx('Cannot read file: ' . $path);
    }
    return $content;
}

function assertProjectsContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        failProjectsUx($message . ': ' . $needle);
    }
}

$root = dirname(__DIR__, 3);
$projectsTemplate = readProjectsUxFile($root . '/web/view/template/page/projects.php');
$modals = readProjectsUxFile($root . '/web/assets/js/modals.js');
$br1 = readProjectsUxFile($root . '/web/assets/js/br1.js');
$bindings = readProjectsUxFile($root . '/web/assets/js/page-api-bindings.js');
$pagesCss = readProjectsUxFile($root . '/web/assets/css/pages.css');
$calendarSmoke = readProjectsUxFile($root . '/api/tests/integration/calendar_smoke.php');

foreach ([
    'crm-page-intro',
    'id="projectsDeleteViewBtn" class="btn crm-btn-secondary" type="button" disabled',
    'id="projectsSaveViewModal"',
    'id="projectsSaveViewForm" novalidate',
] as $needle) {
    assertProjectsContains($projectsTemplate, $needle, 'Projects template missing UX contract');
}

foreach ([
    '<form id="createProjectForm" novalidate>',
] as $needle) {
    assertProjectsContains($modals, $needle, 'Project create modal missing validation contract');
}

foreach ([
    "form.setAttribute('novalidate', 'novalidate')",
    'showProjectCreateError',
    'Введите название проекта',
] as $needle) {
    assertProjectsContains($br1, $needle, 'Project create flow missing inline validation contract');
}

foreach ([
    'function confirmProjectsAction(options)',
    'projectsSaveViewModal',
    'projectsSaveViewForm',
    "deleteViewBtn.disabled = !String(savedViewSelect.value || '').trim()",
    'Будет удалено проектов:',
    "table.innerHTML = '<tr><td colspan=\"7\"",
    'var currentView = normalizeView(state.view);',
] as $needle) {
    assertProjectsContains($bindings, $needle, 'Projects bindings missing UX/security contract');
}

$start = strpos($bindings, 'async function renderProjectsPage()');
$end = strpos($bindings, 'async function renderProjectDetailPage()', $start ?: 0);
if ($start === false || $end === false || $end <= $start) {
    failProjectsUx('Cannot isolate renderProjectsPage');
}
$projectsSource = substr($bindings, $start, $end - $start);
foreach (['window.confirm', 'window.prompt'] as $forbidden) {
    if (str_contains($projectsSource, $forbidden)) {
        failProjectsUx('Projects page still contains forbidden native dialog: ' . $forbidden);
    }
}

foreach ([
    '.crm-projects-page .crm-page-head',
    'grid-template-columns: minmax(280px, 1fr) minmax(0, auto)',
    '.crm-projects-page #projectsBulkActionsBar',
] as $needle) {
    assertProjectsContains($pagesCss, $needle, 'Projects CSS missing visual contract');
}

foreach ([
    "request('DELETE', '/api/v1/tasks/' . \$taskPublicId",
    "request('DELETE', '/api/v1/projects/' . \$projectPublicId",
] as $needle) {
    assertProjectsContains($calendarSmoke, $needle, 'Calendar smoke must clean linked project fixtures');
}

fwrite(STDOUT, "[OK] web_projects_ux_security_contract_smoke\n");
