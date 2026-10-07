<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Web\Controller\CorporateMailWebController;

return [
    'module-corporate-mail' => [CorporateMailWebController::class, 'index'],
];
