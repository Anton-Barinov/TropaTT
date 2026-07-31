<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ClientDetailController extends Controller
{
    public function index(): void
    {
        $this->render('page/client_detail', [
            'title' => 'Карточка клиента',
            'route' => 'client-detail',
        ]);
    }
}
