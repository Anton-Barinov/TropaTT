<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $suggestionServicePath = __DIR__ . '/../../system/library/service/AiSuggestionService.php';
    $promptBuilderPath = __DIR__ . '/../../system/library/service/AiPromptBuilderService.php';

    assertTrue(is_file($suggestionServicePath), 'AiSuggestionService file must exist');
    assertTrue(is_file($promptBuilderPath), 'AiPromptBuilderService file must exist');

    $suggestionService = (string)file_get_contents($suggestionServicePath);
    $promptBuilder = (string)file_get_contents($promptBuilderPath);

    assertTrue($suggestionService !== '', 'AiSuggestionService must be readable');
    assertTrue($promptBuilder !== '', 'AiPromptBuilderService must be readable');

    assertTrue(
        str_contains($suggestionService, 'private function useStrictPromptMaskingForProvider'),
        'AiSuggestionService must contain provider-aware strict masking helper'
    );
    assertTrue(
        str_contains($suggestionService, '$strictPromptMasking = $this->useStrictPromptMaskingForProvider($provider);'),
        'Suggestion flows must calculate strict masking from provider'
    );
    assertTrue(
        str_contains($suggestionService, 'buildPromptEnvelope($intentCode, $prompt, $minimalContext, $input, 0, $strictPromptMasking)'),
        'Prompt envelope must receive strict masking flag in generic intent flows'
    );
    assertTrue(
        str_contains($suggestionService, "['mock', 'local', 'self_hosted', 'self-hosted', 'ollama', 'lm_studio', 'lmstudio']"),
        'Local/self-hosted provider codes must remain on relaxed masking branch'
    );

    assertTrue(
        str_contains($promptBuilder, 'private function sanitizePromptText'),
        'Prompt builder must sanitize prompt/context text before envelope build'
    );
    assertTrue(
        str_contains($promptBuilder, '(?:authorization|cookie)'),
        'Prompt sanitizer must mask authorization/cookie-like values'
    );
    assertTrue(
        str_contains($promptBuilder, 'base64'),
        'Prompt sanitizer must guard base64/binary-like payload fragments'
    );

    fwrite(STDOUT, "[OK] ai_prompt_masking_policy_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_prompt_masking_policy_guard_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
