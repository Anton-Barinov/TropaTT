<?php
declare(strict_types=1);

use Module\Crm\Dropbox\Web\Controller\DropboxWebController;

return [
    'module-dropbox' => [DropboxWebController::class, 'index'],
];
