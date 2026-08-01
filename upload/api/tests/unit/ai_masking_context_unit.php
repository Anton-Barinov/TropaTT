<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/service/AiMaskingService.php';

use Api\System\Library\Service\AiMaskingService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $masking = new AiMaskingService();

    $raw = 'Contact john.doe@example.com, phone +7 (999) 123-45-67, card 4111 1111 1111 1111';
    $masked = $masking->maskSensitiveText($raw);
    unitAssert(!str_contains($masked, 'john.doe@example.com'), 'Email must be masked');
    unitAssert(!str_contains($masked, '+7 (999) 123-45-67'), 'Phone must be masked');
    unitAssert(!str_contains($masked, '4111 1111 1111 1111'), 'Card-like sequence must be masked');
    unitAssert(str_contains($masked, '[masked]'), 'Masked marker must be present');

    $taskBuilderSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/TaskAiContextBuilder.php');
    unitAssert(str_contains($taskBuilderSource, "'description' => \$this->masking->maskSensitiveText"), 'Task context description must be masked at builder level');
    unitAssert(str_contains($taskBuilderSource, "'prompt' => \$this->masking->maskSensitiveText"), 'Task context prompt must be masked at builder level');
    unitAssert(str_contains($taskBuilderSource, "'title' => \$this->maskClientTitleByPolicy("), 'Task nested client title must follow client masking policy');

    $clientBuilderSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/ClientAiContextBuilder.php');
    unitAssert(str_contains($clientBuilderSource, "'title' => \$this->maskClientTitleByPolicy("), 'Client title must follow client masking policy');
    unitAssert(str_contains($clientBuilderSource, "individual' || \$normalizedType === 'sole_proprietor"), 'Personal client titles must be masked');

    echo "[OK] ai_masking_context_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_masking_context_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
