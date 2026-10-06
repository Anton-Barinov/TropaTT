<?php
declare(strict_types=1);

use Module\Crm\ClientPortal\Api\Controller\ClientPortalApiController;

return [
    'GET' => [
        'api/v1/client-portal/requests' => [ClientPortalApiController::class, 'listRequests'],
        'api/v1/client-portal/requests/{public_id}/messages' => [ClientPortalApiController::class, 'listMessages'],
    ],
    'POST' => [
        'api/v1/client-portal/requests' => [ClientPortalApiController::class, 'createRequest'],
    ],
];
