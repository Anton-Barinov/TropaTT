<?php
declare(strict_types=1);

use Module\Crm\SlackIntegration\Controller\SlackPageController;

return [
    'module-slack-integration' => [SlackPageController::class, 'index'],
];
