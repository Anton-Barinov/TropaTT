<?php
declare(strict_types=1);

function unitAssertMockStructured(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $source = (string)file_get_contents(__DIR__ . '/../../system/library/service/MockAiProviderClient.php');
    unitAssertMockStructured($source !== '', 'MockAiProviderClient source must be readable');
    unitAssertMockStructured(str_contains($source, "strtolower(trim((string)(\$responseFormat['type'] ?? ''))) === 'json_object'"), 'Mock provider must detect json_object format');
    unitAssertMockStructured(str_contains($source, 'mockStructuredPayload('), 'Mock provider must build structured payload');
    unitAssertMockStructured(str_contains($source, "'client_summary'"), 'Mock provider structured payload must cover client_summary intent');
    unitAssertMockStructured(str_contains($source, "'client_safe_report'"), 'Mock provider structured payload must cover client_safe_report intent');
    echo "[OK] ai_mock_provider_structured_client_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_mock_provider_structured_client_unit: ' . $e->getMessage() . "\n");
    exit(1);
}

