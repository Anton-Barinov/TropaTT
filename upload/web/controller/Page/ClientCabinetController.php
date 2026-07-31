<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ClientCabinetController extends Controller
{
    public function index(): void
    {
        $this->render('page/client_cabinet', [
            'title' => 'Кабинет клиента',
            'route' => 'client-cabinet',
        ]);
    }
}
