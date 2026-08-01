<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Service\AiMaskingService;

function assertContains621(string $haystack, string $needle, string $message): void
{
    assertTrue(str_contains($haystack, $needle), $message . ' (needle: ' . $needle . ')');
}

try {
    $masking = new AiMaskingService();
    assertTrue($masking->maskSensitiveText('client.sensitive@example.com') === '[masked]', 'Email must be masked before any persistence');
    assertTrue($masking->maskSensitiveText('+7 (999) 123-45-67') === '[masked]', 'Phone must be masked before any persistence');
    assertTrue($masking->maskSensitiveText('12345678901234567890') === '[masked]', 'Bank account must be masked before any persistence');

    $intentServicePath = __DIR__ . '/../../system/library/service/AiIntentSettingService.php';
    $intentService = file_get_contents($intentServicePath);
    assertTrue(is_string($intentService) && $intentService !== '', 'AiIntentSettingService must be readable');
    assertContains621($intentService, "'allow_sensitive_context' => 0,", 'New AI intents must default allow_sensitive_context to 0');
    assertContains621($intentService, "if (array_key_exists('allow_sensitive_context', \$input)) {", 'Intent settings update must handle explicit allow_sensitive_context changes only');

    $migrationPath = __DIR__ . '/../../system/library/database/migration/AiFoundationMigration.php';
    $migration = file_get_contents($migrationPath);
    assertTrue(is_string($migration) && $migration !== '', 'AiFoundationMigration must be readable');
    assertContains621($migration, 'allow_sensitive_context {$bool} DEFAULT 0', 'DB schema must default allow_sensitive_context to 0');
    assertContains621($migration, 'is_sensitive_context {$bool} DEFAULT 0', 'Runtime usage logs must default is_sensitive_context to 0');

    $clientBuilderPath = __DIR__ . '/../../system/library/service/ClientAiContextBuilder.php';
    $clientBuilder = file_get_contents($clientBuilderPath);
    assertTrue(is_string($clientBuilder) && $clientBuilder !== '', 'ClientAiContextBuilder must be readable');
    foreach ([
        "'email' => \$this->masking->maskSensitiveText(",
        "'phone' => \$this->masking->maskSensitiveText(",
        "'tax_inn' => \$this->masking->maskSensitiveText(",
        "'bank_account' => \$this->masking->maskSensitiveText(",
        "'address_legal' => \$this->masking->maskSensitiveText(",
        "'prompt' => \$this->masking->maskSensitiveText(",
    ] as $needle) {
        assertContains621($clientBuilder, $needle, 'Sensitive client context must be masked before entering AI runtime payloads');
    }

    $suggestionServicePath = __DIR__ . '/../../system/library/service/AiSuggestionService.php';
    $suggestionService = file_get_contents($suggestionServicePath);
    assertTrue(is_string($suggestionService) && $suggestionService !== '', 'AiSuggestionService must be readable');
    assertContains621($suggestionService, "'is_sensitive_context' => 0,", 'Suggestion runtime logging must default is_sensitive_context to 0');
    assertContains621($suggestionService, "'prompt_runtime' => \$this->sanitizePromptRuntimeForStorage(\$promptEnvelope)", 'Stored prompt runtime must go through sanitized persistence helper');
    assertContains621($suggestionService, "'input' => \$this->sanitizeInput(\$input)", 'Stored AI input must go through sanitized persistence helper');
    assertContains621($suggestionService, "private function sanitizePromptRuntimeForStorage(array \$promptRuntime): array", 'Sanitized prompt runtime helper must exist');
    assertContains621($suggestionService, "'context_truncated' => (bool)(\$promptRuntime['meta']['context_truncated'] ?? false),", 'Only safe prompt-runtime meta should remain in storage');

    $actionServicePath = __DIR__ . '/../../system/library/service/AiActionService.php';
    $actionService = file_get_contents($actionServicePath);
    assertTrue(is_string($actionService) && $actionService !== '', 'AiActionService must be readable');
    assertContains621($actionService, "'is_sensitive_context' => 0,", 'Action runtime logging must default is_sensitive_context to 0');
    assertContains621($actionService, "return '[redacted]';", 'Action input sanitizer must redact free-form prompt text');

    fwrite(STDOUT, "[OK] ai_sensitive_context_not_stored_by_default_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_sensitive_context_not_stored_by_default_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
