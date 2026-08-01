<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

try {
    $aiJs = (string)file_get_contents(__DIR__ . '/../../../web/assets/js/ai.js');
    $br1Js = (string)file_get_contents(__DIR__ . '/../../../web/assets/js/br1.js');
    $pageBindingsJs = (string)file_get_contents(__DIR__ . '/../../../web/assets/js/page-api-bindings.js');

    assertContains($aiJs, 'function canPreviewSuggestion(suggestion)', 'ai.js must expose canPreviewSuggestion helper');
    assertContains($aiJs, 'suggestionPreviewPolicyMessage', 'ai.js must expose stale preview policy message helper');
    assertContains($aiJs, 'AI_SUGGESTION_NOT_FOUND', 'ai.js must map AI_SUGGESTION_NOT_FOUND error');
    assertContains($aiJs, 'request_id:', 'ai.js must include request_id in error copy');

    assertContains($br1Js, 'canPreviewSuggestion(currentTaskAiSuggestion)', 'task preview flow must guard preview by cache policy');
    assertContains($pageBindingsJs, 'canPreviewSuggestion(currentProjectAiSuggestion)', 'project preview flow must guard preview by cache policy');
    assertContains($pageBindingsJs, 'canPreviewSuggestion(currentAnalyticsSuggestion)', 'analytics preview flow must guard preview by cache policy');

    fwrite(STDOUT, "[OK] web_ai_cache_preview_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_ai_cache_preview_guard_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
