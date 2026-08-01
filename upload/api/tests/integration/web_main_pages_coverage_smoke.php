<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_main_pages_coverage_smoke: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke("file not found: {$path}");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke("unable to read file: {$path}");
    }
    return $content;
}

$routesPath = $webRoot . '/config/routes.php';
$routes = require $routesPath;
if (!is_array($routes)) {
    failSmoke('web/config/routes.php must return array');
}

$required = [
    'dashboard' => ['template' => 'dashboard.php', 'data_pages' => ['dashboard']],
    'tasks' => ['template' => 'tasks.php', 'data_pages' => ['tasks']],
    'task-detail' => ['template' => 'task_detail.php', 'data_pages' => ['task-detail', 'tasks']],
    'projects' => ['template' => 'projects.php', 'data_pages' => ['projects']],
    'calendar' => ['template' => 'calendar.php', 'data_pages' => ['calendar']],
    'my-day' => ['template' => 'my_day.php', 'data_pages' => ['my-day', 'day']],
    'admin' => ['template' => 'admin.php', 'data_pages' => ['admin']],
];

foreach ($required as $route => $definition) {
    $templateFile = (string)($definition['template'] ?? '');
    $allowedDataPages = is_array($definition['data_pages'] ?? null) ? (array)$definition['data_pages'] : [];
    if ($templateFile === '' || $allowedDataPages === []) {
        failSmoke("invalid page definition for {$route}");
    }

    if (!array_key_exists($route, $routes)) {
        failSmoke("required route is missing: {$route}");
    }

    $handler = $routes[$route];
    if (!is_array($handler) || count($handler) !== 2) {
        failSmoke("route handler shape must be [Controller::class, method] for {$route}");
    }

    $templatePath = $webRoot . '/view/template/page/' . $templateFile;
    $template = readFileSafe($templatePath);

    $hasAllowedDataPage = false;
    foreach ($allowedDataPages as $pageCode) {
        if (strpos($template, 'data-page="' . (string)$pageCode . '"') !== false) {
            $hasAllowedDataPage = true;
            break;
        }
    }
    if (!$hasAllowedDataPage) {
        failSmoke("template {$templateFile} must expose one of allowed data-page values for route {$route}");
    }

    if (strpos($template, 'data-protected="1"') === false) {
        failSmoke("template {$templateFile} must stay in protected web-shell flow");
    }
}

fwrite(STDOUT, "[OK] web_main_pages_coverage_smoke\n");
