<?php
declare(strict_types=1);

use Module\Crm\ClientPortal\Web\Controller\ClientPortalWebController;

return [
    'GET' => [
        'client-portal' => [ClientPortalWebController::class, 'index'],
    ],
];
