<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$tasksTemplatePath = $root . '/web/view/template/page/tasks.php';
$bindingsPath = $root . '/web/assets/js/page-api-bindings.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_tasks_saved_views_smoke: {$message}\n");
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

$template = readFileSafe($tasksTemplatePath);
$bindings = readFileSafe($bindingsPath);

$requiredTemplateIds = [
    'tasksSavedViewSelect',
    'tasksSaveViewBtn',
    'tasksDeleteViewBtn',
    'tasksSearchInput',
    'tasksStatusFilter',
    'tasksPriorityFilter',
];

foreach ($requiredTemplateIds as $id) {
    if (!str_contains($template, 'id="' . $id . '"')) {
        failSmoke('tasks template id missing: ' . $id);
    }
}

$requiredBindings = [
    'currentViewPublicId',
    'applyTaskRouteQuery',
    'bindTasksSavedViews',
    "request('api/v1/views'",
    "request('api/v1/views/' + encodeURIComponent(targetId)",
    'view_public_id',
];

foreach ($requiredBindings as $snippet) {
    if (!str_contains($bindings, $snippet)) {
        failSmoke('tasks saved views binding missing snippet: ' . $snippet);
    }
}

fwrite(STDOUT, "[OK] web_tasks_saved_views_smoke\n");
