<?php
declare(strict_types=1);

use Module\Crm\StorageConnectors\Web\Controller\StorageConnectorsWebController;

return [
    'module-storage-connectors' => [StorageConnectorsWebController::class, 'index'],
];
