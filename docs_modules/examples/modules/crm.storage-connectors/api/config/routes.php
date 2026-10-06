<?php
declare(strict_types=1);

/** @var \Api\System\Library\Router $router */
$router->get('/api/v1/modules/crm.storage-connectors/preflight', 'Module\Crm\StorageConnectors\Api\Controller\StorageApiController@preflight');
$router->get('/api/v1/modules/crm.storage-connectors/config', 'Module\Crm\StorageConnectors\Api\Controller\StorageApiController@getConfig');
$router->post('/api/v1/modules/crm.storage-connectors/config', 'Module\Crm\StorageConnectors\Api\Controller\StorageApiController@saveConfig');
$router->post('/api/v1/modules/crm.storage-connectors/test-connection', 'Module\Crm\StorageConnectors\Api\Controller\StorageApiController@testConnection');
$router->post('/api/v1/modules/crm.storage-connectors/migrate-batch', 'Module\Crm\StorageConnectors\Api\Controller\StorageApiController@migrateBatch');
