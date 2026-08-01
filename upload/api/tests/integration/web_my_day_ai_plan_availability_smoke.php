<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $templatePath = dirname(__DIR__, 3) . '/web/view/template/page/my_day.php';
    $bindingsPath = dirname(__DIR__, 3) . '/web/assets/js/page-api-bindings.js';
    $aiJsPath = dirname(__DIR__, 3) . '/web/assets/js/ai.js';

    assertTrue(is_file($templatePath), 'my_day template must exist');
    assertTrue(is_file($bindingsPath), 'page-api-bindings.js must exist');
    assertTrue(is_file($aiJsPath), 'ai.js must exist');

    $template = (string)file_get_contents($templatePath);
    $bindings = (string)file_get_contents($bindingsPath);
    $aiJs = (string)file_get_contents($aiJsPath);

    assertTrue(str_contains($template, 'id="myDayAiCard"'), 'My-day template must expose AI card container');
    assertTrue(str_contains($template, 'id="myDayAiGenerateBtn"'), 'My-day template must expose AI generate button');
    assertTrue(str_contains($template, 'id="myDayAiPlanSummary"'), 'My-day template must expose AI plan summary node');
    assertTrue(str_contains($template, 'id="myDayAiPlanTasks"'), 'My-day template must expose AI plan tasks node');

    assertTrue(str_contains($bindings, "if (route === 'my-day') return await renderMyDayPage();"), 'My-day route must be bound to renderMyDayPage');
    assertTrue(str_contains($bindings, "request('api/v1/ai/my-day/plan'"), 'My-day AI generate must call canonical /api/v1/ai/my-day/plan endpoint');
    assertTrue(str_contains($bindings, "window.CRM.ai.createMyDayPlan(requestPayload)"), 'My-day AI generate must use shared CRM.ai helper when available');
    assertTrue(str_contains($aiJs, "return requestAi('api/v1/ai/my-day/plan', payload || {})"), 'Shared my-day AI helper must route through CRM.ai.requestAi');
    assertTrue(str_contains($bindings, "setMyDayAiState('loading', 'Формируем AI-план дня...');"), 'My-day AI generate must support manual recompute state transition');
    assertTrue(str_contains($bindings, "window.CRM.api.createIdempotencyKey('ai-my-day-plan')"), 'My-day AI generate must issue fresh idempotency key per manual run');

    fwrite(STDOUT, "[OK] web_my_day_ai_plan_availability_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_my_day_ai_plan_availability_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
