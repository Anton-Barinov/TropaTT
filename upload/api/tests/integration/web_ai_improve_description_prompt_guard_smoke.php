<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function assertContainsImprove(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

try {
    $br1 = (string)file_get_contents(__DIR__ . '/../../../web/assets/js/br1.js');
    assertContainsImprove($br1, 'taskAiImproveDescBtn', 'Improve description button binding missing');
    assertContainsImprove($br1, 'structured JSON по системной схеме task_summary', 'Improve description prompt must require structured JSON schema');
    assertContainsImprove($br1, 'improved_description', 'Improve description prompt must mention improved_description field');
    assertContainsImprove($br1, 'update_task_description', 'Improve description prompt must mention update_task_description action');

    fwrite(STDOUT, "[OK] web_ai_improve_description_prompt_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_ai_improve_description_prompt_guard_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
