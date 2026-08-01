<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$taskPagePath = $root . '/web/view/template/page/task_detail.php';
$taskJsPath = $root . '/web/assets/js/br1.js';

function failRowVersionWeb(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_task_description_diff_guard_smoke: {$message}\n");
    exit(1);
}

function readFileRowVersion(string $path): string
{
    if (!is_file($path)) {
        failRowVersionWeb('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failRowVersionWeb('unable to read file: ' . $path);
    }
    return $content;
}

function assertContainsRowVersion(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failRowVersionWeb($message . ' (needle: ' . $needle . ')');
    }
}

$taskPage = readFileRowVersion($taskPagePath);
$taskJs = readFileRowVersion($taskJsPath);

assertContainsRowVersion($taskPage, 'id="taskAiDescriptionDiffModal"', 'Task description diff modal missing');
assertContainsRowVersion($taskPage, 'Проверьте diff перед применением. Изменение описания требует актуальную `row_version`.', 'Task description diff copy missing');
assertContainsRowVersion($taskPage, 'id="taskAiDescriptionDiffOld"', 'Task description old-value diff node missing');
assertContainsRowVersion($taskPage, 'id="taskAiDescriptionDiffNew"', 'Task description new-value diff node missing');
assertContainsRowVersion($taskPage, 'id="taskAiDescriptionDiffApplyBtn"', 'Task description diff apply button missing');

assertContainsRowVersion($taskJs, 'async function confirmDescriptionDiff(newDescription)', 'Task description diff confirmation helper missing');
assertContainsRowVersion($taskJs, "if (actionType === 'update_task' || actionField === 'task.description')", 'Task description apply branch missing');
assertContainsRowVersion($taskJs, "code: 'AI_ROW_VERSION_CONFLICT'", 'Task description apply branch must raise row-version conflict without current row version');
assertContainsRowVersion($taskJs, 'row_version: rowVersionUsed', 'Task description apply branch must send row_version to task patch endpoint');
assertContainsRowVersion($taskJs, 'var confirmed = await confirmDescriptionDiff(actionValue);', 'Task description apply branch must require diff confirmation before patch');

fwrite(STDOUT, "[OK] web_ai_task_description_diff_guard_smoke\n");
