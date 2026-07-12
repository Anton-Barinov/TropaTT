<?php
declare(strict_types=1);

namespace Web\Controller\Common;

use Web\System\Core\Controller;

final class NotFoundController extends Controller
{
    public function index(string $route = ''): void
    {
        $this->render('page/not_found', [
            'title' => '404 Not Found',
            'route' => $route,
        ], 404);
    }
}
