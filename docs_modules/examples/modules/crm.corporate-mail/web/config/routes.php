<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Web\Controller\CorporateMailWebController;

return [
    'GET' => [
        'corporate-mail' => [CorporateMailWebController::class, 'index'],
    ],
];
