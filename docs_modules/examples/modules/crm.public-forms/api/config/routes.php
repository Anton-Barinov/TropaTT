<?php
declare(strict_types=1);

use Module\Crm\PublicForms\Api\Controller\PublicFormsApiController;

return [
    'GET' => [
        'api/v1/public-forms/{slug}' => [PublicFormsApiController::class, 'getForm'],
    ],
    'POST' => [
        'api/v1/public-forms/{slug}/submit' => [PublicFormsApiController::class, 'submitForm'],
    ],
];
