<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$aiSuggestionServicePath = $root . '/api/system/library/service/AiSuggestionService.php';
$br1JsPath = $root . '/web/assets/js/br1.js';

function failSmoke557(string $message): void
{
    fwrite(STDERR, "[FAIL] ai_apply_endpoint_hint_internal_mapping_smoke: {$message}\n");
    exit(1);
}

function readFileSafe557(string $path): string
{
    if (!is_file($path)) {
        failSmoke557('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke557('unable to read file: ' . $path);
    }
    return $content;
}

function assertContains557(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failSmoke557($message . ' (needle: ' . $needle . ')');
    }
}

function assertNotContains557(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        failSmoke557($message . ' (needle: ' . $needle . ')');
    }
}

$serviceSource = readFileSafe557($aiSuggestionServicePath);
$br1Source = readFileSafe557($br1JsPath);

// Backend preview contract must expose only internal known endpoint hints.
assertContains557($serviceSource, "'supported_apply_endpoints' => [", 'preview must define supported_apply_endpoints allowlist');
assertContains557($serviceSource, "'/api/v1/tasks/{public_id}'", 'task patch endpoint hint must be internal fixed mapping');
assertContains557($serviceSource, "'/api/v1/tasks/{public_id}/comment-draft'", 'comment-draft endpoint hint must be internal fixed mapping');
assertContains557($serviceSource, "'/api/v1/tasks/{public_id}/subtasks'", 'subtasks endpoint hint must be internal fixed mapping');
assertContains557($serviceSource, "'/api/v1/tasks/{public_id}/checklists'", 'checklists endpoint hint must be internal fixed mapping');
assertContains557($serviceSource, "'/api/v1/checklists/{public_id}/items'", 'checklist items endpoint hint must be internal fixed mapping');
assertNotContains557($serviceSource, 'apply_endpoint_hint', 'backend preview/apply flow must not consume dynamic apply_endpoint_hint from payload');

// Web apply flow must be hard-mapped to existing endpoints and never build URL from hints.
assertContains557($br1Source, 'function applySelectedActions(actions)', 'applySelectedActions mapping function must exist');
assertContains557($br1Source, "api/v1/tasks/' + encodeURIComponent(taskId) + '/comment-draft", 'web apply must call existing comment-draft endpoint');
assertContains557($br1Source, "api/v1/tasks/' + encodeURIComponent(taskId) + '/subtasks", 'web apply must call existing subtasks endpoint');
assertContains557($br1Source, "api/v1/tasks/' + encodeURIComponent(taskId) + '/checklists", 'web apply must call existing checklists endpoint');
assertContains557($br1Source, "api/v1/checklists/' + encodeURIComponent(ownerChecklistId) + '/items", 'web apply must call existing checklist items endpoint');
assertContains557($br1Source, "api/v1/tasks/' + encodeURIComponent(taskId)", 'web apply must call existing task patch endpoint');
assertNotContains557($br1Source, 'apply_endpoint_hint', 'web apply flow must not read apply_endpoint_hint');

if (preg_match('/window\\.CRM\\.api\\.request\\([^\\n]*apply_endpoint_hint/i', $br1Source) === 1) {
    failSmoke557('dynamic request URL built from apply_endpoint_hint is forbidden');
}

if (preg_match('/window\\.CRM\\.api\\.request\\([^\\n]*(supported_apply_endpoints|meta\\.|raw\\.)/i', $br1Source) === 1) {
    failSmoke557('apply flow must not build request URL from suggestion meta/raw payload');
}

fwrite(STDOUT, "[OK] ai_apply_endpoint_hint_internal_mapping_smoke\n");
