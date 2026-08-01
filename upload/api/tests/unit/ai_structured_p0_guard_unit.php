<?php
declare(strict_types=1);

function unitAssertStructuredP0(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $servicePath = __DIR__ . '/../../system/library/service/AiSuggestionService.php';
    unitAssertStructuredP0(is_file($servicePath), 'AiSuggestionService file must exist');
    $source = (string)file_get_contents($servicePath);
    unitAssertStructuredP0($source !== '', 'AiSuggestionService source must be readable');

    unitAssertStructuredP0(str_contains($source, "if (\$structuredIntent) {\n            if (!\$llmOk) {\n                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];"), 'Structured intents must fail when LLM text is empty/unavailable');
    unitAssertStructuredP0(str_contains($source, "return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];"), 'Structured invalid must map to AI_STRUCTURED_RESPONSE_INVALID');
    unitAssertStructuredP0(str_contains($source, "if (\$trimmed[0] === '{')"), 'Structured parser must accept only top-level JSON object text');
    unitAssertStructuredP0(!str_contains($source, "preg_match('/```(?:json)?"), 'Structured parser must not accept markdown code fences as success');

    unitAssertStructuredP0(str_contains($source, "private function isStructuredIntent(string \$intentCode): bool"), 'Structured intent selector must exist');
    unitAssertStructuredP0(str_contains($source, "'task_summary'") && str_contains($source, "'task_decomposition'") && str_contains($source, "'task_quality'") && str_contains($source, "'task_next_action'") && str_contains($source, "'task_comment_draft'"), 'Task intent batch must be included in structured intent selector');
    unitAssertStructuredP0(str_contains($source, "'project_summary'") && str_contains($source, "'project_risk_summary'") && str_contains($source, "'project_client_report'"), 'Project intent batch must be included in structured intent selector');
    unitAssertStructuredP0(str_contains($source, "'client_summary'") && str_contains($source, "'client_meeting_prep'") && str_contains($source, "'client_data_quality'") && str_contains($source, "'client_safe_report'"), 'Client intent batch must be included in structured intent selector');
    unitAssertStructuredP0(str_contains($source, "'analytics_kpi_explanation'") && str_contains($source, "'analytics_risks_explanation'") && str_contains($source, "'analytics_team_workload_summary'"), 'Analytics batch must be included in structured intent selector');
    unitAssertStructuredP0(str_contains($source, "'calendar_event_agenda'") && str_contains($source, "'admin_log_review'") && str_contains($source, "'webhook_health_review'") && str_contains($source, "'workflow_rule_audit'"), 'Calendar/admin batch must be included in structured intent selector');
    unitAssertStructuredP0(str_contains($source, "'my_day_plan'") && str_contains($source, "'my_week_plan'") && str_contains($source, "'task_list_priority'"), 'Planning batch must be included in structured intent selector');
    unitAssertStructuredP0(str_contains($source, "private function structuredResponseInstruction(string \$intentCode): string"), 'structuredResponseInstruction must exist');
    unitAssertStructuredP0(str_contains($source, 'Return ONLY one JSON object. No markdown, no prose, no code fences.'), 'Structured instruction must explicitly forbid markdown/prose');

    unitAssertStructuredP0(str_contains($source, "'fallback_used' => false") && str_contains($source, "'raw_text_used' => false"), 'P0 success meta must mark fallback/raw_text as false');

    echo "[OK] ai_structured_p0_guard_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_structured_p0_guard_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
