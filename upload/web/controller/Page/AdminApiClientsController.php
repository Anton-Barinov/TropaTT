<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminApiClientsController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_api_clients', [
            'title' => 'Админ: API-клиенты',
            'route' => 'admin-api-clients',
        ]);
    }
}
