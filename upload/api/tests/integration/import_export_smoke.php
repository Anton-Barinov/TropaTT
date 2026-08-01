<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runImportExportSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $projectImport = request('POST', '/api/v1/import/jobs', [
        'type' => 'projects',
        'rows' => [
            [
                'title' => 'Imported project ' . $suffix,
                'description' => 'Imported from smoke',
                'status' => 'active',
                'priority' => 'normal',
            ],
        ],
    ], $headers);
    assertTrue($projectImport['status'] === 201, 'Project import create status must be 201');
    $projectImportId = (string)($projectImport['payload']['data']['job']['public_id'] ?? '');
    assertTrue($projectImportId !== '', 'Project import public_id is required');
    assertTrue(in_array((string)($projectImport['payload']['data']['job']['status'] ?? ''), ['completed', 'completed_with_errors'], true), 'Project import status must be completed');

    $createdProjects = (array)($projectImport['payload']['data']['job']['result']['created_items'] ?? []);
    assertTrue($createdProjects !== [], 'Project import must create project');
    $projectPublicId = (string)($createdProjects[0]['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Imported project public_id required');

    $projectGet = request('GET', '/api/v1/projects/' . $projectPublicId, [], $headers);
    assertTrue($projectGet['status'] === 200, 'Imported project get status must be 200');

    $csv = "title,description,project_public_id,status,priority\n" .
        'Imported task ' . $suffix . ',Imported from csv,' . $projectPublicId . ",new,high\n";

    $taskImport = request('POST', '/api/v1/import/create', [
        'type' => 'tasks',
        'format' => 'csv',
        'content_base64' => base64_encode($csv),
    ], $headers);
    assertTrue($taskImport['status'] === 201, 'Task import alias create status must be 201');
    $taskImportId = (string)($taskImport['payload']['data']['job']['public_id'] ?? '');
    assertTrue($taskImportId !== '', 'Task import public_id required');

    $taskImportStatus = request('GET', '/api/v1/import/status/' . $taskImportId, [], $headers);
    assertTrue($taskImportStatus['status'] === 200, 'Task import status must be 200');
    $createdTasks = (array)($taskImportStatus['payload']['data']['job']['result']['created_items'] ?? []);
    assertTrue($createdTasks !== [], 'Task import must create task');
    $taskPublicId = (string)($createdTasks[0]['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Imported task public_id required');

    $taskGet = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $headers);
    assertTrue($taskGet['status'] === 200, 'Imported task get status must be 200');

    $exportCreate = request('POST', '/api/v1/export/jobs', [
        'type' => 'tasks',
        'filters' => [
            'project_public_id' => $projectPublicId,
            'search' => $suffix,
        ],
    ], $headers);
    assertTrue($exportCreate['status'] === 201, 'Export create status must be 201');
    $exportPublicId = (string)($exportCreate['payload']['data']['job']['public_id'] ?? '');
    assertTrue($exportPublicId !== '', 'Export public_id required');
    assertTrue((int)($exportCreate['payload']['data']['job']['result']['summary']['rows_total'] ?? 0) >= 1, 'Export rows_total must be >= 1');

    $exportStatus = request('GET', '/api/v1/export/status?public_id=' . rawurlencode($exportPublicId), [], $headers);
    assertTrue($exportStatus['status'] === 200, 'Export status alias must be 200');
    $downloadUrl = (string)($exportStatus['payload']['data']['job']['result']['file']['download_url'] ?? '');
    assertTrue($downloadUrl !== '', 'Export download_url required');

    $importList = request('GET', '/api/v1/import/jobs?limit=5&search=' . rawurlencode($projectImportId), [], $headers);
    assertTrue($importList['status'] === 200, 'Import list must be 200');

    $exportList = request('GET', '/api/v1/export/jobs?limit=5&search=' . rawurlencode($exportPublicId), [], $headers);
    assertTrue($exportList['status'] === 200, 'Export list must be 200');

    $unauthorized = request('GET', '/api/v1/export/jobs');
    assertTrue($unauthorized['status'] === 401, 'Export list without token must be 401');
}

runImportExportSmoke();
echo "[OK] import_export_smoke\n";
