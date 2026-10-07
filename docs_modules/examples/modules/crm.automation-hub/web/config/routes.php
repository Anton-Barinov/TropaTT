<?php
declare(strict_types=1);

use Module\Crm\AutomationHub\Web\Controller\AutomationHubWebController;

return [
    'module-automation-hub' => [AutomationHubWebController::class, 'index'],
];
