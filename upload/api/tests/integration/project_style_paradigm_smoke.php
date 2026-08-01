<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$apiRoot = $root . '/api';
$webRoot = $root . '/web';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] project_style_paradigm_smoke: {$message}\n");
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

if (is_file($apiRoot . '/composer.json')) {
    $composer = readFileSafe($apiRoot . '/composer.json');
    if (preg_match('/"(?:laravel\\/framework|symfony\\/http-kernel|yiisoft\\/yii2|cakephp\\/cakephp)"/i', $composer) === 1) {
        failSmoke('heavy backend framework dependency detected in api/composer.json');
    }
}

$webIndex = readFileSafe($webRoot . '/index.php');
if (strpos($webIndex, 'new Web\\System\\Core\\Router') === false || strpos($webIndex, '$router->dispatch($route);') === false) {
    failSmoke('web/index.php must keep MPA router dispatch pattern');
}
if (strpos($webIndex, 'spl_autoload_register') === false) {
    failSmoke('web/index.php must keep lightweight manual autoload pattern');
}

$aiJs = readFileSafe($webRoot . '/assets/js/ai.js');
if (strpos($aiJs, 'window.CRM') === false) {
    failSmoke('ai.js must keep window.CRM namespace integration pattern');
}

$pageBindings = readFileSafe($webRoot . '/assets/js/page-api-bindings.js');
if (strpos($pageBindings, 'window.CRM') === false) {
    failSmoke('page-api-bindings.js must keep window.CRM hydration pattern');
}

$adminAiTemplate = readFileSafe($webRoot . '/view/template/page/admin_ai.php');
if (preg_match('/\b(?:modal|offcanvas|btn|container-fluid|row|col-)\b/', $adminAiTemplate) !== 1) {
    failSmoke('admin_ai template must keep Bootstrap-like layout/pattern classes');
}
if (strpos($adminAiTemplate, 'crm-') === false) {
    failSmoke('admin_ai template must keep existing crm-* class naming patterns');
}

fwrite(STDOUT, "[OK] project_style_paradigm_smoke\n");
