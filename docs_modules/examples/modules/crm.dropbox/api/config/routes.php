<?php
declare(strict_types=1);

use Module\Crm\Dropbox\Controller\DropboxApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => DropboxApiController::class, 'action' => 'getStatus', 'auth' => true],
];
