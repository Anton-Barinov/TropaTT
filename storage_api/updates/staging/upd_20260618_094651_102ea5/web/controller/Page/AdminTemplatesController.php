<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminTemplatesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_templates', [
            'title' => 'Шаблоны',
            'route' => 'admin-templates',
        ]);
    }
}
