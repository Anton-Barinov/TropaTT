<?php
declare(strict_types=1);

function unitAssertProvider(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $source = (string)file_get_contents(__DIR__ . '/../../system/library/service/AiSuggestionService.php');
    unitAssertProvider($source !== '', 'AiSuggestionService source must be readable');
    unitAssertProvider(str_contains($source, '$llmCode = strtoupper(trim((string)($llm[\'code\'] ?? \'\')));'), 'llm code normalization must exist');
    unitAssertProvider(str_contains($source, "if (in_array(\$providerCode, ['mock', 'fake'], true))"), 'mock/fake provider branch must exist');
    unitAssertProvider(str_contains($source, "if (\$llmCode !== '' && \$llmCode !== 'OK')"), 'mock provider branch must return provider error when code is not OK');
    echo "[OK] ai_provider_error_mapping_guard_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_provider_error_mapping_guard_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
