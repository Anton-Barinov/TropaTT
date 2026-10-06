<?php
declare(strict_types=1);

use Module\Crm\PublicForms\Web\Controller\PublicFormsWebController;

return [
    'GET' => [
        'f/{slug}' => [PublicFormsWebController::class, 'render'],
    ],
];
