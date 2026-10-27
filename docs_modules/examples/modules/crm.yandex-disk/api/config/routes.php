<?php
declare(strict_types=1);

use Module\Crm\YandexDisk\Controller\YandexDiskApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => YandexDiskApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/settings', 'controller' => YandexDiskApiController::class, 'action' => 'saveSettings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => YandexDiskApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/upload-file', 'controller' => YandexDiskApiController::class, 'action' => 'uploadFile', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['files.view']],
];
