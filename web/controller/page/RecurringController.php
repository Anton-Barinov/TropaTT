<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class RecurringController extends Controller
{
    public function index(): void
    {
        $this->render('page/recurring', [
            'title' => 'Периодические задачи',
            'route' => 'recurring',
        ]);
    }
}
