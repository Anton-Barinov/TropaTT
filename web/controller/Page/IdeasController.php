<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class IdeasController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../../view/template/page/ideas.php', [
            'title' => 'Идеи',
            'route' => 'ideas',
        ]);
    }
}
