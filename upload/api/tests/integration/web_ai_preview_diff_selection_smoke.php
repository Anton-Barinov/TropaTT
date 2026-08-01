<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$aiJsPath = $root . '/web/assets/js/ai.js';
$foundationSmokePath = $root . '/api/tests/integration/ai_foundation_smoke.php';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_preview_diff_selection_smoke: {$message}\n");
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

$aiJs = readFileSafe($aiJsPath);
$foundationSmoke = readFileSafe($foundationSmokePath);

// Drawer must expose dedicated preview/diff area.
assertContains($aiJs, 'id="aiSuggestionDrawerDiff"', 'AI drawer diff/preview container missing');
assertContains($aiJs, 'Diff/preview', 'AI drawer diff/preview label missing');

// Drawer apply button must be selected-only.
assertContains($aiJs, 'id="aiSuggestionDrawerApplyBtn"', 'AI drawer apply button missing');
assertContains($aiJs, 'Применить выбранное', 'AI drawer selected-apply copy missing');
assertContains($aiJs, 'function selectedDrawerActions()', 'selected actions helper missing');
assertContains($aiJs, '[data-ai-action-checkbox]:checked', 'selected actions must be computed from checked preview actions');
assertContains($aiJs, 'drawerHandlers.onApply(selected)', 'apply handler must receive selected-only actions');

// Preview actions are sourced from canonical preview.changes.
assertContains($aiJs, 'function normalizePreviewActions(preview)', 'normalizePreviewActions helper missing');
assertContains($aiJs, 'Array.isArray(preview.changes)', 'preview changes array handling missing');
assertContains($aiJs, 'data-ai-action-checkbox', 'preview action checkboxes missing');

// Backend/API smoke must enforce preview with actionable changes for task intents.
assertContains($foundationSmoke, 'Suggestion preview must require explicit confirmation', 'foundation smoke preview confirmation assertion missing');
assertContains($foundationSmoke, 'Task decompose preview must contain selectable subtask actions', 'foundation smoke selected preview actions assertion missing for task_decompose');
assertContains($foundationSmoke, 'Task checklist preview must contain selectable checklist actions', 'foundation smoke selected preview actions assertion missing for task_checklist');
assertContains($foundationSmoke, 'Task next-action preview must contain selectable actions', 'foundation smoke selected preview actions assertion missing for task_next_action');

fwrite(STDOUT, "[OK] web_ai_preview_diff_selection_smoke\n");

