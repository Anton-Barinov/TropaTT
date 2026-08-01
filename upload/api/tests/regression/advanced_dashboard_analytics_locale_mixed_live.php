<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicDashboardAnalytics(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicDashboardAnalytics($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'dash_locale_' . $suffix,
        'title' => 'Dashboard Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'dash_locale_' . $suffix;
    $token = 'dash-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'DashboardLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'DashboardLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $dashboard = liveRequest('GET', 'api/v1/dashboard/summary', [], $headers);
    liveAssert($dashboard['status'] === 200, 'Dashboard summary must return 200');
    liveAssert((string)($dashboard['payload']['message'] ?? '') === 'Dashboard summary', 'Dashboard summary message mismatch');
    assertNoCyrillicDashboardAnalytics($dashboard['payload'], 'dashboard.summary.payload');

    $analyticsSummary = liveRequest('GET', 'api/v1/analytics/summary', [], $headers);
    liveAssert($analyticsSummary['status'] === 200, 'Analytics summary must return 200');
    liveAssert((string)($analyticsSummary['payload']['message'] ?? '') === 'Analytics summary', 'Analytics summary message mismatch');
    assertNoCyrillicDashboardAnalytics($analyticsSummary['payload'], 'analytics.summary.payload');

    $analyticsProjects = liveRequest('GET', 'api/v1/analytics/projects', ['limit' => 5], $headers);
    liveAssert($analyticsProjects['status'] === 200, 'Analytics projects must return 200');
    liveAssert((string)($analyticsProjects['payload']['message'] ?? '') === 'Project analytics', 'Analytics projects message mismatch');
    assertNoCyrillicDashboardAnalytics($analyticsProjects['payload'], 'analytics.projects.payload');

    $analyticsUsers = liveRequest('GET', 'api/v1/analytics/users', ['limit' => 5], $headers);
    liveAssert($analyticsUsers['status'] === 200, 'Analytics users must return 200');
    liveAssert((string)($analyticsUsers['payload']['message'] ?? '') === 'User analytics', 'Analytics users message mismatch');
    assertNoCyrillicDashboardAnalytics($analyticsUsers['payload'], 'analytics.users.payload');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_dashboard_analytics_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_dashboard_analytics_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
