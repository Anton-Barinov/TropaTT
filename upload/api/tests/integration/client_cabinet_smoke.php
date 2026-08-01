<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $company = request('POST', '/api/v1/companies', [
        'title' => 'Cabinet Company ' . randomSuffix(),
    ], $headers);
    assertTrue($company['status'] === 201, 'Company create status must be 201');
    $companyPublicId = (string)($company['payload']['data']['company']['public_id'] ?? '');
    assertTrue($companyPublicId !== '', 'Company public_id required');

    $client = request('POST', '/api/v1/clients', [
        'title' => 'Cabinet Client ' . randomSuffix(),
        'company_public_id' => $companyPublicId,
        'email' => 'cab_client_' . randomSuffix() . '@crm.local',
        'status' => 'active',
    ], $headers);
    assertTrue($client['status'] === 201, 'Client create status must be 201');
    $clientPublicId = (string)($client['payload']['data']['client']['public_id'] ?? '');
    assertTrue($clientPublicId !== '', 'Client public_id required');

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Cabinet Project ' . randomSuffix(),
        'client_public_id' => $clientPublicId,
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Cabinet Task ' . randomSuffix(),
        'project_public_id' => $projectPublicId,
        'status' => 'in_progress',
    ], $headers);
    assertTrue($task['status'] === 201, 'Task create status must be 201');

    $list = request('GET', '/api/v1/client-cabinet/projects?client_public_id=' . urlencode($clientPublicId), [], $headers);
    assertTrue($list['status'] === 200, 'Client-cabinet projects status must be 200');
    assertTrue(($list['payload']['code'] ?? '') === 'CLIENT_CABINET_PROJECT_LIST', 'Client-cabinet projects code mismatch');
    assertTrue(count((array)($list['payload']['data']['items'] ?? [])) >= 1, 'Client-cabinet projects list must contain items');

    $detail = request('GET', '/api/v1/client-cabinet/projects/' . $projectPublicId . '?client_public_id=' . urlencode($clientPublicId), [], $headers);
    assertTrue($detail['status'] === 200, 'Client-cabinet project detail status must be 200');
    assertTrue(($detail['payload']['code'] ?? '') === 'CLIENT_CABINET_PROJECT_DETAIL', 'Client-cabinet project detail code mismatch');

    $tasks = request('GET', '/api/v1/client-cabinet/projects/' . $projectPublicId . '/tasks?client_public_id=' . urlencode($clientPublicId), [], $headers);
    assertTrue($tasks['status'] === 200, 'Client-cabinet project tasks status must be 200');
    assertTrue(($tasks['payload']['code'] ?? '') === 'CLIENT_CABINET_PROJECT_TASK_LIST', 'Client-cabinet project tasks code mismatch');
    assertTrue(count((array)($tasks['payload']['data']['items'] ?? [])) >= 1, 'Client-cabinet project tasks list must contain items');

    $forbidden = request('GET', '/api/v1/client-cabinet/projects/' . $projectPublicId . '?client_public_id=cln_NOT_MATCH', [], $headers);
    assertTrue($forbidden['status'] === 403, 'Client-scope mismatch status must be 403');
    assertTrue(($forbidden['payload']['code'] ?? '') === 'FORBIDDEN_CLIENT_SCOPE', 'Client-scope mismatch code mismatch');

    $alias = request('GET', '/api/v1/client/cabinet/projects?client_public_id=' . urlencode($clientPublicId), [], $headers);
    assertTrue($alias['status'] === 200, 'Client-cabinet alias status must be 200');

    $unauthorized = request('GET', '/api/v1/client-cabinet/projects?client_public_id=' . urlencode($clientPublicId));
    assertTrue($unauthorized['status'] === 401, 'Client-cabinet unauthorized status must be 401');

    echo "[OK] Client cabinet smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
