<?php
declare(strict_types=1);

use Module\Crm\Dropbox\Controller\DropboxApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => DropboxApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/settings', 'controller' => DropboxApiController::class, 'action' => 'saveSettings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => DropboxApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/sync-file', 'controller' => DropboxApiController::class, 'action' => 'syncFile', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['files.view']],
];
