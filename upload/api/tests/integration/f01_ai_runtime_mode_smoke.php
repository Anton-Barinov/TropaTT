<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $settingsService = (string)file_get_contents(__DIR__ . '/../../system/library/service/AiSettingsService.php');
    $providerService = (string)file_get_contents(__DIR__ . '/../../system/library/service/AiProviderService.php');
    $aiJs = (string)file_get_contents(__DIR__ . '/../../../web/assets/js/ai.js');
    $bindings = (string)file_get_contents(__DIR__ . '/../../../web/assets/js/page-api-bindings.js');
    $llmDocs = (string)file_get_contents(__DIR__ . '/../../docs/llm.md');

    assertTrue(str_contains($settingsService, "'runtime_mode' => 'staged'"), 'AI settings must default runtime_mode to staged');
    assertTrue(str_contains($settingsService, "['mock', 'staged', 'real']"), 'AI settings must restrict runtime_mode values');
    assertTrue(str_contains($providerService, 'AI_MOCK_MODE_PROVIDER_REQUIRED'), 'Provider service must guard mock runtime mode');
    assertTrue(str_contains($providerService, 'AI_REAL_MODE_PROVIDER_REQUIRED'), 'Provider service must guard real runtime mode');
    assertTrue(str_contains($providerService, "'runtime_mode' => \$runtimeMode"), 'Completion response must include runtime_mode');
    assertTrue(str_contains($bindings, 'runtimeModeLabel(runtimeMode)'), 'Admin AI page must display runtime mode');
    assertTrue(str_contains($aiJs, 'showAiActionNotice'), 'AI JS must expose user-facing action notice flow');
    assertTrue(str_contains($aiJs, 'без передачи паролей, токенов и секретов'), 'AI notice must explain secret redaction boundary');
    assertTrue(str_contains($llmDocs, 'Runtime mode policy'), 'LLM docs must describe runtime mode policy');
    assertTrue(str_contains($llmDocs, 'Real mode checklist'), 'LLM docs must include real mode checklist');
    assertTrue(str_contains($llmDocs, 'Fallback is not silent'), 'LLM docs must document fallback policy');
    assertTrue(str_contains($llmDocs, 'Prompt injection boundary'), 'LLM docs must document prompt injection boundary');

    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
