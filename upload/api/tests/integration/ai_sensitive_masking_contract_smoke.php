<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../system/library/service/AiMaskingService.php';

use Api\System\Library\Service\AiMaskingService;

/**
 * @param list<string> $needles
 */
function assertContainsAll(string $haystack, array $needles, string $messagePrefix): void
{
    foreach ($needles as $needle) {
        assertTrue(str_contains($haystack, $needle), $messagePrefix . ': missing `' . $needle . '`');
    }
}

try {
    $masking = new AiMaskingService();
    $raw = 'Email: john.doe@example.com; Phone: +7 (999) 123-45-67; Card: 4111 1111 1111 1111';
    $masked = $masking->maskSensitiveText($raw);

    assertTrue(!str_contains($masked, 'john.doe@example.com'), 'Masking must hide email');
    assertTrue(!str_contains($masked, '+7 (999) 123-45-67'), 'Masking must hide phone');
    assertTrue(!str_contains($masked, '4111 1111 1111 1111'), 'Masking must hide card-like value');
    assertTrue(substr_count($masked, '[masked]') >= 3, 'Masking markers expected in sanitized text');

    $clientBuilderSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/ClientAiContextBuilder.php');
    assertContainsAll($clientBuilderSource, [
        "'email' => \$this->masking->maskSensitiveText",
        "'phone' => \$this->masking->maskSensitiveText",
        "'tax_inn' => \$this->masking->maskSensitiveText",
        "'tax_kpp' => \$this->masking->maskSensitiveText",
        "'tax_ogrn' => \$this->masking->maskSensitiveText",
        "'tax_ogrnip' => \$this->masking->maskSensitiveText",
        "'bank_account' => \$this->masking->maskSensitiveText",
        "'bank_bik' => \$this->masking->maskSensitiveText",
        "'bank_corr_account' => \$this->masking->maskSensitiveText",
        "'bank_name' => \$this->masking->maskSensitiveText",
        "'address_legal' => \$this->masking->maskSensitiveText",
        "'address_postal' => \$this->masking->maskSensitiveText",
        "'prompt' => \$this->masking->maskSensitiveText",
    ], 'Client AI context masking contract');

    $taskBuilderSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/TaskAiContextBuilder.php');
    assertContainsAll($taskBuilderSource, [
        "'description' => \$this->masking->maskSensitiveText",
        "'prompt' => \$this->masking->maskSensitiveText",
        "'notes' => \$this->masking->maskSensitiveText",
    ], 'Task AI context masking contract');

    fwrite(STDOUT, "[OK] ai_sensitive_masking_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_sensitive_masking_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
