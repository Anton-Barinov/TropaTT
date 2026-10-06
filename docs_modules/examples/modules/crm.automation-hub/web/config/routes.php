<?php
declare(strict_types=1);

use Module\Crm\AutomationHub\Web\Controller\AutomationHubWebController;

return [
    'GET' => [
        'automation-hub' => [AutomationHubWebController::class, 'index'],
    ],
];
