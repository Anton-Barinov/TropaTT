<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminPrioritiesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_priorities', [
            'title' => 'Админ: Приоритеты',
            'route' => 'admin-priorities',
        ]);
    }
}
