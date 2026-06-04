<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ContactsController extends Controller
{
    public function index(): void
    {
        $this->render('page/contacts', [
            'title' => 'Контакты',
            'route' => 'contacts',
        ]);
    }
}

