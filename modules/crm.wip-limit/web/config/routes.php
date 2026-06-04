<?php
declare(strict_types=1);

use Module\Crm\WipLimit\Controller\WipPageController;

return [
    'module-wip-limit' => [WipPageController::class, 'index'],
];
