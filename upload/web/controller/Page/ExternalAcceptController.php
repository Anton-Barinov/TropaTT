<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ExternalAcceptController extends Controller
{
    public function index(): void
    {
        $this->render('page/external_accept', [
            'title' => 'Доступ в портал',
            'route' => 'external-accept',
        ]);
    }
}
