<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Service\AiMaskingService;

function assertContainsOrFail(string $haystack, string $needle, string $message): void
{
    assertTrue(str_contains($haystack, $needle), $message . ' (needle: ' . $needle . ')');
}

try {
    $masking = new AiMaskingService();

    $maskedEmail = $masking->maskSensitiveText('client@example.com');
    assertTrue($maskedEmail === '[masked]', 'Email must be masked by AiMaskingService');

    $maskedPhone = $masking->maskSensitiveText('+7 (999) 123-45-67');
    assertTrue($maskedPhone === '[masked]', 'Phone must be masked by AiMaskingService');

    $maskedCard = $masking->maskSensitiveText('5484 1234 5678 9012');
    assertTrue($maskedCard === '[masked]', 'Long payment/account-like numbers must be masked by AiMaskingService');

    $clientBuilderPath = __DIR__ . '/../../system/library/service/ClientAiContextBuilder.php';
    assertTrue(is_file($clientBuilderPath), 'ClientAiContextBuilder file must exist');
    $clientBuilder = file_get_contents($clientBuilderPath);
    assertTrue(is_string($clientBuilder) && $clientBuilder !== '', 'ClientAiContextBuilder file must be readable');

    $requiredMaskedFields = [
        "'notes' => \$this->masking->maskSensitiveText(",
        "'email' => \$this->masking->maskSensitiveText(",
        "'phone' => \$this->masking->maskSensitiveText(",
        "'tax_inn' => \$this->masking->maskSensitiveText(",
        "'tax_kpp' => \$this->masking->maskSensitiveText(",
        "'tax_ogrn' => \$this->masking->maskSensitiveText(",
        "'tax_ogrnip' => \$this->masking->maskSensitiveText(",
        "'bank_account' => \$this->masking->maskSensitiveText(",
        "'bank_bik' => \$this->masking->maskSensitiveText(",
        "'bank_corr_account' => \$this->masking->maskSensitiveText(",
        "'bank_name' => \$this->masking->maskSensitiveText(",
        "'address_legal' => \$this->masking->maskSensitiveText(",
        "'address_postal' => \$this->masking->maskSensitiveText(",
        "'prompt' => \$this->masking->maskSensitiveText(",
    ];

    foreach ($requiredMaskedFields as $needle) {
        assertContainsOrFail($clientBuilder, $needle, 'ClientAiContextBuilder must apply masking policy for sensitive field');
    }
    assertContainsOrFail(
        $clientBuilder,
        "'title' => \$this->maskClientTitleByPolicy(",
        'Client AI context must apply masking policy to title for personal client types'
    );
    assertContainsOrFail(
        $clientBuilder,
        "if (\$normalizedType === 'individual' || \$normalizedType === 'sole_proprietor')",
        'Client AI context must mask individual and sole proprietor titles'
    );

    $taskBuilderPath = __DIR__ . '/../../system/library/service/TaskAiContextBuilder.php';
    assertTrue(is_file($taskBuilderPath), 'TaskAiContextBuilder file must exist');
    $taskBuilder = file_get_contents($taskBuilderPath);
    assertTrue(is_string($taskBuilder) && $taskBuilder !== '', 'TaskAiContextBuilder file must be readable');
    assertContainsOrFail(
        $taskBuilder,
        "'title' => \$this->maskClientTitleByPolicy(",
        'Task AI nested client summary must apply title masking policy for personal client types'
    );

    $suggestionServicePath = __DIR__ . '/../../system/library/service/AiSuggestionService.php';
    assertTrue(is_file($suggestionServicePath), 'AiSuggestionService file must exist');
    $suggestionService = file_get_contents($suggestionServicePath);
    assertTrue(is_string($suggestionService) && $suggestionService !== '', 'AiSuggestionService file must be readable');

    $clientIntentFlows = [
        "ensureIntentAccessBeforeContextBuild('client_summary'",
        "ensureIntentAccessBeforeContextBuild('client_meeting_prep'",
        "ensureIntentAccessBeforeContextBuild('client_data_quality'",
        "ensureIntentAccessBeforeContextBuild('client_safe_report'",
    ];

    foreach ($clientIntentFlows as $needle) {
        assertContainsOrFail($suggestionService, $needle, 'Client-related intent must have explicit guarded flow in AiSuggestionService');
    }
    assertContainsOrFail(
        $suggestionService,
        "'client_summary' => ['client_public_id', 'title', 'status', 'client_type', 'notes', 'prompt']",
        'Client summary context must use an explicit intent allowlist'
    );
    assertContainsOrFail(
        $suggestionService,
        "'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'upcoming_events', 'open_tasks', 'recent_projects', 'prompt']",
        'Client-safe report context must use a reduced allowlist without sensitive fields'
    );
    assertTrue(
        !str_contains($suggestionService, "'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'notes'")
        && !str_contains($suggestionService, "'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'email'")
        && !str_contains($suggestionService, "'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'phone'")
        && !str_contains($suggestionService, "'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'tax_inn'")
        && !str_contains($suggestionService, "'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'bank_account'"),
        'Client-safe report context must not include direct sensitive client fields'
    );
    assertTrue(
        !str_contains($suggestionService, 'internal_comments')
        && !str_contains($suggestionService, 'comment_excerpt')
        && !str_contains($suggestionService, 'recent_comments'),
        'Client-safe report flow must not include internal comments in AI context or payload scaffolding'
    );
    assertContainsOrFail(
        $suggestionService,
        "'prompt_runtime' => \$this->sanitizePromptRuntimeForStorage(\$promptEnvelope)",
        'Client intent runtime persistence must keep only sanitized prompt-runtime metadata'
    );
    assertContainsOrFail(
        $suggestionService,
        '$this->runtime->cleanupByRetention($this->retention->getPolicies())',
        'Client intent runtime data must remain governed by AI retention cleanup'
    );

    fwrite(STDOUT, "[OK] ai_client_intent_masking_policy_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_client_intent_masking_policy_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
