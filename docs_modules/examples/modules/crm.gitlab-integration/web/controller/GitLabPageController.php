<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Controller;

use Web\System\Core\Controller;

final class GitLabPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/gitlab.php', [
            'title' => 'Интеграция с GitLab',
            'route' => 'module-gitlab-integration',
        ]);
    }
}
