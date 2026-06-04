<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class TeamsController extends Controller
{
    public function index(): void
    {
        $this->render('page/teams', [
            'title' => 'Команды',
            'route' => 'teams',
        ]);
    }
}
