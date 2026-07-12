<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class MentionsController extends Controller
{
    public function index(): void
    {
        $this->render('page/mentions', [
            'title' => 'Упоминания',
            'route' => 'mentions',
        ]);
    }
}
