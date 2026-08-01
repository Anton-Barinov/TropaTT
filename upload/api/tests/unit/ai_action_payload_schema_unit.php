<?php
declare(strict_types=1);

function unitAssertActionSchema(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $servicePath = __DIR__ . '/../../system/library/service/AiSuggestionService.php';
    unitAssertActionSchema(is_file($servicePath), 'AiSuggestionService file must exist');
    $source = (string)file_get_contents($servicePath);
    unitAssertActionSchema($source !== '', 'AiSuggestionService source must be readable');

    unitAssertActionSchema(str_contains($source, 'private function isActionPayloadValid(string $actionType, array $payload): bool'), 'Action payload schema validator method must exist');

    unitAssertActionSchema(str_contains($source, "if (\$normalized === 'update_task_description')"), 'update_task_description validator branch must exist');
    unitAssertActionSchema(str_contains($source, "strlen(\$description) <= 20000"), 'update_task_description must enforce max description length');

    unitAssertActionSchema(str_contains($source, "if (\$normalized === 'create_comment_draft')"), 'create_comment_draft validator branch must exist');
    unitAssertActionSchema(str_contains($source, "strlen(\$body) <= 20000"), 'create_comment_draft must enforce max body length');

    unitAssertActionSchema(str_contains($source, "if (\$normalized === 'create_subtask' || \$normalized === 'create_follow_up_task')"), 'create_subtask/create_follow_up_task validator branch must exist');
    unitAssertActionSchema(str_contains($source, "if (\$title === '' || strlen(\$title) > 255)"), 'create_subtask/follow_up_task must enforce title length');

    unitAssertActionSchema(str_contains($source, "if (\$normalized === 'create_checklist')"), 'create_checklist validator branch must exist');
    unitAssertActionSchema(str_contains($source, "return \$title !== '' && strlen(\$title) <= 255;"), 'create_checklist must enforce checklist_title length');

    unitAssertActionSchema(str_contains($source, "if (\$normalized === 'create_checklist_item')"), 'create_checklist_item validator branch must exist');
    unitAssertActionSchema(str_contains($source, "array_key_exists('checklist_public_id', \$payload)"), 'create_checklist_item must validate optional checklist_public_id type');

    echo "[OK] ai_action_payload_schema_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_action_payload_schema_unit: ' . $e->getMessage() . "\n");
    exit(1);
}

