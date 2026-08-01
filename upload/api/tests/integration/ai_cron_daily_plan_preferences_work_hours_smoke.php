<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $checks
 * @return array<string,mixed>|null
 */
function findCheckByName(array $checks, string $name): ?array
{
    foreach ($checks as $check) {
        if (!is_array($check)) {
            continue;
        }
        if ((string)($check['name'] ?? '') === $name) {
            return $check;
        }
    }

    return null;
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_cron_pref_' . randomSuffix(),
        'title' => 'AI Cron Preference Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userLogin = 'ai.cron.pref.' . randomSuffix();
    $userPassword = 'AiCronPrefPass#2026!';
    $userToken = 'ai-cron-pref-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Cron Preference User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'User create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'User public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'User login status must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $setPreferencesOff = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'daily_plan_enabled' => 0,
            'work_hours_start' => '10:30',
            'work_hours_end' => '19:15',
            'focus_block_minutes' => 45,
            'preferred_response_length' => 'medium',
        ],
    ], $userHeaders);
    assertTrue($setPreferencesOff['status'] === 200, 'Set user AI preferences (daily plan disabled) must be 200');

    $dryRunDisabled = request('POST', '/api/v1/ai/jobs/ai:user-daily-work-plan/dry-run', [
        'scope_public_id' => $userPublicId,
    ], $rootHeaders);
    assertTrue($dryRunDisabled['status'] === 200, 'Dry-run with disabled daily plan preference must return 200');
    $dryRunDisabledData = (array)($dryRunDisabled['payload']['data']['dry_run'] ?? []);
    $checksDisabled = (array)($dryRunDisabledData['checks'] ?? []);
    $executionContextDisabled = (array)($dryRunDisabledData['execution_context'] ?? []);

    $dailyPlanCheckDisabled = findCheckByName($checksDisabled, 'daily_plan_enabled');
    assertTrue(is_array($dailyPlanCheckDisabled), 'Dry-run checks must include daily_plan_enabled');
    assertTrue((bool)($dailyPlanCheckDisabled['ok'] ?? true) === false, 'daily_plan_enabled check must fail when user opted out');

    $workHoursDisabled = (array)($executionContextDisabled['work_hours'] ?? []);
    assertTrue((string)($workHoursDisabled['start'] ?? '') === '10:30', 'Execution context must use user work_hours_start preference');
    assertTrue((string)($workHoursDisabled['end'] ?? '') === '19:15', 'Execution context must use user work_hours_end preference');
    assertTrue((string)($executionContextDisabled['work_hours_source'] ?? '') === 'preferences', 'Execution context work_hours_source must be preferences');

    $preferencesDisabled = (array)($executionContextDisabled['preferences'] ?? []);
    assertTrue((bool)($preferencesDisabled['daily_plan_enabled'] ?? true) === false, 'Execution context preferences must include daily_plan_enabled=false');
    assertTrue((int)($preferencesDisabled['focus_block_minutes'] ?? 0) === 45, 'Execution context preferences must include focus_block_minutes from user preferences');
    assertTrue((string)($preferencesDisabled['preferred_response_length'] ?? '') === 'medium', 'Execution context preferences must include preferred_response_length from user preferences');
    assertTrue((string)($executionContextDisabled['preferences_source'] ?? '') === 'preferences', 'Execution context preferences_source must be preferences');

    $setPreferencesOn = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'daily_plan_enabled' => 1,
            'work_hours_start' => '08:45',
            'work_hours_end' => '17:30',
            'focus_block_minutes' => 60,
            'preferred_response_length' => 'long',
        ],
    ], $userHeaders);
    assertTrue($setPreferencesOn['status'] === 200, 'Set user AI preferences (daily plan enabled) must be 200');

    $dryRunEnabled = request('POST', '/api/v1/ai/jobs/ai:user-daily-work-plan/dry-run', [
        'scope_public_id' => $userPublicId,
    ], $rootHeaders);
    assertTrue($dryRunEnabled['status'] === 200, 'Dry-run with enabled daily plan preference must return 200');
    $dryRunEnabledData = (array)($dryRunEnabled['payload']['data']['dry_run'] ?? []);
    $checksEnabled = (array)($dryRunEnabledData['checks'] ?? []);
    $executionContextEnabled = (array)($dryRunEnabledData['execution_context'] ?? []);

    $dailyPlanCheckEnabled = findCheckByName($checksEnabled, 'daily_plan_enabled');
    assertTrue(is_array($dailyPlanCheckEnabled), 'Dry-run checks must include daily_plan_enabled when preference enabled');
    assertTrue((bool)($dailyPlanCheckEnabled['ok'] ?? false) === true, 'daily_plan_enabled check must pass when user opted in');

    $workHoursEnabled = (array)($executionContextEnabled['work_hours'] ?? []);
    assertTrue((string)($workHoursEnabled['start'] ?? '') === '08:45', 'Execution context must use updated user work_hours_start preference');
    assertTrue((string)($workHoursEnabled['end'] ?? '') === '17:30', 'Execution context must use updated user work_hours_end preference');

    $preferencesEnabled = (array)($executionContextEnabled['preferences'] ?? []);
    assertTrue((bool)($preferencesEnabled['daily_plan_enabled'] ?? false) === true, 'Execution context preferences must include daily_plan_enabled=true');
    assertTrue((int)($preferencesEnabled['focus_block_minutes'] ?? 0) === 60, 'Execution context preferences must include updated focus_block_minutes');
    assertTrue((string)($preferencesEnabled['preferred_response_length'] ?? '') === 'long', 'Execution context preferences must include updated preferred_response_length');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_cron_daily_plan_preferences_work_hours_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_cron_daily_plan_preferences_work_hours_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
