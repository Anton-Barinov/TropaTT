<?php
declare(strict_types=1);

use Module\Crm\YandexDisk\Controller\YandexDiskApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => YandexDiskApiController::class, 'action' => 'getStatus', 'auth' => true],
];
