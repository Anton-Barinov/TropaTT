<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $intentCode = 'task_summary';
    $locale = 'zz-act-audit';

    $createV1 = request('POST', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
        'version' => 1,
        'template_text' => 'Single-active v1 ' . randomSuffix(),
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($createV1['status'] === 201, 'Prompt v1 create must return 201');
    $v1PublicId = (string)($createV1['payload']['data']['prompt']['public_id'] ?? '');
    assertTrue($v1PublicId !== '', 'Prompt v1 public_id is required');

    $createV2 = request('POST', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
        'version' => 2,
        'template_text' => 'Single-active v2 ' . randomSuffix(),
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($createV2['status'] === 201, 'Prompt v2 create must return 201');
    $v2PublicId = (string)($createV2['payload']['data']['prompt']['public_id'] ?? '');
    assertTrue($v2PublicId !== '', 'Prompt v2 public_id is required');

    $listAfterCreate = request('GET', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
    ], $rootHeaders);
    assertTrue($listAfterCreate['status'] === 200, 'Prompt list after create must return 200');

    $itemsAfterCreate = (array)($listAfterCreate['payload']['data']['items'] ?? []);
    $activeAfterCreate = [];
    foreach ($itemsAfterCreate as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') !== $intentCode || (string)($item['locale'] ?? '') !== $locale) {
            continue;
        }
        if ((bool)($item['is_active'] ?? false)) {
            $activeAfterCreate[] = (string)($item['public_id'] ?? '');
        }
    }

    assertTrue(count($activeAfterCreate) === 1, 'Exactly one active prompt must exist for intent+locale after second active create');
    assertTrue($activeAfterCreate[0] === $v2PublicId, 'Latest active prompt must stay active after create');

    $activateV1 = request('PATCH', '/api/v1/ai/prompt-templates/' . $v1PublicId, [
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($activateV1['status'] === 200, 'Prompt v1 activation update must return 200');

    $listAfterUpdate = request('GET', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
    ], $rootHeaders);
    assertTrue($listAfterUpdate['status'] === 200, 'Prompt list after update must return 200');

    $itemsAfterUpdate = (array)($listAfterUpdate['payload']['data']['items'] ?? []);
    $activeAfterUpdate = [];
    foreach ($itemsAfterUpdate as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') !== $intentCode || (string)($item['locale'] ?? '') !== $locale) {
            continue;
        }
        if ((bool)($item['is_active'] ?? false)) {
            $activeAfterUpdate[] = (string)($item['public_id'] ?? '');
        }
    }

    assertTrue(count($activeAfterUpdate) === 1, 'Exactly one active prompt must exist for intent+locale after update activation');
    assertTrue($activeAfterUpdate[0] === $v1PublicId, 'Activated prompt must become the only active version');

    $audit = request('GET', '/api/v1/ai/audit', ['limit' => 300], $rootHeaders);
    assertTrue($audit['status'] === 200, 'AI audit list must return 200');

    $auditItems = (array)($audit['payload']['data']['items'] ?? []);
    $hasPromptUpdateAudit = false;
    foreach ($auditItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['action'] ?? '') !== 'ai_prompt_template_updated') {
            continue;
        }
        if ((string)($item['entity_public_id'] ?? '') === $v1PublicId) {
            $hasPromptUpdateAudit = true;
            break;
        }
    }

    assertTrue($hasPromptUpdateAudit, 'Prompt update must be written to AI audit log');

    fwrite(STDOUT, "[OK] ai_prompt_single_active_audit_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_prompt_single_active_audit_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
