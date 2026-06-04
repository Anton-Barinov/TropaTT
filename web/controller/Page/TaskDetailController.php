<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class TaskDetailController extends Controller
{
    public function index(): void
    {
        $this->render('page/task_detail', [
            'title' => 'Детали задачи',
            'route' => 'task-detail',
        ]);
    }
}
