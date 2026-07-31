<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminUpdatesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_updates', [
            'title' => 'Админ: Обновления TropaTT',
            'route' => 'admin-updates',
        ]);
    }
}
