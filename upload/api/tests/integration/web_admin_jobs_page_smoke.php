<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);

    $projectRoot = dirname(__DIR__, 3);
    $routes = require $projectRoot . '/web/config/routes.php';
    assertTrue(is_array($routes), 'web routes must be array');
    assertTrue(array_key_exists('admin-jobs', $routes), 'web route admin-jobs must exist');

    $tpl = file_get_contents($projectRoot . '/web/view/template/page/admin_jobs.php');
    assertTrue(is_string($tpl) && str_contains($tpl, 'data-page="admin-jobs"'), 'admin-jobs template marker missing');
    assertTrue(is_string($tpl) && str_contains($tpl, 'id="adminJobsImportForm"'), 'admin-jobs import form missing');
    assertTrue(is_string($tpl) && str_contains($tpl, 'id="adminJobsExportForm"'), 'admin-jobs export form missing');

    $bindings = file_get_contents($projectRoot . '/web/assets/js/page-api-bindings.js');
    assertTrue(is_string($bindings) && str_contains($bindings, 'async function renderAdminJobsPage()'), 'renderAdminJobsPage must exist');
    assertTrue(is_string($bindings) && str_contains($bindings, "if (route === 'admin-jobs') return await renderAdminJobsPage();"), 'admin-jobs route renderer must be connected');
    assertTrue(is_string($bindings) && str_contains($bindings, 'api/v1/import/jobs'), 'admin-jobs bindings must call import jobs API');
    assertTrue(is_string($bindings) && str_contains($bindings, 'api/v1/export/jobs'), 'admin-jobs bindings must call export jobs API');
    assertTrue(is_string($bindings) && str_contains($bindings, 'data-job-retry'), 'admin-jobs bindings must render retry actions');
    assertTrue(is_string($bindings) && str_contains($bindings, 'data-job-cancel'), 'admin-jobs bindings must render cancel actions');

    $import = request('POST', '/api/v1/import/jobs', [
        'type' => 'tasks',
        'rows' => [[
            'title' => 'Jobs UI import ' . randomSuffix(),
            'status' => 'new',
            'priority' => 'normal',
        ]],
    ], $rootHeaders);
    assertTrue($import['status'] === 201, 'Import job create must return 201');
    $importPublicId = (string)($import['payload']['data']['job']['public_id'] ?? '');
    assertTrue($importPublicId !== '', 'Import job public_id is required');

    $export = request('POST', '/api/v1/export/jobs', [
        'type' => 'tasks',
        'filters' => ['search' => 'Jobs UI import'],
    ], $rootHeaders);
    assertTrue($export['status'] === 201, 'Export job create must return 201');
    $exportPublicId = (string)($export['payload']['data']['job']['public_id'] ?? '');
    assertTrue($exportPublicId !== '', 'Export job public_id is required');

    $importRetry = request('POST', '/api/v1/import/jobs/' . $importPublicId . '/retry', [], $rootHeaders);
    assertTrue($importRetry['status'] === 201, 'Import job retry must return 201');

    $exportRetry = request('POST', '/api/v1/export/jobs/' . $exportPublicId . '/retry', [], $rootHeaders);
    assertTrue($exportRetry['status'] === 201, 'Export job retry must return 201');

    $importCancel = request('POST', '/api/v1/import/jobs/' . $importPublicId . '/cancel', [], $rootHeaders);
    assertTrue(in_array($importCancel['status'], [200, 409], true), 'Import job cancel must return 200/409');

    $exportCancel = request('POST', '/api/v1/export/jobs/' . $exportPublicId . '/cancel', [], $rootHeaders);
    assertTrue(in_array($exportCancel['status'], [200, 409], true), 'Export job cancel must return 200/409');

    $importList = request('GET', '/api/v1/import/jobs?limit=5', [], $rootHeaders);
    assertTrue($importList['status'] === 200, 'Import list must return 200');

    $exportList = request('GET', '/api/v1/export/jobs?limit=5', [], $rootHeaders);
    assertTrue($exportList['status'] === 200, 'Export list must return 200');

    $suffix = randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'jobs_view_only_' . $suffix,
        'title' => 'Jobs Restricted ' . $suffix,
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Restricted role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Restricted role public_id is required');

    $login = 'jobs.restricted.' . $suffix;
    $password = 'JobsRestricted#2026!';
    $token = 'jobs-restricted-token-' . $suffix;

    $userCreate = request('POST', '/api/v1/users', [
        'login' => $login,
        'password' => $password,
        'email' => $login . '@crm.local',
        'full_name' => 'Jobs Restricted User',
        'token' => $token,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'Restricted user create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');

    $auth = request('POST', '/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'token' => $token,
    ]);
    assertTrue($auth['status'] === 200, 'Restricted login must return 200');
    $restrictedHeaders = authHeaders((string)($auth['payload']['data']['access_token'] ?? ''));

    $forbiddenImport = request('GET', '/api/v1/import/jobs?limit=5', [], $restrictedHeaders);
    assertTrue($forbiddenImport['status'] === 403, 'Restricted role without import.manage must get 403 for import jobs');

    $forbiddenExport = request('GET', '/api/v1/export/jobs?limit=5', [], $restrictedHeaders);
    assertTrue($forbiddenExport['status'] === 403, 'Restricted role without export.manage must get 403 for export jobs');

    if ($userPublicId !== '') {
        request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    }
    if ($rolePublicId !== '') {
        request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] web_admin_jobs_page_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_admin_jobs_page_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
