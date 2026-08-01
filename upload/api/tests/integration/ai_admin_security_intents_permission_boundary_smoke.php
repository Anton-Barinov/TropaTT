<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function findIntentByCode(array $items, string $intentCode): ?array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === $intentCode) {
            return $item;
        }
    }

    return null;
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);

    $adminScopedIntents = [
        'admin_log_review',
        'webhook_health_review',
        'workflow_rule_audit',
        'security_log_review',
    ];

    foreach ($adminScopedIntents as $intentCode) {
        $intent = findIntentByCode($intentItems, $intentCode);
        assertTrue(is_array($intent), 'Intent setting must exist: ' . $intentCode);

        $requiredPermission = trim((string)($intent['required_permission'] ?? ''));
        assertTrue($requiredPermission !== '', 'Admin/security/log intent must require explicit permission: ' . $intentCode);
        assertTrue($requiredPermission !== 'ai.use', 'Admin/security/log intent must not be accessible by ai.use only: ' . $intentCode);
    }

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_use_boundary_' . randomSuffix(),
        'title' => 'AI Use Boundary Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Role permissions set must be 200');

    $userLogin = 'ai.boundary.user.' . randomSuffix();
    $userPassword = 'AiBoundaryPass#2026!';
    $userToken = 'ai-boundary-token-' . randomSuffix();

    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Boundary User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'Boundary user create must be 201');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'Boundary user login must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $availability = request('GET', '/api/v1/ai/availability', [
        'intents' => implode(',', $adminScopedIntents),
    ], $userHeaders);
    assertTrue($availability['status'] === 200, 'AI availability status must be 200 for ai.use-only user');

    $availabilityIntents = (array)($availability['payload']['data']['intents'] ?? []);
    foreach ($adminScopedIntents as $intentCode) {
        $item = (array)($availabilityIntents[$intentCode] ?? []);
        assertTrue((bool)($item['enabled'] ?? true) === false, 'Admin/security/log intent must be disabled for ai.use-only user: ' . $intentCode);
        assertTrue((string)($item['reason'] ?? '') === 'permission_required', 'Disabled reason must be permission_required for: ' . $intentCode);
    }

    $adminLogReview = request('POST', '/api/v1/ai/admin/log-review', [], $userHeaders);
    assertTrue($adminLogReview['status'] === 403, 'ai.use-only user must get 403 on /ai/admin/log-review');

    $webhookHealth = request('POST', '/api/v1/ai/admin/webhook-health', [], $userHeaders);
    assertTrue($webhookHealth['status'] === 403, 'ai.use-only user must get 403 on /ai/admin/webhook-health');

    $workflowAudit = request('POST', '/api/v1/ai/admin/workflow-audit', [], $userHeaders);
    assertTrue($workflowAudit['status'] === 403, 'ai.use-only user must get 403 on /ai/admin/workflow-audit');

    fwrite(STDOUT, "[OK] ai_admin_security_intents_permission_boundary_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_admin_security_intents_permission_boundary_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
