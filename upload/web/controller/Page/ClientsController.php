<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ClientsController extends Controller
{
    public function index(): void
    {
        $this->render('page/clients', [
            'title' => 'Клиенты',
            'route' => 'clients',
        ]);
    }
}
