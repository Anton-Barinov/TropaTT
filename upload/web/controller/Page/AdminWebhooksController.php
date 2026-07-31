<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminWebhooksController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_webhooks', [
            'title' => 'Вебхуки',
            'route' => 'admin-webhooks',
        ]);
    }
}
