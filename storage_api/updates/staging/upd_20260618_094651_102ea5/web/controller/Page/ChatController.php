<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ChatController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../../view/template/page/chat.php', [
            'title' => 'Чаты',
            'route' => 'chat',
        ]);
    }
}
