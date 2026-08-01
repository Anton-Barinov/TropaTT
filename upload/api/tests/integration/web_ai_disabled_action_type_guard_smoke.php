<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$servicePath = $root . '/api/system/library/service/AiSuggestionService.php';
$controllerPath = $root . '/api/controller/ai/AiActionController.php';
$serviceSource = is_file($servicePath) ? file_get_contents($servicePath) : false;
$controllerSource = is_file($controllerPath) ? file_get_contents($controllerPath) : false;

function failDisabledActionWeb(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_disabled_action_type_guard_smoke: {$message}\n");
    exit(1);
}

if (!is_string($serviceSource) || $serviceSource === '') {
    failDisabledActionWeb('unable to read AiSuggestionService source');
}
if (!is_string($controllerSource) || $controllerSource === '') {
    failDisabledActionWeb('unable to read AiActionController source');
}

foreach ([
    "isset(\$enabledActionTypes['update_task_description'])",
    "isset(\$enabledActionTypes['create_comment_draft'])",
    "isset(\$enabledActionTypes['create_subtask'])",
    "\$canCreateChecklist = isset(\$enabledActionTypes['create_checklist']);",
    "\$canCreateChecklistItem = isset(\$enabledActionTypes['create_checklist_item']);",
] as $needle) {
    if (strpos($serviceSource, $needle) === false) {
        failDisabledActionWeb('preview gating missing: ' . $needle);
    }
}

if (strpos($serviceSource, 'private function getEnabledActionTypesForPreview(): array') === false) {
    failDisabledActionWeb('enabled preview action-type resolver missing');
}
if (strpos($controllerSource, "'items' => \$service->enabledAllowlist()") === false) {
    failDisabledActionWeb('action-types endpoint must expose enabledAllowlist only');
}

fwrite(STDOUT, "[OK] web_ai_disabled_action_type_guard_smoke\n");
