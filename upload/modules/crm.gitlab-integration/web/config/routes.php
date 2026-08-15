<?php
declare(strict_types=1);

use Module\Crm\GitlabIntegration\Controller\GitLabPageController;

return [
    'module-gitlab-integration' => [GitLabPageController::class, 'index'],
];
