<?php
declare(strict_types=1);

use Module\Crm\YandexDisk\Web\Controller\YandexDiskWebController;

return [
    'module-yandex-disk' => [YandexDiskWebController::class, 'index'],
];
