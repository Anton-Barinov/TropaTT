<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $global = request('GET', '/api/v1/search/global?q=Project&limit=5', [], $headers);
    assertTrue($global['status'] === 200, 'Search global status must be 200');
    assertTrue(($global['payload']['code'] ?? '') === 'SEARCH_GLOBAL', 'Search global code mismatch');

    $tasks = request('GET', '/api/v1/search/tasks?q=Task&limit=5', [], $headers);
    assertTrue($tasks['status'] === 200, 'Search tasks status must be 200');
    assertTrue(($tasks['payload']['code'] ?? '') === 'SEARCH_TASKS', 'Search tasks code mismatch');

    $projects = request('GET', '/api/v1/search/projects?q=Project&limit=5', [], $headers);
    assertTrue($projects['status'] === 200, 'Search projects status must be 200');
    assertTrue(($projects['payload']['code'] ?? '') === 'SEARCH_PROJECTS', 'Search projects code mismatch');

    $clients = request('GET', '/api/v1/search/clients?q=smoke&limit=5', [], $headers);
    assertTrue($clients['status'] === 200, 'Search clients status must be 200');
    assertTrue(($clients['payload']['code'] ?? '') === 'SEARCH_CLIENTS', 'Search clients code mismatch');

    $invalid = request('GET', '/api/v1/search/global?q=a', [], $headers);
    assertTrue($invalid['status'] === 422, 'Short query must return 422');

    echo "[OK] Search smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
