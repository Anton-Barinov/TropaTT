<?php
declare(strict_types=1);

use Module\Crm\GithubIntegration\Controller\GitHubPageController;

return [
    'module-github-integration' => [GitHubPageController::class, 'index'],
];
