<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ProjectsController extends Controller
{
    public function index(): void
    {
        $this->render('page/projects', [
            'title' => 'Проекты',
            'route' => 'projects',
        ]);
    }
}
