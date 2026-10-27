<?php
declare(strict_types=1);

use Module\Crm\PublicForms\Web\Controller\PublicFormsWebController;

return [
    'module-public-forms' => [PublicFormsWebController::class, 'index'],
    'public-form' => [PublicFormsWebController::class, 'publicForm'],
];
