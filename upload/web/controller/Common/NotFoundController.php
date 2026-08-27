<?php
declare(strict_types=1);

namespace Web\Controller\Common;

use Web\System\Core\Controller;

final class NotFoundController extends Controller
{
    public function index(string $route = ''): void
    {
        // Detect inactive module routes and show a helpful message
        $moduleHint = '';
        if (preg_match('/^module-([a-z0-9.-]+)$/', $route, $m)) {
            $moduleSlug = $m[1];
            $moduleHint = $moduleSlug;
        }

        $this->render('page/not_found', [
            'title' => '404 Not Found',
            'route' => $route,
            'moduleHint' => $moduleHint,
        ], 404);
    }
}
