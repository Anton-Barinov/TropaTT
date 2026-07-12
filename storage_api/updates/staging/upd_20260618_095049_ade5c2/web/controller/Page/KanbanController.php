<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class KanbanController extends Controller
{
    public function index(): void
    {
        $this->render('page/kanban', [
            'title' => 'Канбан',
            'route' => 'kanban',
        ]);
    }
}
