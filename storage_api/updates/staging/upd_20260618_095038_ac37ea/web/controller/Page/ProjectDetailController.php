<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ProjectDetailController extends Controller
{
    public function index(): void
    {
        $this->render('page/project_detail', [
            'title' => 'Детали проекта',
            'route' => 'project-detail',
        ]);
    }
}
