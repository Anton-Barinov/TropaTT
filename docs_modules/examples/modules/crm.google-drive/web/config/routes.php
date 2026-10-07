<?php
declare(strict_types=1);

use Module\Crm\GoogleDrive\Web\Controller\GoogleDriveWebController;

return [
    'module-google-drive' => [GoogleDriveWebController::class, 'index'],
];
