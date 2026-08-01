<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$templatePath = $root . '/web/view/template/page/task_detail.php';
$bindingsPath = $root . '/web/assets/js/br1.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_task_detail_collaboration_ui_smoke: {$message}\n");
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

$template = readFileSafe($templatePath);
$bindings = readFileSafe($bindingsPath);

foreach (['taskFollowBtn', 'taskFavoriteBtn', 'commentMentionUserSelect'] as $id) {
    if (!str_contains($template, 'id="' . $id . '"')) {
        failSmoke('task detail template id missing: ' . $id);
    }
}

$requiredSnippets = [
    'loadTaskCollaborationState(taskId)',
    "api/v1/subscriptions",
    "api/v1/favorites",
    "api/v1/reactions",
    "api/v1/mentions",
    'matchMentionedUsersFromText',
    'data-comment-react',
    'data-comment-reaction-clear',
];

foreach ($requiredSnippets as $snippet) {
    if (!str_contains($bindings, $snippet)) {
        failSmoke('task detail collaboration binding missing snippet: ' . $snippet);
    }
}

fwrite(STDOUT, "[OK] web_task_detail_collaboration_ui_smoke\n");
