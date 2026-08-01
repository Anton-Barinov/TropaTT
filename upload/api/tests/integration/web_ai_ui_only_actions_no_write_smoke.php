<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$taskJsPath = $root . '/web/assets/js/br1.js';

function failUiOnlyGuard(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_ui_only_actions_no_write_smoke: {$message}\n");
    exit(1);
}

function readUiOnlyGuard(string $path): string
{
    if (!is_file($path)) {
        failUiOnlyGuard('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failUiOnlyGuard('unable to read file: ' . $path);
    }
    return $content;
}

function assertContainsUiOnly(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failUiOnlyGuard($message . ' (needle: ' . $needle . ')');
    }
}

$taskJs = readUiOnlyGuard($taskJsPath);

assertContainsUiOnly($taskJs, "actionType === 'create_meeting'", 'UI-only create_meeting branch missing');
assertContainsUiOnly($taskJs, "|| actionType === 'create_calendar_event'", 'UI-only create_calendar_event branch missing');
assertContainsUiOnly($taskJs, "|| actionType === 'schedule_meeting'", 'UI-only schedule_meeting branch missing');
assertContainsUiOnly($taskJs, 'if (!openTaskLinkedCalendarModal()) {', 'UI-only meeting branch must open modal instead of writing directly');
assertContainsUiOnly($taskJs, "code: 'AI_CALENDAR_MODAL_NOT_AVAILABLE'", 'UI-only meeting branch must fail safely when modal is unavailable');
assertContainsUiOnly($taskJs, "createMeetingBtn.addEventListener('click'", 'Dedicated create-meeting shortcut missing');
assertContainsUiOnly($taskJs, "notify('Открыта форма встречи. Проверьте детали и сохраните событие вручную.');", 'Create-meeting shortcut must explain manual save requirement');

$branchStart = strpos($taskJs, "if (\n          actionType === 'create_meeting'");
if ($branchStart === false) {
    failUiOnlyGuard('unable to locate UI-only calendar action branch start');
}
$branchEndMarker = "          continue;\n        }";
$branchEnd = strpos($taskJs, $branchEndMarker, $branchStart);
if ($branchEnd === false) {
    failUiOnlyGuard('unable to locate UI-only calendar action branch end');
}
$uiOnlyBranch = substr($taskJs, $branchStart, $branchEnd - $branchStart + strlen($branchEndMarker));
if (!is_string($uiOnlyBranch) || $uiOnlyBranch === '') {
    failUiOnlyGuard('unable to isolate UI-only calendar action branch');
}
if (strpos($uiOnlyBranch, "request('api/v1/calendar/events'") !== false || strpos($uiOnlyBranch, "window.CRM.api.request('api/v1/calendar/events'") !== false) {
    failUiOnlyGuard('UI-only calendar action branch must not create direct calendar write requests');
}
if (strpos($uiOnlyBranch, 'openTaskLinkedCalendarModal()') === false) {
    failUiOnlyGuard('UI-only calendar action branch must delegate to modal flow');
}

fwrite(STDOUT, "[OK] web_ai_ui_only_actions_no_write_smoke\n");
