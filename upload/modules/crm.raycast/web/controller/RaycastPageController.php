<?php
declare(strict_types=1);

namespace Module\Crm\Raycast\Controller;

use Web\System\Core\Controller;

final class RaycastPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/raycast.php', [
            'title' => 'Raycast (MCP)',
            'route' => 'module-raycast',
        ]);
    }
}
