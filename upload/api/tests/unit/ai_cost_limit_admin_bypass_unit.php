<?php
declare(strict_types=1);

function unitAssertCostBypass(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $source = (string)file_get_contents(__DIR__ . '/../../system/library/service/AiCostLimitService.php');
    unitAssertCostBypass($source !== '', 'AiCostLimitService source must be readable');
    unitAssertCostBypass(str_contains($source, 'if ($this->isAiAdminActor($actor)) {'), 'AiCostLimitService must bypass limits for ai.admin actors');
    unitAssertCostBypass(str_contains($source, "trim((string)\$permission) === 'ai.admin'"), 'AiCostLimitService must check ai.admin permission code');
    echo "[OK] ai_cost_limit_admin_bypass_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_cost_limit_admin_bypass_unit: ' . $e->getMessage() . "\n");
    exit(1);
}

