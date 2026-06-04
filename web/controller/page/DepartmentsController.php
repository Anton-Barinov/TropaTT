<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class DepartmentsController extends Controller
{
    public function index(): void
    {
        $this->render('page/departments', [
            'title' => 'Департаменты',
            'route' => 'departments',
        ]);
    }
}

