<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration\Controller;

use Web\System\Core\Controller;

final class GitHubPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/github.php', [
            'title' => 'Интеграция с GitHub',
            'route' => 'module-github-integration',
        ]);
    }
}
