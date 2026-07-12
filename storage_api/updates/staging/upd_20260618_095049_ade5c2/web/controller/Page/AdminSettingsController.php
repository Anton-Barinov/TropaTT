<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminSettingsController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_settings', [
            'title' => 'Админ: Системные настройки',
            'route' => 'admin-settings',
        ]);
    }
}
