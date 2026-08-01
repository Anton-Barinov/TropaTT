<?php
declare(strict_types=1);

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $repoSource = (string)file_get_contents(__DIR__ . '/../../model/ai/AiRuntimeRepository.php');
    unitAssert($repoSource !== '', 'AiRuntimeRepository source must be readable');
    unitAssert(
        str_contains($repoSource, "->whereIn('status', ['draft', 'ready', 'applied', 'partially_applied', 'dismissed', 'confirmed'])"),
        'cache lookup must support actionable and read-only cached statuses'
    );

    $serviceSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/AiSuggestionService.php');
    unitAssert($serviceSource !== '', 'AiSuggestionService source must be readable');
    unitAssert(str_contains($serviceSource, "'can_apply' =>"), 'cache meta must expose can_apply');
    unitAssert(str_contains($serviceSource, 'canApplyCachedSuggestion('), 'canApplyCachedSuggestion helper must exist');
    unitAssert(str_contains($serviceSource, 'isPreviewApplicableIntent('), 'isPreviewApplicableIntent helper must exist');

    echo "[OK] ai_cache_preview_contract_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_cache_preview_contract_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
