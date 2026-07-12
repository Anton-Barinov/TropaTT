<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class NotificationsController extends Controller
{
    public function index(): void
    {
        $this->render('page/notifications', [
            'title' => 'Уведомления',
            'route' => 'notifications',
        ]);
    }
}
