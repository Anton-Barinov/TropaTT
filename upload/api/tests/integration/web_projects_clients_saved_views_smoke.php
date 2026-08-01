<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);

    $projectRoot = dirname(__DIR__, 3);

    $projectsTpl = file_get_contents($projectRoot . '/web/view/template/page/projects.php');
    assertTrue(is_string($projectsTpl) && str_contains($projectsTpl, 'id="projectsSavedViewSelect"'), 'projects saved view select missing');
    $clientsTpl = file_get_contents($projectRoot . '/web/view/template/page/clients.php');
    assertTrue(is_string($clientsTpl) && str_contains($clientsTpl, 'id="clientsSavedViewSelect"'), 'clients saved view select missing');

    $bindings = file_get_contents($projectRoot . '/web/assets/js/page-api-bindings.js');
    assertTrue(is_string($bindings) && str_contains($bindings, 'projectsSaveViewBtn'), 'projects saved view bindings missing');
    assertTrue(is_string($bindings) && str_contains($bindings, 'clientsSaveViewBtn'), 'clients saved view bindings missing');

    $projectViewCreate = request('POST', '/api/v1/views', [
        'entity_type' => 'project',
        'title' => 'Projects view ' . randomSuffix(),
        'filters' => [
            'search' => 'smoke-project',
            'status' => 'active',
            'sort_by' => 'created_at',
            'sort_dir' => 'DESC',
        ],
    ], $rootHeaders);
    assertTrue($projectViewCreate['status'] === 201, 'Project view create must return 201');
    $projectViewId = (string)($projectViewCreate['payload']['data']['view']['public_id'] ?? '');
    assertTrue($projectViewId !== '', 'Project view public_id is required');

    $clientViewCreate = request('POST', '/api/v1/views', [
        'entity_type' => 'client',
        'title' => 'Clients view ' . randomSuffix(),
        'filters' => [
            'search' => 'smoke-client',
            'status' => 'active',
            'sort_by' => 'updated_at',
            'sort_dir' => 'ASC',
        ],
    ], $rootHeaders);
    assertTrue($clientViewCreate['status'] === 201, 'Client view create must return 201');
    $clientViewId = (string)($clientViewCreate['payload']['data']['view']['public_id'] ?? '');
    assertTrue($clientViewId !== '', 'Client view public_id is required');

    $projectViewList = request('GET', '/api/v1/views?entity_type=project&limit=20', [], $rootHeaders);
    assertTrue($projectViewList['status'] === 200, 'Project views list must return 200');
    $clientViewList = request('GET', '/api/v1/views?entity_type=client&limit=20', [], $rootHeaders);
    assertTrue($clientViewList['status'] === 200, 'Client views list must return 200');

    request('DELETE', '/api/v1/views/' . $projectViewId, [], $rootHeaders);
    request('DELETE', '/api/v1/views/' . $clientViewId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] web_projects_clients_saved_views_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_projects_clients_saved_views_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
