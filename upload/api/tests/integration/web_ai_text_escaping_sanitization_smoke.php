<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $aiJsPath = dirname(__DIR__, 3) . '/web/assets/js/ai.js';
    $bindingsPath = dirname(__DIR__, 3) . '/web/assets/js/page-api-bindings.js';

    assertTrue(is_file($aiJsPath), 'web/assets/js/ai.js must exist');
    assertTrue(is_file($bindingsPath), 'web/assets/js/page-api-bindings.js must exist');

    $aiJs = (string)file_get_contents($aiJsPath);
    $bindingsJs = (string)file_get_contents($bindingsPath);

    assertTrue(str_contains($aiJs, 'function escapeHtml(value)'), 'ai.js must define escapeHtml helper');
    assertTrue(str_contains($aiJs, "summaryNode.innerHTML = '<strong>' + escapeHtml(String(payload.summary || suggestion.summary || 'AI-предложение')) + '</strong>'"), 'ai.js summary render must escape payload summary');
    assertTrue(str_contains($aiJs, "return '<strong>' + escapeHtml(change.field) + '</strong>: ' + escapeHtml(change.value);"), 'ai.js preview change renderer must escape field/value');
    assertTrue(str_contains($aiJs, "sourceNode.innerHTML = ''"), 'ai.js source renderer must exist');
    assertTrue(str_contains($aiJs, "escapeHtml(String(suggestion.intent_code || ''))"), 'ai.js source renderer must escape intent');
    assertTrue(str_contains($aiJs, "escapeHtml(String(suggestion.entity_public_id || ''))"), 'ai.js source renderer must escape entity_public_id');

    assertTrue(str_contains($bindingsJs, "function safeText(value)"), 'page-api-bindings.js must define safeText helper');
    assertTrue(str_contains($bindingsJs, "window.CRM.br1 ? window.CRM.br1.escapeHtml(value) : String(value || '')"), 'safeText must route through escapeHtml');
    assertTrue(str_contains($bindingsJs, "aiDigestSummaryNode.innerHTML = '<strong>' + safeText(digestSummary || 'AI-сводка дня') + '</strong>'"), 'dashboard AI summary render must escape text');
    assertTrue(str_contains($bindingsJs, "aiDayPlanSummary.innerHTML = '<strong>' + safeText(String(payload.summary || suggestion.summary || 'AI-план дня')) + '</strong>'"), 'calendar day-plan summary render must escape payload summary');

    $aiLines = preg_split('/\\R/', $aiJs) ?: [];
    foreach ($aiLines as $line) {
        if (!str_contains($line, 'innerHTML') || !str_contains($line, 'payload.')) {
            continue;
        }
        assertTrue(str_contains($line, 'escapeHtml('), 'ai.js must not render payload fields into innerHTML without escapeHtml');
    }

    $bindingLines = preg_split('/\\R/', $bindingsJs) ?: [];
    foreach ($bindingLines as $line) {
        if (!str_contains($line, 'innerHTML') || !str_contains($line, 'payload.')) {
            continue;
        }
        assertTrue(str_contains($line, 'safeText('), 'page-api-bindings.js must not render payload fields into innerHTML without safeText');
    }

    fwrite(STDOUT, "[OK] web_ai_text_escaping_sanitization_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_ai_text_escaping_sanitization_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
