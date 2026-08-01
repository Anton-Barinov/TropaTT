<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = dirname(__DIR__, 3);
    $navigation = (string)file_get_contents($root . '/web/assets/js/navigation.js');
    $ai = (string)file_get_contents($root . '/web/assets/js/ai.js');

    assertTrue(str_contains($navigation, 'data-search-mode-toggle'), 'Global search must expose a separate semantic mode toggle');
    assertTrue(str_contains($navigation, 'window.CRM.ai.semanticSearch'), 'Navigation semantic mode must use window.CRM.ai.semanticSearch');
    assertTrue(str_contains($ai, "requestAi('api/v1/ai/search/semantic'"), 'AI module must call canonical semantic search endpoint');
    assertTrue(!str_contains($navigation, 'fetch('), 'Navigation semantic mode must not use direct fetch');
    assertTrue(!str_contains($ai, 'fetch('), 'AI module must not use direct fetch');
    assertTrue(!preg_match("/https?:\\/\\/[^'\"]*(openai|anthropic|deepseek|gemini|mistral)/i", $navigation . "\n" . $ai), 'Web semantic search must not contain direct provider URLs');

    echo "[OK] web_ai_semantic_search_mode_smoke\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_ai_semantic_search_mode_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
