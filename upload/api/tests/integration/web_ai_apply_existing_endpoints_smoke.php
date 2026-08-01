<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$br1JsPath = $root . '/web/assets/js/br1.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_apply_existing_endpoints_smoke: {$message}\n");
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

$br1Js = readFileSafe($br1JsPath);

// Task-detail drawer apply flow must be selected-only.
assertContains($br1Js, 'function applySelectedActions(actions)', 'selected apply function missing');
assertContains($br1Js, 'var selected = Array.isArray(actions) ? actions : [];', 'selected-only apply guard missing');
assertContains($br1Js, 'if (selected.length === 0)', 'empty selection guard missing');

// Apply flow must use existing business endpoints, not direct AI write endpoints.
assertContains($br1Js, "api/v1/tasks/' + encodeURIComponent(taskId) + '/comment-draft", 'comment-draft apply endpoint missing');
assertContains($br1Js, "api/v1/tasks/' + encodeURIComponent(taskId) + '/subtasks", 'subtasks apply endpoint missing');
assertContains($br1Js, "api/v1/tasks/' + encodeURIComponent(taskId) + '/checklists", 'checklists apply endpoint missing');
assertContains($br1Js, "api/v1/checklists/' + encodeURIComponent(ownerChecklistId) + '/items", 'checklist items apply endpoint missing');
assertContains($br1Js, "api/v1/tasks/' + encodeURIComponent(taskId)", 'task patch apply endpoint missing');
assertContains($br1Js, 'openTaskLinkedCalendarModal()', 'UI-only calendar actions must delegate to modal flow');

assertContains($br1Js, "'/api/v1/tasks/{public_id}/comment-draft'", 'comment-draft apply target hint missing');
assertContains($br1Js, "'/api/v1/tasks/{public_id}/subtasks'", 'subtasks apply target hint missing');
assertContains($br1Js, "'/api/v1/tasks/{public_id}/checklists'", 'checklists apply target hint missing');
assertContains($br1Js, "'/api/v1/checklists/{public_id}/items'", 'checklist items apply target hint missing');
assertContains($br1Js, "'/api/v1/tasks/{public_id}'", 'task update apply target hint missing');

// AI endpoints in this flow are lifecycle-only (preview/confirm), not business writes.
assertContains($br1Js, 'api/v1/ai/suggestions/', 'AI suggestion lifecycle route missing');
assertContains($br1Js, '/preview-apply', 'AI preview lifecycle route missing');
assertContains($br1Js, '/confirm', 'AI confirm lifecycle route missing');

if (preg_match("#/api/v1/ai/(tasks|projects|clients|calendar|workflow|admin)/[^\\\"']*/(subtasks|checklists|comment-draft|items|events)#i", $br1Js) === 1) {
    failSmoke('detected AI-scoped business write endpoint; apply must use existing non-AI endpoints');
}

$branchStart = strpos($br1Js, "if (\n          actionType === 'create_meeting'");
if ($branchStart === false) {
    failSmoke('unable to locate UI-only calendar action branch start');
}
$branchEndMarker = "          continue;\n        }";
$branchEnd = strpos($br1Js, $branchEndMarker, $branchStart);
if ($branchEnd === false) {
    failSmoke('unable to locate UI-only calendar action branch end');
}
$uiOnlyBranch = substr($br1Js, $branchStart, $branchEnd - $branchStart + strlen($branchEndMarker));
if (!is_string($uiOnlyBranch) || $uiOnlyBranch === '') {
    failSmoke('unable to isolate UI-only calendar action branch');
}
if (strpos($uiOnlyBranch, "request('api/v1/calendar/events'") !== false || strpos($uiOnlyBranch, "window.CRM.api.request('api/v1/calendar/events'") !== false) {
    failSmoke('UI-only calendar action branch must not perform direct calendar writes');
}

fwrite(STDOUT, "[OK] web_ai_apply_existing_endpoints_smoke\n");
