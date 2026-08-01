<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Service\AiMaskingService;

try {
    $masking = new AiMaskingService();

    assertTrue($masking->classifyField('email') === 'personal', 'email must be classified as personal');
    assertTrue($masking->classifyField('client_phone') === 'personal', 'phone must be classified as personal');
    assertTrue($masking->classifyField('tax_inn') === 'sensitive', 'tax fields must be classified as sensitive');
    assertTrue($masking->classifyField('bank_account') === 'sensitive', 'bank fields must be classified as sensitive');
    assertTrue($masking->classifyField('api_key') === 'secret', 'api_key must be classified as secret');
    assertTrue($masking->classifyField('prompt') === 'sensitive', 'prompt must be classified as sensitive');

    $metadata = $masking->contextPolicyMetadata([
        'client_public_id' => 'clt_demo',
        'email' => 'client@example.com',
        'tax_inn' => '1234567890',
        'api_key' => 'must-not-leak',
        'status' => 'active',
    ]);
    assertTrue((bool)($metadata['contains_personal'] ?? false) === true, 'Context metadata must detect personal fields');
    assertTrue((bool)($metadata['contains_sensitive'] ?? false) === true, 'Context metadata must detect sensitive fields');
    assertTrue(in_array('api_key', (array)($metadata['secret_fields_blocked'] ?? []), true), 'Context metadata must list blocked secret fields');

    $usageService = file_get_contents(__DIR__ . '/../../system/library/service/AiUsageService.php');
    assertTrue(is_string($usageService) && str_contains($usageService, "str_contains(\$name, 'prompt')"), 'AiUsageService must redact prompt-like metadata');
    assertTrue(str_contains($usageService, "str_contains(\$name, 'message')"), 'AiUsageService must redact message-like metadata');

    $promptBuilder = file_get_contents(__DIR__ . '/../../system/library/service/AiPromptBuilderService.php');
    assertTrue(is_string($promptBuilder) && str_contains($promptBuilder, "'prompt',"), 'AiPromptBuilderService must treat prompt keys as sensitive context');
    assertTrue(str_contains($promptBuilder, 'User input (untrusted content)'), 'AI user prompt must preserve untrusted-content boundary');

    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

