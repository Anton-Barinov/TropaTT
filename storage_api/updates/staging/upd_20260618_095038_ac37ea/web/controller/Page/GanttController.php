<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class GanttController extends Controller
{
    public function index(): void
    {
        $this->render('page/gantt', [
            'title' => 'Гант',
            'route' => 'gantt',
        ]);
    }
}
