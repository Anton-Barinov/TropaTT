<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $webIndex = dirname(__DIR__, 2) . '/../web/index.php';
    $routesFile = dirname(__DIR__, 2) . '/../web/config/routes.php';
    $bindingsFile = dirname(__DIR__, 2) . '/../web/assets/js/page-api-bindings.js';

    assertTrue(is_file($webIndex), 'web/index.php must exist');
    assertTrue(is_file($routesFile), 'web/config/routes.php must exist');
    assertTrue(is_file($bindingsFile), 'page-api-bindings.js must exist');

    $routes = require $routesFile;
    assertTrue(is_array($routes), 'routes config must be array');
    assertTrue(isset($routes['clients']), 'clients route must be registered');
    assertTrue(isset($routes['client-detail']), 'client-detail route must be registered');

    $_GET = ['route' => 'clients'];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/index.php?route=clients',
        'SCRIPT_NAME' => '/index.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'crm-web-clients-smoke/1.0',
    ];
    ob_start();
    require $webIndex;
    $clientsHtml = (string)ob_get_clean();
    assertTrue(str_contains($clientsHtml, 'id="clientsTableBody"'), 'clients page must contain clients table body');
    assertTrue(str_contains($clientsHtml, 'id="clientsFilterSearch"'), 'clients page must contain search filter');
    assertTrue(str_contains($clientsHtml, 'id="clientCreateModal"'), 'clients page must contain create modal');
    assertTrue(str_contains($clientsHtml, 'id="clientEditModal"'), 'clients page must contain edit modal');

    $_GET = ['route' => 'client-detail', 'client_public_id' => 'cli_TEST'];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/index.php?route=client-detail&client_public_id=cli_TEST',
        'SCRIPT_NAME' => '/index.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'crm-web-clients-smoke/1.0',
    ];
    ob_start();
    require $webIndex;
    $detailHtml = (string)ob_get_clean();
    assertTrue(str_contains($detailHtml, 'id="clientDetailProfile"'), 'client detail page must contain profile block');
    assertTrue(str_contains($detailHtml, 'id="clientDetailTasksBody"'), 'client detail page must contain tasks table');
    assertTrue(str_contains($detailHtml, 'id="clientDetailExtra"'), 'client detail page must contain extra attributes block');

    $bindings = (string)file_get_contents($bindingsFile);
    assertTrue(str_contains($bindings, 'async function renderClientsPage()'), 'renderClientsPage must exist');
    assertTrue(str_contains($bindings, "if (route === 'clients') return await renderClientsPage();"), 'clients route renderer must be connected');
    assertTrue(str_contains($bindings, 'async function renderClientDetailPage()'), 'renderClientDetailPage must exist');
    assertTrue(str_contains($bindings, "if (route === 'client-detail') return await renderClientDetailPage();"), 'client-detail route renderer must be connected');

    echo "[OK] Clients pages web smoke passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Clients pages web smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
