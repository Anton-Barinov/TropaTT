<?php
declare(strict_types=1);

use Module\Crm\GoogleDrive\Controller\GoogleDriveApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => GoogleDriveApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/settings', 'controller' => GoogleDriveApiController::class, 'action' => 'saveSettings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => GoogleDriveApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/upload-file', 'controller' => GoogleDriveApiController::class, 'action' => 'uploadFile', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['files.view']],
];
