<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class TasksController extends Controller
{
    public function index(): void
    {
        $this->render('page/tasks', [
            'title' => 'Задачи',
            'route' => 'tasks',
        ]);
    }
}
