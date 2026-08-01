<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$aiJsPath = $root . '/web/assets/js/ai.js';
$taskJsPath = $root . '/web/assets/js/br1.js';

function failFollowUpGuard(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_follow_up_task_explicit_selection_guard_smoke: {$message}\n");
    exit(1);
}

function readFollowUpGuard(string $path): string
{
    if (!is_file($path)) {
        failFollowUpGuard('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failFollowUpGuard('unable to read file: ' . $path);
    }
    return $content;
}

function assertContainsFollowUpGuard(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failFollowUpGuard($message . ' (needle: ' . $needle . ')');
    }
}

$aiJs = readFollowUpGuard($aiJsPath);
$taskJs = readFollowUpGuard($taskJsPath);

assertContainsFollowUpGuard($aiJs, "requires_explicit_selection", 'Drawer action renderer must understand requires_explicit_selection');
assertContainsFollowUpGuard($aiJs, "Нужно выбрать вручную: будет создана новая business entity", 'Drawer must explain explicit selection requirement for follow-up task');
assertContainsFollowUpGuard($aiJs, "requiresExplicitSelection ? '' : ' checked'", 'Explicit-selection action must not be preselected by default');
assertContainsFollowUpGuard($taskJs, "actionType === 'create_subtask' || actionType === 'create_follow_up_task'", 'Task AI apply flow must support explicit follow-up action type');

fwrite(STDOUT, "[OK] web_ai_follow_up_task_explicit_selection_guard_smoke\n");
